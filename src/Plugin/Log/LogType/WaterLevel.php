<?php

namespace Drupal\farm_water_level\Plugin\Log\LogType;

use Drupal\farm_entity\Plugin\Log\LogType\FarmLogType;

/**
 * Provides the water level log type.
 *
 * @LogType(
 *   id = "water_level",
 *   label = @Translation("Water level"),
 * )
 */
class WaterLevel extends FarmLogType {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();
    return $fields;
  }

}
