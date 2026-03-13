<?php

namespace Drupal\farm_water_level_thingspeak\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\farm_water_level_thingspeak\Service\ThingSpeakService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the farmOS Water Level ThingSpeak integration.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The ThingSpeak service.
   *
   * @var \Drupal\farm_water_level_thingspeak\Service\ThingSpeakService
   */
  protected ThingSpeakService $thingSpeakService;

  /**
   * Constructs a new SettingsForm.
   *
   * @param \Drupal\farm_water_level_thingspeak\Service\ThingSpeakService $thingspeak_service
   *   The ThingSpeak service.
   */
  public function __construct(ThingSpeakService $thingspeak_service, ...$parent_args) {
    parent::__construct(...$parent_args);
    $this->thingSpeakService = $thingspeak_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_water_level_thingspeak.thingspeak_service'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['farm_water_level_thingspeak.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_water_level_thingspeak_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('farm_water_level_thingspeak.settings');

    $form['thingspeak'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('ThingSpeak Connection'),
    ];

    $form['thingspeak']['channel_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Channel ID'),
      '#description' => $this->t('The ThingSpeak channel ID containing your water level sensor data.'),
      '#default_value' => $config->get('channel_id'),
      '#required' => TRUE,
    ];

    $form['thingspeak']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Read API Key'),
      '#description' => $this->t('The ThingSpeak Read API Key for private channels. Leave empty for public channels.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['thingspeak']['water_level_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Water Level Field'),
      '#description' => $this->t('The ThingSpeak field that contains the water level readings.'),
      '#options' => [
        'field1' => $this->t('Field 1'),
        'field2' => $this->t('Field 2'),
        'field3' => $this->t('Field 3'),
        'field4' => $this->t('Field 4'),
        'field5' => $this->t('Field 5'),
        'field6' => $this->t('Field 6'),
        'field7' => $this->t('Field 7'),
        'field8' => $this->t('Field 8'),
      ],
      '#default_value' => $config->get('water_level_field') ?: 'field1',
    ];

    $form['import'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Import Settings'),
    ];

    $form['import']['sync_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Sync Interval'),
      '#description' => $this->t('How frequently to fetch new data from ThingSpeak during cron runs.'),
      '#options' => [
        300 => $this->t('Every 5 minutes'),
        900 => $this->t('Every 15 minutes'),
        1800 => $this->t('Every 30 minutes'),
        3600 => $this->t('Every hour'),
        86400 => $this->t('Every day'),
      ],
      '#default_value' => $config->get('sync_interval') ?: 900,
    ];

    $form['import']['units'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Units'),
      '#description' => $this->t('The unit of measurement for the water level readings (e.g. m, ft, cm, in).'),
      '#default_value' => $config->get('units') ?: 'm',
      '#size' => 10,
      '#maxlength' => 20,
    ];

    $form['import']['log_name_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Log Name Prefix'),
      '#description' => $this->t('Prefix for the farmOS observation log names. The timestamp will be appended automatically.'),
      '#default_value' => $config->get('log_name_prefix') ?: 'Water Level',
      '#maxlength' => 100,
    ];

    // Show sync status.
    $last_sync = $this->thingSpeakService->getLastSyncTime();
    $last_entry_id = $this->thingSpeakService->getLastEntryId();

    $form['status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sync Status'),
    ];

    $form['status']['last_sync'] = [
      '#type' => 'item',
      '#title' => $this->t('Last Sync'),
      '#markup' => $last_sync > 0
        ? $this->t('@date', ['@date' => date('Y-m-d H:i:s', $last_sync)])
        : $this->t('Never'),
    ];

    $form['status']['last_entry_id'] = [
      '#type' => 'item',
      '#title' => $this->t('Last Imported Entry ID'),
      '#markup' => $last_entry_id !== NULL ? (string) $last_entry_id : $this->t('None'),
    ];

    $form['status']['reset_sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset sync state'),
      '#description' => $this->t('Check this to reset the sync state so all data will be re-imported on the next sync. Use with caution as this may create duplicate log entries.'),
      '#default_value' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $channel_id = trim($form_state->getValue('channel_id'));
    if (!empty($channel_id) && !ctype_digit($channel_id)) {
      $form_state->setErrorByName('channel_id', $this->t('The channel ID must be a numeric value.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('farm_water_level_thingspeak.settings')
      ->set('channel_id', trim($form_state->getValue('channel_id')))
      ->set('api_key', trim($form_state->getValue('api_key')))
      ->set('water_level_field', $form_state->getValue('water_level_field'))
      ->set('sync_interval', (int) $form_state->getValue('sync_interval'))
      ->set('units', trim($form_state->getValue('units')))
      ->set('log_name_prefix', trim($form_state->getValue('log_name_prefix')))
      ->save();

    if ($form_state->getValue('reset_sync')) {
      $this->thingSpeakService->resetSyncState();
      $this->messenger()->addStatus($this->t('Sync state has been reset.'));
    }

    parent::submitForm($form, $form_state);
  }

}
