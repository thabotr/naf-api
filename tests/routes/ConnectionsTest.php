<?php
namespace resource;

require_once(realpath(dirname(__FILE__) . '/../../vendor/autoload.php'));
require_once(realpath(dirname(__FILE__) . '/CommonTest.php'));

use Exception;

class ConnectionsTest extends CommonTest
{
  public function testGetPendingConnectionsReturnsConnectionRequests(): void
  {

    $this->setConnectionRequests();
    $response = $this->client->get(
      "connections/pending",
      ['auth' => [$this->me->handle, $this->me->token, 'basic']]
    );

    $this->assertEquals(200, $response->getStatusCode());
    $requests = json_decode($response->getBody());
    $handles = array_map($this->toHandle, $requests);
    $expectedHandles = array_map($this->toHandle, $this->others);
    $this->assertEquals($expectedHandles, $handles);
    $this->clearUserConnectionRequests();
  }

  public function testPostConnectionsCreatesRequestToConnectOnValidHandle(): void
  {
    $this->clearUserConnectionRequests();
    $response = $this->client->post(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'body' => $this->others[0]->handle,
      ]
    );
    $this->assertEquals(200, $response->getStatusCode());
    $connRequests = $this->repo->get_connection_requests($this->me->id);
    $this->assertEquals(1, count($connRequests));
    $expectedHandle = $this->others[0]->handle;
    $handle = $connRequests[0]['handle'];
    $this->assertEquals($expectedHandle, $handle);
    $this->clearUserConnectionRequests();
  }

  public function testPostConnectionsReturnsBadRequestOnBadHandle(): void
  {
    $badHandle1 = "w/";
    $response = $this->client->post(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'body' => $badHandle1,
        "http_errors" => false
      ]
    );
    $this->assertEquals(400, $response->getStatusCode());
    $badHandle2 = "w/!testHandle2";
    $response2 = $this->client->post(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'body' => $badHandle2,
        "http_errors" => false
      ]
    );
    $this->assertEquals(400, $response2->getStatusCode());
  }

  public function testPostRequestReturnsNotFoundOnConnectToUnregisteredUser(): void
  {
    $unregisteredHandle = "w/imNotRegistered";
    $response = $this->client->post(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'body' => $unregisteredHandle,
        "http_errors" => false
      ]
    );
    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testDeleteConnectionsReturnsBadRequestOnBadHandle(): void
  {
    $badHandle1 = "w/";
    $response = $this->client->delete(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => ['toHandle' => $badHandle1],
        "http_errors" => false
      ]
    );
    $this->assertEquals(400, $response->getStatusCode());
    $badHandle2 = "w/!testHandle2";
    $response2 = $this->client->delete(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => ['toHandle' => $badHandle2],
        "http_errors" => false
      ]
    );
    $this->assertEquals(400, $response2->getStatusCode());
  }

  public function testDeleteConnectionsDeletesConnectionsToUser(): void
  {
    $handles = array_map($this->toHandle, $this->others);
    $connectedHandle = $this->others[1]->handle;
    $this->setUserConnections();
    $response = $this->requestDeleteConnectionTo($connectedHandle);
    $this->assertEquals(200, $response->getStatusCode());
    $handlesXceptDeleted2 = array_values(array_filter(
      $handles,
      function ($handle) use ($connectedHandle) {
        return $handle != $connectedHandle;
      },
    ));
    $profiles = $this->repo->get_profiles_for_connected_users($this->me->id);
    $handlesForConnectedUsers = array_map($this->toHandle, $profiles);
    $this->assertEquals($handlesXceptDeleted2, $handlesForConnectedUsers);
    $this->clearUserConnections();
  }

  public function testDeleteConnectionDeletesRequestToConnect(): void
  {
    $this->setConnectionRequests();
    $requestedHandle = $this->others[0]->handle;
    $response = $this->requestDeleteConnectionTo($requestedHandle);
    $this->assertEquals(200, $response->getStatusCode());
    $handles = array_map($this->toHandle, $this->others);
    $handlesXceptDeleted = array_values(array_filter(
      $handles,
      function ($handle) use ($requestedHandle) {
        return $handle != $requestedHandle;
      },
    ));
    $requestsToConnect = $this->repo->get_connection_requests($this->me->id);
    $currentRequestedHandles = array_map($this->toHandle, $requestsToConnect);
    $this->assertEquals($handlesXceptDeleted, $currentRequestedHandles);
    $this->clearUserConnectionRequests();
  }

  private function requestDeleteConnectionTo(string $userHandle){
    return $this->client->delete(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => ['toHandle' => $userHandle],
      ]
    );
  }

  private $toHandle;

  public function setUp(): void
  {
    parent::setUp();
    $this->toHandle = function ($userLikeObj) {
      try {
        return $userLikeObj->handle;
      } catch( Exception $_) {
        return $userLikeObj['handle'];
      }
    };
  }

  protected function clearUserConnectionRequests(): void
  {
    $this->clearUserConnections();
  }
  protected function setConnectionRequests(): void
  {
    foreach ($this->others as $user) {
      $this->repo->add_connection_request($this->me->id, $user->handle);
    }
  }
}