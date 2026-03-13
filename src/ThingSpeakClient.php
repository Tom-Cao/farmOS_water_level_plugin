<?php

namespace Drupal\farm_water_level;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for fetching water level data from the ThingSpeak Read API.
 */
class ThingSpeakClient {

  const BASE_URL = 'https://api.thingspeak.com';

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    LoggerInterface $logger,
  ) {
    $this->config = $config_factory->get('farm_water_level.settings');
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * Fetches the latest feed entries from the configured ThingSpeak channel.
   *
   * @param int|null $results
   *   Number of results to return. Falls back to configured default.
   *
   * @return array
   *   Array of feed entries, each with 'created_at' and 'value' keys.
   */
  public function fetchFeed(?int $results = NULL): array {
    $channel_id = $this->config->get('thingspeak_channel_id');
    $api_key = $this->config->get('thingspeak_read_api_key');
    $field_number = $this->config->get('thingspeak_field_number') ?: 1;
    $results = $results ?? ($this->config->get('results_to_fetch') ?: 100);

    if (empty($channel_id)) {
      $this->logger->warning('ThingSpeak channel ID is not configured.');
      return [];
    }

    $url = sprintf(
      '%s/channels/%s/fields/%d.json',
      self::BASE_URL,
      $channel_id,
      $field_number,
    );

    $query = ['results' => $results];
    if (!empty($api_key)) {
      $query['api_key'] = $api_key;
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        'query' => $query,
        'timeout' => 15,
      ]);

      $data = json_decode((string) $response->getBody(), TRUE);

      if (empty($data['feeds'])) {
        return [];
      }

      $field_key = 'field' . $field_number;
      $entries = [];
      foreach ($data['feeds'] as $feed) {
        if (isset($feed[$field_key]) && $feed[$field_key] !== NULL) {
          $entries[] = [
            'entry_id' => isset($feed['entry_id']) ? (int) $feed['entry_id'] : NULL,
            'created_at' => $feed['created_at'] ?? '',
            'value' => (float) $feed[$field_key],
          ];
        }
      }

      return $entries;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch ThingSpeak data: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Fetches the single most recent entry from the channel.
   *
   * @return array|null
   *   The latest entry or NULL if unavailable.
   */
  public function fetchLatest(): ?array {
    $entries = $this->fetchFeed(1);
    return $entries[0] ?? NULL;
  }

  /**
   * Fetches channel metadata from ThingSpeak.
   *
   * @return array
   *   Channel metadata array, or empty array on failure.
   */
  public function fetchChannelInfo(): array {
    $channel_id = $this->config->get('thingspeak_channel_id');
    $api_key = $this->config->get('thingspeak_read_api_key');

    if (empty($channel_id)) {
      return [];
    }

    $url = sprintf('%s/channels/%s.json', self::BASE_URL, $channel_id);
    $query = [];
    if (!empty($api_key)) {
      $query['api_key'] = $api_key;
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        'query' => $query,
        'timeout' => 15,
      ]);
      return json_decode((string) $response->getBody(), TRUE) ?: [];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch ThingSpeak channel info: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

}
