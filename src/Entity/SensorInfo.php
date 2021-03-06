<?php
/**
 * @file
 * Contains \Drupal\monitoring\Entity\SensorInfo.
 */

namespace Drupal\monitoring\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Represents a sensor info entity class.
 *
 * @todo more
 * @ConfigEntityType(
 *   id = "monitoring_sensor",
 *   label = @Translation("Monitoring Sensor"),
 *   controllers = {
 *     "view_builder" = "Drupal\monitoring\SensorViewBuilder",
 *     "list_builder" = "Drupal\monitoring\SensorListBuilder",
 *     "form" = {
 *       "add" = "Drupal\monitoring\SensorForm",
 *       "delete" = "Drupal\monitoring\SensorDeleteForm",
 *       "edit" = "Drupal\monitoring\SensorForm"
 *     }
 *   },
 *   admin_permission = "administer sensors",
 *   config_prefix = "sensor",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "delete-form" = "monitoring.sensor_delete",
 *     "edit-form" = "monitoring.sensor_edit"
 *   }
 * )
 */
class SensorInfo extends ConfigEntityBase {

  /**
   * The config id.
   *
   * @var string
   */
  public $id;

  /**
   * The sensor label.
   *
   * @var string
   */
  public $label;

  /**
   * The sensor description.
   *
   * @var string
   */
  public $description;

  /**
   * The sensor category.
   *
   * @var string
   */
  public $category;

  /**
   * The sensor id.
   *
   * @var string
   */
  public $sensor_id;

  /**
   * The sensor result class.
   *
   * @var string
   */
  public $result_class;

  /**
   * The sensor settings.
   *
   * @var array
   */
  public $settings = array();

  /**
   * The sensor value label.
   *
   * @var string
   */
  public $value_label;

  /**
   * The sensor value type.
   *
   * @var string
   */
  public $value_type;

  /**
   * The sensor value numeric flag.
   *
   * @var bool
   */
  public $numeric;

  /**
   * The sensor caching time.
   *
   * @var integer
   */
  public $caching_time;

  /**
   * The sensor enabled/disabled flag.
   *
   * @var bool
   */
  public $status;

  /**
   * The sensor id.
   *
   * @var string
   */

  public function getName() {
    return $this->id;
  }

  /**
   * Gets sensor label.
   *
   * The sensor label might not be self-explaining enough or unique without
   * the category, the category should always be present when the label is
   * displayed.
   *
   * @return string
   *   Sensor label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Gets sensor description.
   *
   * @return string
   *   Sensor description.
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Gets sensor class.
   *
   * @return string
   *   Sensor class
   */
  public function getSensorClass() {
    $definition = monitoring_sensor_manager()->getDefinition($this->sensor_id);
    return $definition['class'];
  }

  /**
   * Returns the sensor plugin.
   *
   * @return \Drupal\monitoring\Sensor\SensorInterface
   *   Instantiated sensor.
   */
  public function getPlugin() {
    $configuration = array('sensor_info' => $this);
    $sensor = monitoring_sensor_manager()->createInstance($this->sensor_id, $configuration);
    return $sensor;
  }
  
  /**
   * Gets sensor result class.
   *
   * @return string
   *   Result class.
   */
  public function getResultClass() {
    return '\Drupal\monitoring\Result\SensorResult';
  }

  /**
   * Gets sensor categories.
   *
   * @return string
   *   Categories.
   */
  public function getCategory() {
    return $this->category;
  }

  /**
   * Gets sensor value label.
   *
   * In case the sensor defines value_type, it will use the label provided for
   * that type by monitoring_value_types().
   *
   * Next it searches for the label within the sensor definition value_label.
   *
   * If nothing is defined, it returns NULL.
   *
   * @return string|null
   *   Sensor value label.
   */
  public function getValueLabel() {
    if ($this->value_label) {
      return $this->value_label;
    }
    if ($this->value_type) {
      $value_types = monitoring_value_types();
      return $value_types[$this->value_type]['label'];
    }
  }

  /**
   * Gets sensor value type.
   *
   * @return string|null
   *   Sensor value type.
   *
   * @see monitoring_value_types().
   */
  public function getValueType() {
    return $this->value_type;
  }

  /**
   * Determines if the sensor value is numeric.
   *
   * @return bool
   *   TRUE if the sensor value is numeric.
   */
  public function isNumeric() {
    return ($this->numeric != NULL) ? $this->numeric : TRUE;
  }

  /**
   * Determines if the sensor value type is boolean.
   *
   * @return bool
   *   TRUE if the sensor value type is boolean.
   */
  public function isBool() {
    return  $this->getValueType() == 'bool';
  }

  /**
   * Gets sensor caching time.
   *
   * @return int
   *   Caching time in seconds.
   */
  public function getCachingTime() {
    return $this->caching_time;
  }

  /**
   * Gets threshold type.
   *
   * @return string|null
   *   Threshold type.
   */
  public function getThresholdsType() {
    $thresholds = $this->settings['thresholds'];
    if (!empty($thresholds['type'])) {
      return $thresholds['type'];
    }

    // We assume the default threshold type.
    return 'exceeds';
  }

  /**
   * Returns a given threshold if one is configured.
   *
   * @param $key
   *   Name of the threshold, for example warning or critical.
   *
   * @return int|null
   *   The threshold value or NULL if not-configured.
   */
  public function getThresholdValue($key) {
    $thresholds = $this->settings['thresholds'];
    if (isset($thresholds[$key]) && $thresholds[$key] !== '') {
      return $thresholds[$key];
    }
  }

  /**
   * Returns all settings.
   *
   * @return array
   *   Settings as an array.
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * Gets time interval value.
   *
   * @return int
   *   Number of seconds of the time interval.
   *   NULL in case the sensor does not define the time interval.
   */
  public function getTimeIntervalValue() {
    return $this->getSetting('time_interval_value', NULL);
  }

  /**
   * Gets setting.
   *
   * @param string $key
   *   Setting key.
   * @param mixed $default
   *   Default value if the setting does not exist.
   *
   * @return mixed
   *   Setting value.
   */
  public function getSetting($key, $default = NULL) {
    return isset($this->settings[$key]) ? $this->settings[$key] : $default;
  }

  /**
   * Checks if sensor is enabled.
   *
   * @return bool
   */
  public function isEnabled() {
    return (boolean) $this->status;
  }

  /**
   * Checks if sensor is configurable.
   *
   * @return bool
   */
  public function isConfigurable() {
    return in_array('Drupal\monitoring\Sensor\SensorConfigurableInterface', class_implements($this->getSensorClass()));
  }

  /**
   * Checks if sensor provides extended info.
   *
   * @return bool
   */
  public function isExtendedInfo() {
    return in_array('Drupal\monitoring\Sensor\SensorExtendedInfoInterface', class_implements($this->getSensorClass()));
  }

  /**
   * Checks if sensor defines thresholds.
   *
   * @return bool
   */
  public function isDefiningThresholds() {
    return in_array('Drupal\monitoring\Sensor\SensorThresholdsInterface', class_implements($this->getSensorClass()));
  }

  /**
   * Compiles sensor values to an associative array.
   *
   * @return array
   *   Sensor info associative array.
   */
  public function getDefinition() {
    $info_array = array(
      'label' => $this->getLabel(),
      'category' => $this->getCategory(),
      'description' => $this->getDescription(),
      'numeric' => $this->isNumeric(),
      'value_label' => $this->getValueLabel(),
      'caching_time' => $this->getCachingTime(),
      'time_interval' => $this->getTimeIntervalValue(),
      'enabled' => $this->isEnabled(),
    );

    if ($this->isDefiningThresholds()) {
      $info_array['thresholds'] = $this->getSetting('thresholds');
    }

    return $info_array;
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    // Checks whether both labels and categories are equal.
    if ($a->getLabel() == $b->getLabel() && $a->getCategory() == $b->getCategory()) {
      return 0;
    }
    // If the categories are not equal, their order is determined.
    elseif ($a->getCategory() != $b->getCategory()) {
      return ($a->getCategory() < $b->getCategory()) ? -1 : 1;
    }
    // In the end, the label's order is determined.
    return ($a->getLabel() < $b->getLabel()) ? -1 : 1;
  }
}
