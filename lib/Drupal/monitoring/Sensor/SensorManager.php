<?php
/**
 * @file
 * Contains \Drupal\monitoring\Sensor\SensorManager.
 */

namespace Drupal\monitoring\Sensor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\String;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\monitoring\SensorRunner;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\monitoring\Entity\SensorInfo;

/**
 * Manages sensor definitions and settings.
 *
 * Provides list of enabled sensors.
 * Sensors can be listed by category.
 *
 * Maintains a (non persistent) info cache.
 * Enables and disables sensors.
 *
 */
class SensorManager extends DefaultPluginManager {

  /**
   * List of sensor definitions.
   *
   * @var \Drupal\monitoring\Entity\SensorInfo[]
   */
  protected $info;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructes a sensor manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, LanguageManager $language_manager, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config) {
    parent::__construct('Plugin/monitoring/Sensor', $namespaces, $module_handler, 'Drupal\monitoring\Annotation\Sensor');
    $this->alterInfo('block');
    $this->moduleHandler = $module_handler;
    $this->setCacheBackend($cache_backend, $language_manager, 'monitoring_sensor_plugins');
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = array()) {
    // Configuration contains sensor_info object. Extracting
    // it to use for sensor object creation.
    $sensor_info = $configuration['sensor_info'];
    $definition = $this->getDefinition($plugin_id);
    // Sensor class from the sensor definition.
    $class = $definition['class'];
    // Creating instance of the sensor. Refer Sensor.php for arguments.
    return new $class($sensor_info, $plugin_id, $definition);
  }

  /**
   * Returns monitoring sensor info.
   *
   * @return \Drupal\monitoring\Entity\SensorInfo[]
   *   List of SensorInfo instances.
   */
  public function getSensorInfo() {
    $sensors = SensorInfo::loadMultiple();
    $this->moduleHandler->alter('monitoring_sensor_info', $sensors);

    // Sort the sensors by category and label.
    uasort($sensors, "\Drupal\monitoring\Sensor\SensorManager::orderSensorInfo");

    return $sensors;
  }

  /**
   * Returns monitoring sensor info for enabled sensors.
   *
   * @return \Drupal\monitoring\Entity\SensorInfo[]
   *   List of SensorInfo instances.
   */
  public function getEnabledSensorInfo() {
    $enabled_sensors = array();
    foreach ($this->getSensorInfo() as $sensor_info) {
      if ($sensor_info->isEnabled()) {
        $enabled_sensors[$sensor_info->getName()] = $sensor_info;
      }
    }
    return $enabled_sensors;
  }

  /**
   * Returns monitoring sensor info for a given sensor.
   *
   * @param string $sensor_name
   *   Sensor id.
   *
   * @return \Drupal\monitoring\Entity\SensorInfo
   *   A single SensorInfo instance.
   *
   * @throws \Drupal\monitoring\Sensor\NonExistingSensorException
   *   Thrown if the requested sensor does not exist.
   */
  public function getSensorInfoByName($sensor_name) {
    $info = $this->getSensorInfo();
    if (isset($info[$sensor_name])) {
      return $info[$sensor_name];
    }
    throw new NonExistingSensorException(String::format('Sensor @sensor_name does not exist', array('@sensor_name' => $sensor_name)));
  }

  /**
   * Gets sensor info grouped by categories.
   *
   * @todo: The enabled flag is strange, FALSE should return all?
   *
   * @param bool $enabled
   *   Sensor isEnabled flag.
   *
   * @return \Drupal\monitoring\Entity\SensorInfo[]
   *   Sensor info.
   */
  public function getSensorInfoByCategories($enabled = TRUE) {
    $info_by_categories = array();
    foreach ($this->getSensorInfo() as $sensor_name => $sensor_info) {
      if ($sensor_info->isEnabled() != $enabled) {
        continue;
      }

      $info_by_categories[$sensor_info->getCategory()][$sensor_name] = $sensor_info;
    }

    return $info_by_categories;
  }

  /**
   * Reset the static cache
   */
  public function resetCache() {
    $this->info = array();
  }

  /**
   * Saves sensor settings.
   *
   * @param string $sensor_name
   *   The sensor name.
   * @param $settings
   *   Settings that should be saved.
   */
  public function saveSettings($sensor_name, array $settings) {
    $sensor = SensorInfo::load($sensor_name);
    $sensor->settings = $settings;
    $sensor->save();
  }

  /**
   * Enable a sensor.
   *
   * Checks if the sensor is enabled and enables it if not.
   *
   * @param string $sensor_name
   *   Sensor name to be enabled.
   *
   * @throws \Drupal\monitoring\Sensor\NonExistingSensorException
   *   Thrown if the requested sensor does not exist.
   */
  public function enableSensor($sensor_name) {
    $sensor_info = $this->getSensorInfoByName($sensor_name);
    if (!$sensor_info->isEnabled()) {
      $this->saveSettings($sensor_name, array('enabled' => TRUE));
      $available_sensors = \Drupal::state()->get('monitoring.available_sensors', array());

      if (!isset($available_sensors[$sensor_name])) {
        // Use the watchdog message as the disappeared sensor does when new
        // sensors are detected.
        watchdog('monitoring', '@count new sensor/s added: @names',
          array('@count' => 1, '@names' => $sensor_name));
      }

      $available_sensors[$sensor_name]['enabled'] = TRUE;
      $available_sensors[$sensor_name]['name'] = $sensor_name;
      \Drupal::state()->set('monitoring.available_sensors', $available_sensors);
    }
  }

  /**
   * Disable a sensor.
   *
   * Checks if the sensor is enabled and if so it will disable it and remove
   * from the active sensor list.
   *
   * @param string $sensor_name
   *   Sensor name to be disabled.
   *
   * @throws \Drupal\monitoring\Sensor\NonExistingSensorException
   *   Thrown if the requested sensor does not exist.
   */
  public function disableSensor($sensor_name) {
    $sensor_info = $this->getSensorInfoByName($sensor_name);
    if ($sensor_info->isEnabled()) {
      $this->saveSettings($sensor_name, array('enabled' => FALSE));
      $available_sensors = \Drupal::state()->get('monitoring.available_sensors', array());
      $available_sensors[$sensor_name]['enabled'] = FALSE;
      $available_sensors[$sensor_name]['name'] = $sensor_name;
      \Drupal::state()->set('monitoring.available_sensors', $available_sensors);
    }
  }

  /**
   * Callback for uasort() to order sensors by category and label.
   *
   * @param \Drupal\monitoring\Entity\SensorInfo $a
   *   1st Object to compare with.
   *
   * @param \Drupal\monitoring\Entity\SensorInfo $b
   *   2nd Object to compare with.
   *
   * @return int
   *   Sort order of the passed in SensorInfo objects.
   */
  public static function orderSensorInfo(SensorInfo $a, SensorInfo $b) {
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

  /**
   * Returns if an array is flat.
   *
   * @param $array
   *   The array to check.
   *
   * @return bool
   *   TRUE if the array has no values that are arrays again.
   */
  protected function isFlatArray($array) {
    foreach ($array as $value) {
      if (is_array($value)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
