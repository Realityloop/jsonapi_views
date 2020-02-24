<?php

namespace Drupal\jsonapi_views\Resource;

use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\views\ViewExecutable;
use Drupal\views\ResultRow;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes a request for a collection of featured nodes.
 *
 * @internal
 */
final class ViewsResource extends EntityResourceBase
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
  public function process(Request $request): ResourceResponse
  {
    $view = $request->get('view');
    assert($view instanceof ViewExecutable);
    $display = $request->get('display');

    // TODO: Check access properly.
    if (!$view->access([$display])) {
      return $this->createJsonapiResponse($this->createCollectionDataFromEntities([]), $request, 403, []);
    }

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
    $response = $this->createJsonapiResponse($data, $request, 200, [] /* , $pagination_links */)->addCacheableDependency($bubbleable_metadata);
    return $response;
  }
}
