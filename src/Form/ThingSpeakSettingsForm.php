<?php

namespace Drupal\farm_water_level\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for ThingSpeak water level sensor settings.
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

    $form['thingspeak_channel_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ThingSpeak Channel ID'),
      '#description' => $this->t('The numeric channel ID from your ThingSpeak channel.'),
      '#default_value' => $config->get('thingspeak_channel_id'),
      '#required' => TRUE,
    ];

    $form['thingspeak_read_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ThingSpeak Read API Key'),
      '#description' => $this->t('The Read API key for your ThingSpeak channel. Required for private channels.'),
      '#default_value' => $config->get('thingspeak_read_api_key'),
    ];

    $form['thingspeak_field_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Field Number'),
      '#description' => $this->t('The ThingSpeak field number that contains the water level data (1-8).'),
      '#default_value' => $config->get('thingspeak_field_number') ?: 1,
      '#min' => 1,
      '#max' => 8,
      '#required' => TRUE,
    ];

    $form['sync_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Sync Interval (seconds)'),
      '#description' => $this->t('How often to pull new readings from ThingSpeak during cron. Default: 3600 (1 hour).'),
      '#default_value' => $config->get('sync_interval') ?: 3600,
      '#min' => 60,
      '#required' => TRUE,
    ];

    $form['results_to_fetch'] = [
      '#type' => 'number',
      '#title' => $this->t('Results per Request'),
      '#description' => $this->t('Number of data points to fetch from ThingSpeak per API call.'),
      '#default_value' => $config->get('results_to_fetch') ?: 100,
      '#min' => 1,
      '#max' => 8000,
      '#required' => TRUE,
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
      ->set('thingspeak_field_number', (int) $form_state->getValue('thingspeak_field_number'))
      ->set('sync_interval', (int) $form_state->getValue('sync_interval'))
      ->set('results_to_fetch', (int) $form_state->getValue('results_to_fetch'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
