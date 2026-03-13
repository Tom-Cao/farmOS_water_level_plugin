<?php

namespace Drupal\farm_water_level\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\farm_water_level\ThingSpeakClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying water level sensor data.
 */
class WaterLevelController extends ControllerBase {

  /**
   * @var \Drupal\farm_water_level\ThingSpeakClient
   */
  protected $thingSpeakClient;

  public function __construct(ThingSpeakClient $thingspeak_client) {
    $this->thingSpeakClient = $thingspeak_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_water_level.thingspeak_client'),
    );
  }

  /**
   * Displays the water level dashboard with current and historical readings.
   */
  public function dashboard() {
    $config = $this->config('farm_water_level.settings');
    $channel_id = $config->get('thingspeak_channel_id');

    if (empty($channel_id)) {
      $this->messenger()->addWarning($this->t(
        'ThingSpeak is not configured. Please <a href=":url">configure the module</a> first.',
        [':url' => '/admin/config/farm/water-level'],
      ));
      return ['#markup' => ''];
    }

    $latest = $this->thingSpeakClient->fetchLatest();
    $entries = $this->thingSpeakClient->fetchFeed();

    $build = [];

    $build['latest'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['water-level-latest']],
    ];

    if ($latest) {
      $build['latest']['heading'] = [
        '#markup' => '<h2>' . $this->t('Current Reading') . '</h2>',
      ];
      $build['latest']['value'] = [
        '#markup' => '<div class="water-level-value"><strong>' .
          $this->t('Water Level: @value', ['@value' => $latest['value']]) .
          '</strong></div>',
      ];
      $build['latest']['timestamp'] = [
        '#markup' => '<div class="water-level-timestamp">' .
          $this->t('Recorded: @time', ['@time' => $latest['created_at']]) .
          '</div>',
      ];
    }
    else {
      $build['latest']['empty'] = [
        '#markup' => '<p>' . $this->t('No data available from ThingSpeak.') . '</p>',
      ];
    }

    if (!empty($entries)) {
      $header = [
        $this->t('Entry ID'),
        $this->t('Timestamp'),
        $this->t('Water Level'),
      ];

      $rows = [];
      foreach (array_reverse($entries) as $entry) {
        $rows[] = [
          $entry['entry_id'] ?? '-',
          $entry['created_at'],
          $entry['value'],
        ];
      }

      $build['history'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['water-level-history']],
      ];
      $build['history']['heading'] = [
        '#markup' => '<h2>' . $this->t('Recent Readings') . '</h2>',
      ];
      $build['history']['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No readings found.'),
      ];
    }

    $build['#cache'] = [
      'max-age' => 300,
    ];

    return $build;
  }

}
