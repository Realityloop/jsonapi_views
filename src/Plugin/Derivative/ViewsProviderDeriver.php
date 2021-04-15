<?php

namespace Drupal\jsonapi_views\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\views\Views;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides LinkProvider plugin definitions for custom menus.
 */
class ViewsProviderDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $view_keys = array_keys(Views::getViewsAsOptions());
    foreach ($view_keys as $key) {
      $key_vals = explode(':', $key);
      $view_id = $key_vals[0];
      $display_id = $key_vals[1];
      $this->derivatives[$display_id] = array_merge($base_plugin_definition, [
        'link_key' => "jsonapi_views--{$view_id}-{$display_id}"
      ]);
    }
    return $this->derivatives;
  }

}
