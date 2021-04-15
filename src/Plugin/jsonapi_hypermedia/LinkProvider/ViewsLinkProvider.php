<?php

namespace Drupal\jsonapi_menu_items\Plugin\jsonapi_hypermedia\LinkProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi_hypermedia\AccessRestrictedLink;
use Drupal\jsonapi_hypermedia\Plugin\LinkProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a JSON:API Views LinkProvider.
 *
 * @JsonapiHypermediaLinkProvider(
 *   id = "jsonapi_views.top_level.view_items",
 *   deriver = "Drupal\jsonapi_views\Plugin\Derivative\ViewsProviderDeriver",
 *   link_relation_type = "view_items",
 * )
 */
final class ViewsLinkProvider extends LinkProviderBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getLink($context) {
    assert($context instanceof JsonApiDocumentTopLevel);
    return AccessRestrictedLink::createLink(
      AccessResult::allowed(),
      new CacheableMetadata(),
      new Url('jsonapi_views', ['display_id' => $this->pluginDefinition['link_context']['display_id']]),
      $this->getLinkRelationType()
    );
  }

}
