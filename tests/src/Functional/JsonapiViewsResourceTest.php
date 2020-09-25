<?php

namespace Drupal\Tests\jsonapi_views\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\jsonapi\Functional\ResourceResponseTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use GuzzleHttp\RequestOptions;

/**
 * Tests JSON:API Views routes.
 *
 * @group jsonapi_views
 */
class JsonapiViewsResourceTest extends BrowserTestBase {

  use JsonApiRequestTestTrait;
  use ResourceResponseTestTrait;
  use EntityReferenceTestTrait;
  use CommentTestTrait;

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
  protected static $modules = [
    'basic_auth',
    'node',
    'path',
    'views',
    'jsonapi_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

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

    // Create test bundles and and entity references and rebuild routes.
    NodeType::create([
      'name' => 'location',
      'type' => 'location',
    ])->save();
    NodeType::create([
      'name' => 'room',
      'type' => 'room',
    ])->save();

    $this->createEntityReferenceField(
      'node',
      'room',
      'field_location',
      'Location',
      'node',
      'default',
      [
        'target_bundles' => [
          'location' => 'location',
        ],
      ],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    $this->container->get('router.builder')->rebuildIfNeeded();

    // Create an account, which tests will use. Also ensure the @current_user
    // service this account, to ensure certain access check logic in tests works
    // as expected.
    $this->account = $this->createUser();
    $this->container->get('current_user')->setAccount($this->account);
  }

  /**
   * Tests the Current User Info resource.
   */
  public function testContentPageViewsResource() {
    $role_id = $this->drupalCreateRole([
      'access content overview',
    ]);
    $this->account->addRole($role_id);
    $this->account->setEmail('test@example.com');
    $this->account->save();

    $url = Url::fromUri('internal:/jsonapi/views/content/page_1');
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());
    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
    $response_document = Json::decode((string) $response->getBody());
    $this->assertIsArray($response_document['data']);

    $this->assertArrayNotHasKey('errors', $response_document);
  }

  /**
   * Tests the Current User Info resource.
   */
  public function testContentPageExposedFilters() {
    $role_id = $this->drupalCreateRole([
      'access content overview',
      'administer nodes',
      'access content',
    ]);
    $this->account->addRole($role_id);
    $this->account->setEmail('test@example.com');
    $this->account->save();

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $nodes = [
      'published' => [],
      'unpublished' => [],
      'promoted' => [],
      'unpromoted' => [],
    ];

    for ($i = 0; $i < 9; $i++) {
      $promoted = ($i % 2 === 0);
      $published = ($i % 3 === 0);
      $node = Node::create([
        'type' => 'room',
        'title' => $this->randomString(),
        'status' => $published ? 1 : 0,
        'promote' => $promoted ? 1 : 0,
      ]);
      $node->save();

      $nodes['all'][$node->uuid()] = $node;
      $nodes[$published ? 'published' : 'unpublished'][$node->uuid()] = $node;
      $nodes[$promoted ? 'promoted' : 'unpromoted'][$node->uuid()] = $node;
    }

    $url = Url::fromUri('internal:/jsonapi/views/content/page_1');

    // Get all nodes.
    $url->setOption('query', [
      'views-filter[status]' => '0',
    ]);
    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
    $response_document = Json::decode((string) $response->getBody());
    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(9, $response_document['data']);
    $this->assertSame(array_reverse(array_keys($nodes['all'])), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));

    // Get published nodes.
    $url->setOption('query', [
      'views-filter[status]' => '1',
    ]);

    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
    $response_document = Json::decode((string) $response->getBody());

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(3, $response_document['data']);
    $this->assertSame(array_reverse(array_keys($nodes['published'])), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));

    // Get unpublished nodes.
    $url->setOption('query', [
      'views-filter[status]' => '2',
    ]);
    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
    $response_document = Json::decode((string) $response->getBody());
    print_r($url->getOptions());
    print_r($response_document);
    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(7, $response_document['data']);
    $this->assertSame(array_reverse(array_keys($nodes['unpublished'])), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
  }

  /**
   * Grants permissions to the authenticated role.
   *
   * @param string[] $permissions
   *   Permissions to grant.
   */
  protected function grantPermissionsToTestedRole(array $permissions) {
    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), $permissions);
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

}
