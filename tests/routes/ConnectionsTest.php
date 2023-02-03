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
    $badHandle = "w/";
    $response = $this->client->post(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'body' => $badHandle,
        "http_errors" => false
      ]
    );
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertEquals(
      "invalid handle in request body. Expected handle matching " .
      "'w/[a-zA-Z0-9-_]+'. Found '$badHandle' instead",
      $this->responseToString($response),
    );
    $secondBadHandle = "w/!testHandle2";
    $secondResponse = $this->client->post(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'body' => $secondBadHandle,
        "http_errors" => false
      ]
    );
    $this->assertEquals(400, $secondResponse->getStatusCode());
    $this->assertEquals(
      "invalid handle in request body. Expected handle matching " .
      "'w/[a-zA-Z0-9-_]+'. Found '$secondBadHandle' instead",
      $this->responseToString($secondResponse),
    );
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
    $this->assertEquals(
      "user '$unregisteredHandle' not found",
      $this->responseToString($response),
    );
  }

  public function testDeleteConnectionsReturnsBadRequestOnBadHandle(): void
  {
    $badHandle = "w/";
    $response = $this->client->delete(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => ['toHandle' => $badHandle],
        "http_errors" => false
      ]
    );
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertEquals(
      "expected 'toHandle' parameter matching 'w/[a-zA-Z0-9-_]+'. ".
      "Found '$badHandle' instead",
      $this->responseToString($response),
    );
    $secondBadHandle = "w/!testHandle2";
    $responseSecondBadHandle = $this->client->delete(
      "connections",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => ['toHandle' => $secondBadHandle],
        "http_errors" => false
      ]
    );
    $this->assertEquals(400, $responseSecondBadHandle->getStatusCode());
    $this->assertEquals(
      "expected 'toHandle' parameter matching 'w/[a-zA-Z0-9-_]+'. ".
      "Found '$secondBadHandle' instead",
      $this->responseToString($responseSecondBadHandle),
    );
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
    $toHandle = function ($profile) {
      return $profile['handle'];
    };
    $handlesForConnectedUsers = array_map($toHandle, $profiles);
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
    $toHandle = function ($request) {
      return $request['handle'];
    };
    $requestsToConnect = $this->repo->get_connection_requests($this->me->id);
    $currentRequestedHandles = array_map($toHandle, $requestsToConnect);
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