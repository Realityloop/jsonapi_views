<?php

namespace Drupal\Tests\jsonapi_views\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\views\Tests\ViewTestData;
use GuzzleHttp\RequestOptions;

/**
 * Tests JSON:API Views routes.
 *
 * @group jsonapi_views
 */
class JsonapiViewsResourceTest extends ViewTestBase {

  use JsonApiRequestTestTrait;

  /**
   * The account to use for authentication.
   *
   * @var null|\Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['jsonapi_views_test'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['jsonapi_views_test_node_view'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    ViewTestData::createTestViews(get_class($this), ['jsonapi_views_test']);
    $this->enableViewsTestModule();

    // Ensure the anonymous user role has no permissions at all.
    $user_role = Role::load(RoleInterface::ANONYMOUS_ID);
    foreach ($user_role->getPermissions() as $permission) {
      $user_role->revokePermission($permission);
    }
    $user_role->save();
    assert([] === $user_role->getPermissions(), 'The anonymous user role has no permissions at all.');

    // Ensure the authenticated user role has no permissions at all.
    $user_role = Role::load(RoleInterface::AUTHENTICATED_ID);
    foreach ($user_role->getPermissions() as $permission) {
      $user_role->revokePermission($permission);
    }
    $user_role->save();
    assert([] === $user_role->getPermissions(), 'The authenticated user role has no permissions at all.');

    $this->container->get('router.builder')->rebuildIfNeeded();

    // Create an account, which tests will use. Also ensure the @current_user
    // service this account, to ensure certain access check logic in tests works
    // as expected.
    $this->account = $this->createUser();
    $this->container->get('current_user')->setAccount($this->account);
  }

  /**
   * Tests that the test view has been enabled.
   */
  public function testNodeViewExists() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $this->drupalGet('jsonapi-views-test-node-view');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty reaction rule listing.
    $this->assertSession()->pageTextContains('JSON:API Views Test Node View');
  }

  /**
   * Tests the JSON:API Views resource displays.
   */
  public function testJsonApiViewsResourceDisplays() {
    $location = $this->drupalCreateNode(['type' => 'location']);
    $room = $this->drupalCreateNode(['type' => 'room']);

    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // Page display.
    $response_document = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'page_1')
    );

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(2, $response_document['data']);

    // Block display.
    $response_document = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'block_1')
    );

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(1, $response_document['data']);
    $this->assertSame($room->uuid(), $response_document['data'][0]['id']);

    // Attachment display.
    $response_document = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'attachment_1')
    );

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(1, $response_document['data']);
    $this->assertSame($location->uuid(), $response_document['data'][0]['id']);
  }

  /**
   * Tests the JSON:API Views resource Exposed Filters feature.
   */
  public function testJsonApiViewsResourceExposedFilters() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $nodes = [
      'published' => [],
      'unpublished' => [],
      'promoted' => [],
      'unpromoted' => [],
    ];

    for ($i = 0; $i < 9; $i++) {
      $promoted = ($i % 2 === 0);
      $published = ($i % 3 === 0);
      $node = $this->drupalCreateNode([
        'type' => 'room',
        'status' => $published ? 1 : 0,
        'promote' => $promoted ? 1 : 0,
      ]);
      $node->save();

      $nodes['all'][$node->uuid()] = $node;
      $nodes[$published ? 'published' : 'unpublished'][$node->uuid()] = $node;
      $nodes[$promoted ? 'promoted' : 'unpromoted'][$node->uuid()] = $node;
    }

    // Get all nodes.
    $query = ['views-filter[status]' => '0'];
    $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'page_1', $query)
    );

    // @TODO - Fix tests and/or Exposed filters.
    // phpcs:disable
    // $response_document = $this->getJsonApiViewResponse('jsonapi_views_test_node_view', 'page_1', $query);
    // $this->assertCount(9, $response_document['data']);
    // $this->assertSame(array_reverse(array_keys($nodes['all'])), array_map(static function (array $data) {
    //   return $data['id'];
    // }, $response_document['data']));
    // phpcs:enable

    // Get published nodes.
    $query = ['views-filter[status]' => '1'];
    $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'page_1', $query)
    );

    // @TODO - Fix tests and/or Exposed filters.
    // phpcs:disable
    // $response_document = $this->getJsonApiViewResponse('jsonapi_views_test_node_view', 'page_1', $query);
    // $this->assertCount(3, $response_document['data']);
    // $this->assertSame(array_reverse(array_keys($nodes['published'])), array_map(static function (array $data) {
    //   return $data['id'];
    // }, $response_document['data']));
    // phpcs:enable

    // Get unpublished nodes.
    $query = ['views-filter[status]' => '2'];
    $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'page_1', $query)
    );

    // @TODO - Fix tests and/or Exposed filters.
    // phpcs:disable
    // $response_document = $this->getJsonApiViewResponse('jsonapi_views_test_node_view', 'page_1', $query);
    // $this->assertCount(7, $response_document['data']);
    // $this->assertSame(array_reverse(array_keys($nodes['unpublished'])), array_map(static function (array $data) {
    //   return $data['id'];
    // }, $response_document['data']));
    // phpcs:enable
  }

  /**
   * Tests the JSON:API Views resource Pager feature.
   */
  public function testJsonApiViewsResourcePager() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $nodes = [];

    for ($i = 0; $i < 12; $i++) {
      $node = $this->drupalCreateNode([
        'type' => 'room',
        'status' => 1,
      ]);
      $node->save();

      $nodes['all'][$node->uuid()] = $node;
    }
    $nodes['paged'] = array_chunk($nodes['all'], 5, TRUE);

    // Test that views showing a specified number of items do not include
    // pager links. The block view is configured to show 5 items with no pager.
    $query = [];
    $response_document = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'block_1', $query)
    );
    $this->assertCount(5, $response_document['data']);
    $this->assertSame(array_keys($nodes['paged'][0]), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertArrayNotHasKey('prev', $response_document['links']);

    // Test that views showing a paged items include the correct links
    // The embed view is configured to show a 5 item mini pager.
    $query = [];
    $response_document = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'embed_1', $query)
    );
    $this->assertCount(5, $response_document['data']);
    $this->assertSame(array_keys($nodes['paged'][0]), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertArrayNotHasKey('prev', $response_document['links']);
    $this->assertSame(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'embed_1', ['page' => 1])->setAbsolute()->toString(),
      $response_document['links']['next']['href']
    );

    $response_document = $this->getJsonApiViewResponse(
      URL::fromUri($response_document['links']['next']['href'])
    );
    $this->assertCount(5, $response_document['data']);
    $this->assertSame(array_keys($nodes['paged'][1]), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertSame(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'embed_1', ['page' => 0])->setAbsolute()->toString(),
      $response_document['links']['prev']['href']
    );
    $this->assertSame(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'embed_1', ['page' => 2])->setAbsolute()->toString(),
      $response_document['links']['next']['href']
    );

    $response_document = $this->getJsonApiViewResponse(
      URL::fromUri($response_document['links']['next']['href'])
    );
    $this->assertCount(2, $response_document['data']);
    $this->assertSame(array_keys($nodes['paged'][2]), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertSame(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'embed_1', ['page' => 1])->setAbsolute()->toString(),
      $response_document['links']['prev']['href']
    );
    $this->assertArrayNotHasKey('next', $response_document['links']);

    $query = [
      'page' => 10,
    ];
    $response_document = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'embed_1', $query)
    );
    $this->assertCount(0, $response_document['data']);
    $this->assertArrayNotHasKey('next', $response_document['links']);
  }

  /**
   * Returns Guzzle request options for authentication.
   *
   * @return array
   *   Guzzle request options to use for authentication.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getAuthenticationRequestOptions() {
    return [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($this->account->name->value . ':' . $this->account->passRaw),
      ],
    ];
  }

  /**
   * Get a JSON:API Views resource response document.
   *
   * @param \Drupal\core\Url $url
   *   The url for a JSON:API View.
   *
   * @return array
   *   The response document.
   */
  protected function getJsonApiViewResponse(Url $url) {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));

    $response_document = Json::decode((string) $response->getBody());

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);

    return $response_document;
  }

  /**
   * Get a JSON:API Views Url for a given view display.
   *
   * @param string $view_name
   *   The View name.
   * @param string $display_id
   *   The View display id.
   * @param string $query
   *   A query object to add to the request.
   *
   * @return \Drupal\core\Url
   *   The url for a JSON:API View.
   */
  protected function getJsonApiViewUrl($view_name, $display_id, $query = []) {
    $url = Url::fromUri("internal:/jsonapi/views/{$view_name}/{$display_id}");
    $url->setOption('query', $query);

    return $url;
  }

}
