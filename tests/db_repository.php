<?php require_once(realpath(dirname(__FILE__) . '/../src/repository.php'));
use PHPUnit\Framework\TestCase;
use repository\database\DBRepository;

class DBRepositoryTest extends TestCase
{
  public $mysqli;

  protected function setUpConnections(): void
  {
    $this->mysqli->query("INSERT IGNORE INTO connection(user_a, user_b) VALUES (1, 2)");
    $this->mysqli->query("INSERT IGNORE INTO connection(user_a, user_b) VALUES (1, 3)");
  }

  protected function setUp()
  {
    $this->mysqli = new DBRepository("tartarus.aserv.co.za:3306", "thabolao_naf_admin", "naf_admin_pw", "thabolao_naf_db");
  }
  protected function tearDown()
  {
    $this->mysqli->close();
  }
  function addUserMessages(): void {
    $this->mysqli->query("DELETE FROM message WHERE from_user = 1 OR to_user = 1");
    $this->mysqli->query("INSERT INTO message(text, from_user, to_user) VALUES ('t1', 1, 2)");
    $this->mysqli->query("INSERT INTO message(text, from_user, to_user) VALUES ('t2', 2, 1)");
    $this->mysqli->query("INSERT INTO message(text, from_user, to_user) VALUES ('t3', 1, 3)");
    $this->mysqli->query("INSERT INTO message(text, from_user, to_user) VALUES ('t4', 1, 3)");
    $this->mysqli->query("INSERT INTO message(text, from_user, to_user) VALUES ('t5', 3, 1)");
  }
  function removeUserMessages(): void {
    $this->mysqli->query("DELETE FROM message WHERE from_user = 1 OR to_user = 1");
  }
  public function testGetUserMessagesReturnsAllTheUsersMessages(): void {
    $user_id = 1;
    $user_handle = "w/testHandle";
    $expected_number_of_messages = 5;
    $this->addUserMessages();
    $messages = $this->mysqli->get_user_messages($user_id);
    $this->removeUserMessages();
    $this->assertEquals($expected_number_of_messages, count($messages));
    foreach($messages as $msg) {
      $is_user_message = $msg['fromHandle'] === $user_handle || $msg['toHandle'] === $user_handle;
        $this->assertTrue($is_user_message);
    }
  }
  public function testGetUserIdAndProfileReturnsValidResult(): void
  {
    $test_handle = "w/testHandle";
    $test_token = "testToken";
    [$user_id, $profile] = $this->mysqli->get_user_id_and_profile($test_handle, $test_token);
    $expected_id = 1;
    $expected_profile = array("handle" => "w/testHandle");
    $this->assertEquals($expected_id, $user_id);
    $this->assertArraySubset($expected_profile, $profile);
    $this->assertArraySubset($profile, $expected_profile);
  }
  public function testGetUserChatsReturnsValidResult(): void
  {
    $this->setUpConnections();
    $test_id = 1;
    $chats = $this->mysqli->get_user_chats($test_id);
    $expected_chats = [array("user" => array("handle" => "w/testHandle2")), array("user" => array("handle" => "w/testHandle3"))];
    $this->assertArraySubset($expected_chats, $chats);
    $this->assertArraySubset($chats, $expected_chats);
  }

  public function testDeleteUserChatDeletesConnectionBetweenUserAndChat(): void
  {
    $this->setUpConnections();
    $test_id = 1;
    $chat_handle = "w/testHandle2";
    $this->mysqli->delete_user_chat($test_id, $chat_handle);
    $chats = $this->mysqli->get_user_chats($test_id);
    $expected_chats = [array("user" => array("handle" => "w/testHandle3"))];
    $this->assertArraySubset($expected_chats, $chats);
    $this->assertArraySubset($chats, $expected_chats);
  }

  public function testAddUserMessageReturnsTimestampOnSuccess(): void
  {
    $this->setUpConnections();
    $this->mysqli->query("DELETE FROM message WHERE text = 'test text'");
    $user_id = 1;
    $message = array(
      "text" => "test text",
      "toHandle" => "w/testhandle2",
    );
    $message_metadata = $this->mysqli->add_user_message($user_id, $message);
    $timestamp = $message_metadata['timestamp'];
    $timestamp_format = "%d-%d-%d %d:%d:%d";
    $this->assertStringMatchesFormat($timestamp_format, $timestamp);
  }

  public function testAddUserMessageReturnsAnEmptyArrayWhenSendingToUnconnectedUser(): void
  {
    $this->setUpConnections();
    $this->mysqli->query("DELETE FROM message WHERE text = 'test text 2'");
    $user_id = 1;
    $message = array(
      "text" => "test text 2",
      "toHandle" => "w/testhandle4",
    );
    $message_metadata = $this->mysqli->add_user_message($user_id, $message);
    $this->assertEquals(0, count($message_metadata));
  }

  public function testAddUserReturnsFalseOnDuplicateHandle(): void {
    $existing_user = array(
      "handle" => "w/testHandle",
      "token" => "anyToken",
    );
    $user_added = $this->mysqli->add_user($existing_user);
    $this->assertFalse($user_added);
  }

  public function testAddUserReturnsTrueWhenNewUserCreated(): void {
    $nonexisting_user = array(
      "handle" => "w/newTestHandle",
      "token" => "anyToken",
    );
    $handle = $nonexisting_user['handle'];
    $this->mysqli->query("DELETE FROM user WHERE handle = '$handle'");
    $user_added = $this->mysqli->add_user($nonexisting_user);
    $this->assertTrue($user_added);
  }
}

?>