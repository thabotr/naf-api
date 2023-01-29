<?php
namespace resource;

require_once(realpath(dirname(__FILE__) . '/../../vendor/autoload.php'));
require_once(realpath(dirname(__FILE__) . '/../../src/repository.php'));

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
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

  function logRequest()
  {
      return function (callable $handler) {
          return function (
              RequestInterface $request,
              array $options
          ) use ($handler) {
            var_dump($request);
              $promise = $handler($request, $options);
              return $promise->then(
                  function (ResponseInterface $response) {
                      return $response->withHeader("hello", "hi");
                  }
              );
          };
      };
  }
  public function setUp(): void
  {
    $this->repo = new DBRepository(
      "tartarus.aserv.co.za:3306",
      "thabolao_naf_admin",
      "naf_admin_pw",
      "thabolao_naf_db"
    );
    $stack = new HandlerStack();
    $stack->setHandler(new CurlHandler());
    $stack->push($this->logRequest());

    $this->client = new Client([
      // 'base_uri' => "http://www.thaborlabs.com/naf/api/",
      'base_uri' => "http://localhost:8000/naf/api/",
      'allow_redirects' => true,
      'timeout' => 50.0,
      'stack' => $stack
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