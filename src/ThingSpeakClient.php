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
   * Resolves channel ID and API key for a sensor, falling back to globals.
   *
   * @return array{string, string}
   *   [channel_id, api_key]
   */
  public function resolveChannel(array $sensor): array {
    $channel_id = !empty($sensor['channel_id']) ? $sensor['channel_id'] : ($this->config->get('thingspeak_channel_id') ?: '');
    $api_key = !empty($sensor['read_api_key']) ? $sensor['read_api_key'] : ($this->config->get('thingspeak_read_api_key') ?: '');
    return [$channel_id, $api_key];
  }

  /**
   * Fetches feed entries for a sensor.
   *
   * Uses the sensor's own channel/key if set, otherwise the global defaults.
   *
   * @param array $sensor
   *   Sensor config with keys: field_number, and optionally channel_id, read_api_key.
   * @param int|null $results
   *   Number of results to return.
   *
   * @return array
   *   Array of feed entries, each with 'entry_id', 'created_at', 'value'.
   */
  public function fetchForSensor(array $sensor, ?int $results = NULL): array {
    [$channel_id, $api_key] = $this->resolveChannel($sensor);
    $field_number = $sensor['field_number'] ?? 1;
    $results = $results ?? ($this->config->get('results_to_fetch') ?: 100);

    if (empty($channel_id)) {
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
      $this->logger->error('Failed to fetch ThingSpeak channel @ch field @field: @message', [
        '@ch' => $channel_id,
        '@field' => $field_number,
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

}
