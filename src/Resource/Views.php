<?php

namespace Drupal\jsonapi_views\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\ResultRow;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes a request for a collection of featured nodes.
 *
 * @internal
 */
final class Views extends EntityResourceBase
{
  protected function executeView(ViewExecutable &$view, $display) {
    return $view->preview($display);
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, ViewEntityInterface $view, $display): ResourceResponse
  {
    $view = $view->getExecutable();

    $context = new RenderContext();
    \Drupal::service('renderer')->executeInRenderContext($context, function () use (&$view, $display, $request) {
      return $this->executeView($view, $display, $request);
    });

    // Handle any bubbled cacheability metadata.
    if (!$context->isEmpty()) {
      $bubbleable_metadata = $context->pop();
      BubbleableMetadata::createFromObject($view->result)
        ->merge($bubbleable_metadata);
    }

    $entities = array_map(function(ResultRow $row){ return $row->_entity; }, $view->result);
    $data = $this->createCollectionDataFromEntities($entities);

    // @TODO: Build pagination links from the views pager object.
    // $pagination_links = ????

    $response = $this->createJsonapiResponse($data, $request, 200, [] /* , $pagination_links */);
    return $response;
  }

  /**
   * {@inheritdoc}
   *
   * @TODO: The third defaults parameter is not passed in be the calling enhancer method.
   * This needs to be patched in jsonapi_resources
   */
  public function getRouteResourceTypes(Route $route, string $route_name, array $defaults): array
  {
    $view = $defaults['view']->getExecutable();
    $entityType = $view->getBaseEntityType()->id();
    return $this->getResourceTypesByEntityTypeId($entityType);
  }
}
