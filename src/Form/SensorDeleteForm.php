<?php

namespace Drupal\farm_water_level\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Confirmation form for deleting a sensor.
 */
class SensorDeleteForm extends ConfirmFormBase {

  protected string $sensorId;
  protected string $sensorName;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_water_level_sensor_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to remove sensor %name?', ['%name' => $this->sensorName]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('farm_water_level.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $sensor_id = NULL) {
    $config = $this->config('farm_water_level.settings');
    $sensors = $config->get('sensors') ?: [];

    if ($sensor_id === NULL || !isset($sensors[$sensor_id])) {
      $this->messenger()->addError($this->t('Sensor not found.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return $form;
    }

    $this->sensorId = $sensor_id;
    $this->sensorName = $sensors[$sensor_id]['name'];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('farm_water_level.settings');
    $sensors = $config->get('sensors') ?: [];

    unset($sensors[$this->sensorId]);
    $config->set('sensors', $sensors)->save();

    $this->messenger()->addStatus($this->t('Sensor %name has been removed.', ['%name' => $this->sensorName]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
