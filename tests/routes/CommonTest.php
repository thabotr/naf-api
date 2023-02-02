<?php
namespace resource;
use common\Config;

require_once(realpath(dirname(__FILE__) . '/../../vendor/autoload.php'));
require_once(realpath(dirname(__FILE__) . '/../../src/repository.php'));
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use repository\database\DBRepository;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CommonTest extends TestCase
{

  public function testGetPingReturnsPong(): void
  {
    $response = $this->client->get("ping");
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertTrue($response->getBody() == "pong");
  }

  public function testGetReturnsUnauthorizedOnBadCredentials(): void
  {
    $unregisteredHandle = 'w/someUnregisteredHandle';
    $token = 'someUnregisteredTestToken';
    $response = $this->client->get(
      "",
      [
        'auth' => [$unregisteredHandle, $token, 'basic'],
        'http_errors' => false
      ]
    );
    $this->assertEquals(401, $response->getStatusCode());
  }

  public $client;
  public $repo;

  public $me;
  public $others;

  public function setUp(): void
  {
    $this->repo = new DBRepository();
    $config = new Config();

    $this->client = new Client([
      'base_uri' => $config->webServerURL. "/naf/api/",
      'allow_redirects' => true,
      'timeout' => 2.0,
    ]);

    $this->me = $this->setUser(new Profile(-1, 'w/testHandle', 'testToken'));
    $this->others = [
      new Profile(-1, 'w/testHandle2', 'testToken2'),
      new Profile(-1, 'w/testHandle3', 'testToken3'),
      new Profile(-1, 'w/testHandle4', 'testToken4'),
      new Profile(-1, 'w/testHandle5', 'testToken5')
    ];

    $this->others = array_map(
      function ($user) {
        return $this->setUser($user);
      }, 
      $this->others
    );
  }

  public function tearDown(): void
  {
    $this->clearUser($this->me);
    foreach($this->others as $user) {
      $this->clearUser($user);
    }
  }

  protected function setUser(Profile $user): Profile
  {
    $this->repo->add_user(array(
      "handle" => $user->handle,
      "token" => $user->token
    ));
    [$id, $_] = $this->repo->get_user_id_and_profile(
      $user->handle,
      $user->token
    );
    $user->id = $id;
    return $user;
  }

  protected function clearUser(Profile $user): void
  {
    $this->repo->delete_user_account($user->id);
  }

  protected function clearUserConnections(): void
  {
    foreach($this->others as $user) {
      $this->repo->abandon_user($this->me->id, $user->handle);
    }
  }

  protected function setUserConnections(): void
  {
    foreach($this->others as $user) {
      $this->repo->add_connection_request($this->me->id, $user->handle);
      $this->repo->add_connection_request($user->id, $this->me->handle);
    }
  }
}

class Profile
{
  public int $id;
  public string $token;
  public string $handle;

  public function __construct(int $id, string $handle, string $token)
  {
    $this->id = $id;
    $this->handle = $handle;
    $this->token = $token;
  }
}