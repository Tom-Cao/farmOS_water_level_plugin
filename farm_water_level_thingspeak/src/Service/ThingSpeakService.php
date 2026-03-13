<?php

namespace Drupal\farm_water_level_thingspeak\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for fetching water level data from ThingSpeak.
 */
class ThingSpeakService {

  /**
   * The ThingSpeak API base URL.
   */
  const THINGSPEAK_API_URL = 'https://api.thingspeak.com';

  /**
   * The state key for tracking the last synced entry ID.
   */
  const LAST_ENTRY_STATE_KEY = 'farm_water_level_thingspeak.last_entry_id';

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Constructs a new ThingSpeakService.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    StateInterface $state,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('farm_water_level_thingspeak');
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  /**
   * Fetches the latest feeds from a ThingSpeak channel.
   *
   * @param string $channel_id
   *   The ThingSpeak channel ID.
   * @param string $api_key
   *   The ThingSpeak Read API key (empty for public channels).
   * @param int $results
   *   The number of results to fetch (1-8000).
   * @param int|null $since_entry_id
   *   Only fetch entries after this entry ID (optional).
   *
   * @return array
   *   An array of feed entries, each with 'created_at', 'entry_id', and
   *   field values (e.g. 'field1', 'field2', etc.), or an empty array on
   *   failure.
   */
  public function fetchFeeds(string $channel_id, string $api_key, int $results = 100, ?int $since_entry_id = NULL): array {
    if (empty($channel_id)) {
      $this->logger->warning('ThingSpeak channel ID is not configured.');
      return [];
    }

    $url = self::THINGSPEAK_API_URL . '/channels/' . $channel_id . '/feeds.json';
    $query = ['results' => $results];
    if (!empty($api_key)) {
      $query['api_key'] = $api_key;
    }
    if ($since_entry_id !== NULL) {
      $query['start_entry_id'] = $since_entry_id;
    }

    try {
      $response = $this->httpClient->request('GET', $url, ['query' => $query]);
      $body = (string) $response->getBody();
      $data = json_decode($body, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->error('Invalid JSON response from ThingSpeak: @error', [
          '@error' => json_last_error_msg(),
        ]);
        return [];
      }

      if (!isset($data['feeds']) || !is_array($data['feeds'])) {
        $this->logger->warning('Unexpected ThingSpeak response format: missing "feeds" key.');
        return [];
      }

      return $data['feeds'];
    }
    catch (RequestException $e) {
      $this->logger->error('Error fetching data from ThingSpeak: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Syncs water level data from ThingSpeak into farmOS observation logs.
   *
   * Called by hook_cron() and the manual sync controller.
   *
   * @return int
   *   The number of new log entries created.
   */
  public function syncData(): int {
    $config = $this->configFactory->get('farm_water_level_thingspeak.settings');
    $channel_id = $config->get('channel_id');
    $api_key = $config->get('api_key');
    $water_level_field = $config->get('water_level_field') ?: 'field1';
    $units = $config->get('units') ?: 'm';
    $log_name_prefix = $config->get('log_name_prefix') ?: 'Water Level';
    $sync_interval = (int) ($config->get('sync_interval') ?: 900);

    if (empty($channel_id)) {
      $this->logger->notice('Water Level ThingSpeak sync skipped: channel ID not configured.');
      return 0;
    }

    // Check if enough time has passed since the last sync.
    $last_sync = $this->state->get('farm_water_level_thingspeak.last_sync', 0);
    if ((time() - $last_sync) < $sync_interval) {
      return 0;
    }

    $last_entry_id = $this->state->get(self::LAST_ENTRY_STATE_KEY, NULL);
    $feeds = $this->fetchFeeds($channel_id, $api_key, 100, $last_entry_id);

    if (empty($feeds)) {
      $this->state->set('farm_water_level_thingspeak.last_sync', time());
      return 0;
    }

    $created_count = 0;
    foreach ($feeds as $feed) {
      // Skip entries already imported.
      $entry_id = isset($feed['entry_id']) ? (int) $feed['entry_id'] : NULL;
      if ($entry_id !== NULL && $last_entry_id !== NULL && $entry_id <= $last_entry_id) {
        continue;
      }

      $water_level_value = $feed[$water_level_field] ?? NULL;
      if ($water_level_value === NULL || $water_level_value === '') {
        continue;
      }

      $timestamp = isset($feed['created_at'])
        ? strtotime($feed['created_at'])
        : time();
      if ($timestamp === FALSE) {
        $timestamp = time();
      }

      $log_name = $log_name_prefix . ' - ' . date('Y-m-d H:i:s', $timestamp);

      if ($this->createObservationLog($log_name, (float) $water_level_value, $units, $timestamp, $entry_id)) {
        $created_count++;
        if ($entry_id !== NULL) {
          $last_entry_id = max($last_entry_id ?? 0, $entry_id);
        }
      }
    }

    if ($last_entry_id !== NULL) {
      $this->state->set(self::LAST_ENTRY_STATE_KEY, $last_entry_id);
    }
    $this->state->set('farm_water_level_thingspeak.last_sync', time());

    if ($created_count > 0) {
      $this->logger->info('Water Level ThingSpeak: imported @count new readings.', [
        '@count' => $created_count,
      ]);
    }

    return $created_count;
  }

  /**
   * Creates a farmOS observation log with a water level quantity.
   *
   * @param string $name
   *   The log name.
   * @param float $value
   *   The water level value.
   * @param string $units
   *   The unit of measurement (e.g. 'm', 'ft', 'cm').
   * @param int $timestamp
   *   The Unix timestamp of the reading.
   * @param int|null $entry_id
   *   The ThingSpeak entry ID (stored in log notes).
   *
   * @return bool
   *   TRUE if the log was created successfully, FALSE otherwise.
   */
  public function createObservationLog(string $name, float $value, string $units, int $timestamp, ?int $entry_id = NULL): bool {
    try {
      $log_storage = $this->entityTypeManager->getStorage('log');
      $quantity_storage = $this->entityTypeManager->getStorage('quantity');

      // Build notes with the ThingSpeak entry ID for traceability.
      $notes = '';
      if ($entry_id !== NULL) {
        $notes = 'ThingSpeak Entry ID: ' . $entry_id;
      }

      // Create the water level quantity.
      $quantity = $quantity_storage->create([
        'type' => 'standard',
        'measure' => 'length',
        'value' => [
          'decimal' => (string) $value,
        ],
        'units' => $units,
        'label' => 'Water Level',
      ]);
      $quantity->save();

      // Create the observation log.
      $log_values = [
        'type' => 'observation',
        'name' => $name,
        'timestamp' => $timestamp,
        'status' => 'done',
        'quantity' => [['target_id' => $quantity->id()]],
      ];
      if (!empty($notes)) {
        $log_values['notes'] = ['value' => $notes, 'format' => 'default'];
      }

      $log = $log_storage->create($log_values);
      $log->save();

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create observation log for water level: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Returns the timestamp of the last successful sync.
   *
   * @return int
   *   Unix timestamp of the last sync, or 0 if never synced.
   */
  public function getLastSyncTime(): int {
    return (int) $this->state->get('farm_water_level_thingspeak.last_sync', 0);
  }

  /**
   * Returns the last imported ThingSpeak entry ID.
   *
   * @return int|null
   *   The last entry ID, or NULL if no data has been imported yet.
   */
  public function getLastEntryId(): ?int {
    return $this->state->get(self::LAST_ENTRY_STATE_KEY, NULL);
  }

  /**
   * Clears the throttle so the next syncData() call will not be rate-limited.
   *
   * The last imported entry ID is preserved so no duplicate logs are created.
   */
  public function clearSyncThrottle(): void {
    $this->state->delete('farm_water_level_thingspeak.last_sync');
  }

  /**
   * Resets the full sync state so all data will be re-imported on next sync.
   *
   * Use with caution: this removes the last imported entry ID, which may cause
   * duplicate log entries for data that has already been imported.
   */
  public function resetSyncState(): void {
    $this->state->delete(self::LAST_ENTRY_STATE_KEY);
    $this->state->delete('farm_water_level_thingspeak.last_sync');
  }

}
