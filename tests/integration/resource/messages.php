<?php
namespace resource;

require_once(realpath(dirname(__FILE__) . '/../../../vendor/autoload.php'));
require_once(realpath(dirname(__FILE__) . '/common.php'));


class ConnectionsEndpointTests extends HTTPResourceTests
{
  public function testGetMessagesReturnsAllMessagesForUser(): void
  {
    $response = $this->client->get(
      '/messages',
      ['auth' => [$this->me->handle, $this->me->token, 'basic']]
    );
    $this->assertEquals(200, $response->getStatusCode());
    $expectedTextForMessages = array_map($this->getText, $this->sentMessages);
    array_push(
      $expectedTextForMessages,
      ...array_map($this->getText, $this->receivedMessages)
    );
    $messages = json_decode($response->getBody(), true);
    $textForMessages = array_map($this->getText, $messages);
    $this->assertEquals($expectedTextForMessages, $textForMessages);
  }
  public function testGetMessagesAfterReturnsAllMessagesForUserAfterGivenDatetime(): void
  {
    $lastSentMessage = $this->sentMessages[count($this->sentMessages) - 1];
    $messagesAfter = $lastSentMessage['timestamp'];
    $response = $this->client->get(
      "/messages",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => ['after' => $messagesAfter],
      ],
    );
    $this->assertEquals(200, $response->getStatusCode());
    $expectedTextForMessagesAfter = array_map($this->getText, $this->receivedMessages);
    $messages = json_decode($response->getBody(), true);
    $textForMessagesAfter = array_map($this->getText, $messages);
    $this->assertEquals($expectedTextForMessagesAfter, $textForMessagesAfter);
  }
  public function testGetMessagesToMeReturnsAllMessagesSentToUser(): void
  {
    $response = $this->client->get(
      "/messages",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => ['toMe' => 1],
      ],
    );
    $this->assertEquals(200, $response->getStatusCode());
    $expectedTextForMessagesToMe = array_map($this->getText, $this->receivedMessages);
    $messages = json_decode($response->getBody(), true);
    $textForMessagesToMe = array_map($this->getText, $messages);
    $this->assertEquals($expectedTextForMessagesToMe, $textForMessagesToMe);
  }
  public function testGetMessagesReturnsBadRequestIfAfterInvalid(): void
  {
    $invalidTimestamp = "2020-=1-11";
    $response = $this->client->get(
      "/messages",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'query' => ['after' => $invalidTimestamp],
        'http_errors' => false,
      ],
    );
    $this->assertEquals(400, $response->getStatusCode());
  }
  
  public function testPostMessagesReturnsBadRequestOnMissingText(): void
  {
    $messageWithoutText = array("toHandle" => $this->others[0]->handle);
    $response = $this->client->post(
      "/messages",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'http_errors' => false,
        'json' => $messageWithoutText,
      ],
    );
    $this->assertEquals(400, $response->getStatusCode());
  }
  public function testPostMessagesReturnsBadRequestOnMissingOrBadToHandle(): void
  {
    $messageWithoutReceipient = array("text" => "hello");
    $response = $this->client->post(
      "/messages",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'http_errors' => false,
        'json' => $messageWithoutReceipient,
      ],
    );
    $this->assertEquals(400, $response->getStatusCode());
    $messageWBadHandle = array("text" => "hello", "toHandle" => "w/");
    $response2 = $this->client->post(
      "/messages",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'http_errors' => false,
        'json' => $messageWBadHandle,
      ],
    );
    $this->assertEquals(400, $response2->getStatusCode());
  }
  public function testPostMessagesReturnsNotFoundOnUnconnectedUserHandle(): void
  {
    $user = $this->others[0];
    $this->repo->abandon_user($this->me->id, $user->handle);
    $messageToUnconnectedUser = array(
      "text" => "hello",
      "toHandle" => $user->handle,
    );
    $response = $this->client->post(
      "/messages",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'http_errors' => false,
        'json' => $messageToUnconnectedUser,
      ],
    );
    $this->assertEquals(404, $response->getStatusCode());
  }
  public function testPostMessagesSendsMessageOnValidMessage(): void
  {
    $user = $this->others[0];
    $message = array("toHandle" => $user->handle, "text" => "a unique message");
    $response = $this->client->post(
      "/messages",
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'json' => $message,
      ],
    );
    $this->assertEquals(201, $response->getStatusCode());
    $messages = $this->repo->get_user_messages($this->me->id);
    $sanitizedMessages = array_map(
      function ($msg) {
        return array(
          "toHandle" => $msg["toHandle"],
          "text" => $msg["text"],
        );
      },
      $messages
    );
    $this->assertContains($message, $sanitizedMessages);
  }

  public function setUp(): void
  {
    parent::setUp();
    $this->setUserConnections();
    $this->setConversations();
    $this->getText = function ($msg) {
      return $msg['text'];
    };
  }

  public $sentMessages;
  public $receivedMessages;
  public $getText;

  public function tearDown(): void
  {
    $this->clearUserConnections();
    parent::tearDown();
  }

  public function setConversations(): void
  {
    $user = $this->others[0];
    $this->sentMessages = [
      array("text" => "text1", "toHandle" => $user->handle, "timestamp" => ""),
      array("text" => "text2", "toHandle" => $user->handle, "timestamp" => ""),
      array("text" => "text3", "toHandle" => $user->handle, "timestamp" => ""),
      array("text" => "text4", "toHandle" => $user->handle, "timestamp" => ""),
    ];
    $this->receivedMessages = [
      array("text" => "text5", "toHandle" => $this->me->handle, "timestamp" => ""),
      array("text" => "text6", "toHandle" => $this->me->handle, "timestamp" => ""),
      array("text" => "text7", "toHandle" => $this->me->handle, "timestamp" => ""),
      array("text" => "text8", "toHandle" => $this->me->handle, "timestamp" => ""),
    ];

    for($i = 0; $i < count($this->sentMessages); ++$i) {
      $result = $this->repo->add_user_message( $this->me->id, $this->sentMessages[$i]);
      $this->sentMessages[$i]["timestamp"] = $result["timestamp"];
      sleep(1);
    }
    for($i = 0; $i < count($this->receivedMessages); ++$i) {
      $result = $this->repo->add_user_message($user->id, $this->receivedMessages[$i]);
      $this->receivedMessages[$i]["timestamp"] = $result["timestamp"];
      sleep(1);
    }
  }
}