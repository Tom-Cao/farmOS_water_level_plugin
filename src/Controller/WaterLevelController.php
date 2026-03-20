<?php

namespace Drupal\farm_water_level\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\farm_water_level\ThingSpeakClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * Builds a ThingSpeak chart iframe URL.
   */
  protected function buildChartUrl(string $channel_id, int $field_number, string $api_key, int $results, array $extra = []): string {
    $params = [
      'width' => 'auto',
      'height' => $extra['height'] ?? '200',
      'yaxismin' => '0',
      'yaxismax' => '100',
      'title' => $extra['title'] ?? '',
      'xaxis' => $extra['xaxis'] ?? '',
      'yaxis' => $extra['yaxis'] ?? '',
      'color' => '#1a73e8',
      'bgcolor' => '#ffffff',
      'dynamic' => 'true',
      'results' => (string) $results,
      'type' => 'line',
    ];
    if (!empty($api_key)) {
      $params['api_key'] = $api_key;
    }
    return sprintf(
      'https://thingspeak.com/channels/%s/charts/%d?%s',
      $channel_id,
      $field_number,
      http_build_query($params),
    );
  }

  /**
   * Dashboard: grid of sensor cards with mini charts + add card.
   */
  public function dashboard() {
    $config = $this->config('farm_water_level.settings');
    $sensors = $config->get('sensors') ?: [];
    $results = $config->get('results_to_fetch') ?: 100;

    $build = [];
    $build['#attached']['library'][] = 'farm_water_level/dashboard';

    $build['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['water-level-grid']],
    ];

    foreach ($sensors as $sensor_id => $sensor) {
      [$channel_id, $api_key] = $this->thingSpeakClient->resolveChannel($sensor);
      $field_number = $sensor['field_number'] ?? 1;
      $detail_url = Url::fromRoute('farm_water_level.sensor_detail', ['sensor_id' => $sensor_id])->toString();

      if (!empty($channel_id)) {
        $chart_url = $this->buildChartUrl($channel_id, $field_number, $api_key, $results);
        $template = '<a href="{{ detail_url }}" class="water-level-card">
            <div class="water-level-card__chart">
              <iframe src="{{ chart_url }}" width="100%" height="200" frameborder="0"></iframe>
            </div>
            <div class="water-level-card__label">{{ name }}</div>
          </a>';
      }
      else {
        $chart_url = '';
        $template = '<a href="{{ detail_url }}" class="water-level-card">
            <div class="water-level-card__chart" style="display:flex;align-items:center;justify-content:center;color:#94a3b8;">No channel configured</div>
            <div class="water-level-card__label">{{ name }}</div>
          </a>';
      }

      $build['grid'][$sensor_id] = [
        '#type' => 'inline_template',
        '#template' => $template,
        '#context' => [
          'chart_url' => $chart_url,
          'detail_url' => $detail_url,
          'name' => $sensor['name'],
        ],
      ];
    }

    $build['grid']['add'] = [
      '#type' => 'inline_template',
      '#template' => '<a href="{{ url }}" class="water-level-add-card" title="{{ label }}">+</a>',
      '#context' => [
        'url' => Url::fromRoute('farm_water_level.sensor_add')->toString(),
        'label' => $this->t('Add sensor'),
      ],
    ];

    $build['#cache'] = ['max-age' => 300];

    return $build;
  }

  /**
   * Detail page for a single sensor.
   */
  public function sensorDetail(string $sensor_id) {
    $config = $this->config('farm_water_level.settings');
    $sensors = $config->get('sensors') ?: [];

    if (!isset($sensors[$sensor_id])) {
      throw new NotFoundHttpException();
    }

    $sensor = $sensors[$sensor_id];
    [$channel_id, $api_key] = $this->thingSpeakClient->resolveChannel($sensor);
    $field_number = $sensor['field_number'] ?? 1;
    $results = $config->get('results_to_fetch') ?: 100;

    $build = [];

    if (empty($channel_id)) {
      $this->messenger()->addWarning($this->t('No ThingSpeak channel configured for this sensor.'));
      $build['back'] = [
        '#type' => 'link',
        '#title' => $this->t('Back to dashboard'),
        '#url' => Url::fromRoute('farm_water_level.dashboard'),
        '#attributes' => ['class' => ['button']],
      ];
      return $build;
    }

    // Full-size chart.
    $chart_url = $this->buildChartUrl($channel_id, $field_number, $api_key, $results, [
      'height' => '400',
      'title' => $sensor['name'],
      'xaxis' => 'Time',
      'yaxis' => 'Water Level (cm)',
    ]);

    $build['chart'] = [
      '#type' => 'inline_template',
      '#template' => '<iframe src="{{ url }}" width="100%" height="420" frameborder="0" style="border:1px solid #ddd; border-radius:4px; margin-bottom:1.5em;"></iframe>',
      '#context' => ['url' => $chart_url],
    ];

    // Latest reading.
    $latest_entries = $this->thingSpeakClient->fetchForSensor($sensor, 1);
    $latest = $latest_entries[0] ?? NULL;

    if ($latest) {
      $build['latest'] = [
        '#markup' => '<div class="water-level-latest-detail"><strong>' .
          $this->t('Current: @value cm', ['@value' => $latest['value']]) .
          '</strong> &mdash; ' .
          $this->t('@time', ['@time' => $latest['created_at']]) .
          '</div>',
      ];
    }

    // Data table.
    $entries = $this->thingSpeakClient->fetchForSensor($sensor, $results);

    if (!empty($entries)) {
      $rows = [];
      foreach (array_reverse($entries) as $entry) {
        $rows[] = [
          $entry['entry_id'] ?? '-',
          $entry['created_at'],
          $entry['value'],
        ];
      }
      $build['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Entry ID'),
          $this->t('Timestamp'),
          $this->t('Water Level (cm)'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No readings found.'),
      ];
    }

    $build['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to dashboard'),
      '#url' => Url::fromRoute('farm_water_level.dashboard'),
      '#attributes' => ['class' => ['button']],
    ];

    $build['#cache'] = ['max-age' => 300];

    return $build;
  }

  /**
   * Title callback for the sensor detail page.
   */
  public function sensorDetailTitle(string $sensor_id): string {
    $sensors = $this->config('farm_water_level.settings')->get('sensors') ?: [];
    return $sensors[$sensor_id]['name'] ?? 'Sensor';
  }

}
