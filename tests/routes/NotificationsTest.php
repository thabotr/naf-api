<?php
namespace resource;

require_once(realpath(dirname(__FILE__) . '/../../vendor/autoload.php'));
require_once(realpath(dirname(__FILE__) . '/CommonTest.php'));

class NotificationsTest extends CommonTest
{
  private function GETNotifications(array $params) {
    return $this->client->get(
      "notifications",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => $params,
        "http_errors" => false,
      ]
    );
  }
  public function testGetNotificationsReturnsBadRequestOnBadMessagesAfter(): void
  {
    $badTimestamp = '2011-03-37 17:10:12';
    $response = $this->GETNotifications(['messagesAfter' => $badTimestamp]);
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertEquals(
      "parameter 'messagesAfter' should be of format '%Y-%m-%d %H:%i:%s:v'",
      $this->responseToString($response),
    );
  }
  public function testGetNotificationsReturnsBadRequestOnBadConnectionsAfter(): void
  {
    $badTimestamp = '2011-033-01';
    $response = $this->GETNotifications(['connectionsAfter' => $badTimestamp]);
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertEquals(
      "parameter 'connectionsAfter' should be of format '%Y-%m-%d %H:%i:%s:v'",
      $this->responseToString($response),
    );
  }
  public function testGetNotificationsReturnsCorrectResultOnOnlyMessagesAfter(): void
  {
    $response = $this->GETNotifications(['messagesAfter' => $this->timeB4MsgSend]);
    $this->assertEquals(200, $response->getStatusCode());
    $responseStr = $this->responseToString($response);
    $newMessagesOnlyNotification = "10";
    $this->assertEquals($newMessagesOnlyNotification, $responseStr);

    $now = $this->repo->datetime_now();
    $secondResponse = $this->GETNotifications(['messagesAfter' => $now]);
    $this->assertEquals(200, $secondResponse->getStatusCode());
    $secondResponseStr = $this->responseToString($secondResponse);
    $noNotifications = "00";
    $this->assertEquals($noNotifications, $secondResponseStr);
  }
  public function testGetNotificationsReturnsCorrectResultOnOnlyConnectionsAfter(): void
  {
    $response = $this->GETNotifications(
      ['connectionsAfter' => $this->timeB4UserConnection]
    );
    $this->assertEquals(200, $response->getStatusCode());
    $responseStr = $this->responseToString($response);
    $newConnectionsOnly = "01"; 
    $this->assertEquals($newConnectionsOnly, $responseStr);
    
    $now = $this->repo->datetime_now();
    $secondResponse = $this->GETNotifications(['connectionsAfter' => $now]);
    $this->assertEquals(200, $secondResponse->getStatusCode());
    $secondResponseStr = $this->responseToString($secondResponse);
    $noNotifications = "00";
    $this->assertEquals($noNotifications, $secondResponseStr);
  }
  public function testGetNotificationsReturnsCorrectResultOnMessagesNConnectionsAfter(): void
  {
    $response = $this->GETNotifications(
      [
        'connectionsAfter' => $this->timeB4UserConnection,
        'messagesAfter' => $this->timeB4MsgSend
      ]
    );
    $this->assertEquals(200, $response->getStatusCode());
    $responseStr = $this->responseToString($response);
    $newMessagesNConnections = "11";
    $this->assertEquals($newMessagesNConnections, $responseStr);
    
    $now = $this->repo->datetime_now();
    $secondResponse = $this->GETNotifications(
      ['connectionsAfter' => $now, 'messagesAfter' => $now]
    );
    $this->assertEquals(200, $secondResponse->getStatusCode());
    $secondResponseStr = $this->responseToString($secondResponse);
    $noNotifications = "00";
    $this->assertEquals($noNotifications, $secondResponseStr);
  }
  public $timeB4MsgSend;
  public $timeB4UserConnection;
  public function setUp(): void
  {
    parent::setUp();
    $this->timeB4UserConnection = $this->repo->datetime_now();
    $this->setUserConnections();
    $this->timeB4MsgSend = $this->repo->datetime_now();
    $this->setMessages();
  }
  private function setMessages(): void
  {
    $message = array(
      "text" => "TestNofitications->setMessages",
      "toHandle" => $this->others[0]->handle,
    );
    $this->repo->add_user_message($this->me->id, $message);
  }
}