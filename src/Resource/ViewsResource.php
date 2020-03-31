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
final class ViewsResource extends EntityResourceBase {

  /**
   * Extracts exposed filter values from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   Key value pairs of exposed filters.
   */
  protected function getExposedFilterParams(Request $request) {
    $all_params = $request->query->all();
    $exposed_filter_params = isset($all_params['views-filter'])
      ? $all_params['views-filter']
      : [];
    return $exposed_filter_params;
  }

  /**
   * Executes a view display with url parameters.
   *
   * @param \Drupal\views\ViewExecutable\ViewExecutable $view
   *   An executable view instance.
   * @param string $display
   *   A display machine name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\views\ViewExecutable\ViewExecutable
   *   The executed view with query parameters applied as exposed filters.
   */
  protected function executeView(ViewExecutable &$view, string $display, Request $request) {
    // Get params from request.
    $exposed_filter_params = $this->getExposedFilterParams($request);
    $view->setExposedInput($exposed_filter_params);

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
  public function process(Request $request): ResourceResponse {
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

    $entities = array_map(function (ResultRow $row) {
      return $row->_entity;
    }, $view->result);
    $data = $this->createCollectionDataFromEntities($entities);

    // @TODO: Build pagination links from the views pager object.
    // $pagination_links = ????
    $response = $this->createJsonapiResponse($data, $request, 200, [] /* , $pagination_links */);
    if (isset($bubbleable_metadata)) {
      $response->addCacheableDependency($bubbleable_metadata);
    }
    return $response;
  }

}
