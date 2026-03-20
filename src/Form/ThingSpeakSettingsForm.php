<?php

namespace Drupal\farm_water_level\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Global settings form for the water level sensor module.
 */
class ThingSpeakSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['farm_water_level.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_water_level_thingspeak_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('farm_water_level.settings');

    $form['channel'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('ThingSpeak Channel'),
    ];

    $form['channel']['thingspeak_channel_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Channel ID'),
      '#description' => $this->t('The numeric channel ID from your ThingSpeak channel. All sensors share this channel.'),
      '#default_value' => $config->get('thingspeak_channel_id'),
      '#required' => TRUE,
    ];

    $form['channel']['thingspeak_read_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Read API Key'),
      '#description' => $this->t('Required for private channels.'),
      '#default_value' => $config->get('thingspeak_read_api_key'),
    ];

    $form['sync'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sync Settings'),
    ];

    $form['sync']['sync_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Sync Interval (seconds)'),
      '#description' => $this->t('How often to pull new readings from ThingSpeak during cron.'),
      '#default_value' => $config->get('sync_interval') ?: 3600,
      '#min' => 60,
      '#required' => TRUE,
    ];

    $form['sync']['results_to_fetch'] = [
      '#type' => 'number',
      '#title' => $this->t('Results per Request'),
      '#description' => $this->t('Number of data points to fetch from ThingSpeak per API call.'),
      '#default_value' => $config->get('results_to_fetch') ?: 100,
      '#min' => 1,
      '#max' => 8000,
      '#required' => TRUE,
    ];

    // Sensors table.
    $sensors = $config->get('sensors') ?: [];
    $form['sensors_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sensors'),
      '#description' => $this->t('Each sensor maps to a field (1–8) on the ThingSpeak channel above.'),
    ];

    if (!empty($sensors)) {
      $header = [
        $this->t('Name'),
        $this->t('Field'),
        $this->t('Operations'),
      ];
      $rows = [];
      foreach ($sensors as $sensor_id => $sensor) {
        $rows[] = [
          $sensor['name'],
          $sensor['field_number'],
          [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'edit' => [
                  'title' => $this->t('Edit'),
                  'url' => Url::fromRoute('farm_water_level.sensor_edit', ['sensor_id' => $sensor_id]),
                ],
                'delete' => [
                  'title' => $this->t('Delete'),
                  'url' => Url::fromRoute('farm_water_level.sensor_delete', ['sensor_id' => $sensor_id]),
                ],
              ],
            ],
          ],
        ];
      }

      $form['sensors_section']['sensors_table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
    }

    $form['sensors_section']['add_sensor'] = [
      '#type' => 'link',
      '#title' => $this->t('+ Add sensor'),
      '#url' => Url::fromRoute('farm_water_level.sensor_add'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('farm_water_level.settings')
      ->set('thingspeak_channel_id', $form_state->getValue('thingspeak_channel_id'))
      ->set('thingspeak_read_api_key', $form_state->getValue('thingspeak_read_api_key'))
      ->set('sync_interval', (int) $form_state->getValue('sync_interval'))
      ->set('results_to_fetch', (int) $form_state->getValue('results_to_fetch'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
