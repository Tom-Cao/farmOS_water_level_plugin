<?php

namespace Drupal\farm_water_level\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for adding or editing a sensor with all its ThingSpeak settings.
 */
class SensorForm extends ConfigFormBase {

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
    return 'farm_water_level_sensor_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $sensor_id = NULL) {
    $config = $this->config('farm_water_level.settings');
    $sensors = $config->get('sensors') ?: [];
    $sensor = $sensor_id !== NULL && isset($sensors[$sensor_id]) ? $sensors[$sensor_id] : [];

    $global_channel = $config->get('thingspeak_channel_id') ?: '';
    $global_api_key = $config->get('thingspeak_read_api_key') ?: '';

    $form['sensor_id'] = [
      '#type' => 'value',
      '#value' => $sensor_id,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sensor Name'),
      '#description' => $this->t('A human-readable name (e.g. "Well #3", "Pond North").'),
      '#default_value' => $sensor['name'] ?? '',
      '#required' => TRUE,
    ];

    $form['field_number'] = [
      '#type' => 'number',
      '#title' => $this->t('ThingSpeak Field Number'),
      '#description' => $this->t('The field (1–8) on the channel that this sensor writes to.'),
      '#default_value' => $sensor['field_number'] ?? 1,
      '#min' => 1,
      '#max' => 8,
      '#required' => TRUE,
    ];

    $form['thingspeak'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('ThingSpeak Channel'),
      '#description' => $this->t('Leave blank to use the global defaults (Channel: @ch).', [
        '@ch' => $global_channel ?: $this->t('not set'),
      ]),
    ];

    $form['thingspeak']['channel_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Channel ID'),
      '#description' => $this->t('Override the global channel ID for this sensor.'),
      '#default_value' => $sensor['channel_id'] ?? '',
      '#placeholder' => $global_channel ?: '',
    ];

    $form['thingspeak']['read_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Read API Key'),
      '#description' => $this->t('Override the global Read API key for this sensor.'),
      '#default_value' => $sensor['read_api_key'] ?? '',
      '#placeholder' => $global_api_key ? '••••••••' : '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $config = $this->config('farm_water_level.settings');
    $sensors = $config->get('sensors') ?: [];
    $field_number = (int) $form_state->getValue('field_number');
    $channel_id = $form_state->getValue('channel_id') ?: $config->get('thingspeak_channel_id');
    $current_id = $form_state->getValue('sensor_id');

    foreach ($sensors as $id => $sensor) {
      if ($id === $current_id) {
        continue;
      }
      $other_channel = $sensor['channel_id'] ?: $config->get('thingspeak_channel_id');
      if ($sensor['field_number'] === $field_number && $other_channel === $channel_id) {
        $form_state->setErrorByName('field_number', $this->t(
          'Field @num on this channel is already used by sensor %name.',
          ['@num' => $field_number, '%name' => $sensor['name']],
        ));
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('farm_water_level.settings');
    $sensors = $config->get('sensors') ?: [];

    $sensor_id = $form_state->getValue('sensor_id');
    if ($sensor_id === NULL) {
      $sensor_id = preg_replace('/[^a-z0-9_]/', '_', strtolower($form_state->getValue('name')));
      $sensor_id = preg_replace('/_+/', '_', trim($sensor_id, '_'));
      $base = $sensor_id;
      $i = 1;
      while (isset($sensors[$sensor_id])) {
        $sensor_id = $base . '_' . $i++;
      }
    }

    $sensors[$sensor_id] = [
      'name' => $form_state->getValue('name'),
      'field_number' => (int) $form_state->getValue('field_number'),
      'channel_id' => trim($form_state->getValue('channel_id') ?? ''),
      'read_api_key' => trim($form_state->getValue('read_api_key') ?? ''),
    ];

    $config->set('sensors', $sensors)->save();

    $this->messenger()->addStatus($this->t('Sensor %name saved.', ['%name' => $form_state->getValue('name')]));
    $form_state->setRedirectUrl(Url::fromRoute('farm_water_level.dashboard'));
  }

}
