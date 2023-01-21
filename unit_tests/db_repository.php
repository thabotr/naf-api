<?php require_once(realpath(dirname(__FILE__) . '/../src/repository.php'));
use PHPUnit\Framework\TestCase;
use repository\database\DBRepository;

class DBRepositoryTest extends TestCase
{
  public $db_repo;

  protected function setUp()
  {
    $this->db_repo = new DBRepository(
      "tartarus.aserv.co.za:3306",
      "thabolao_naf_admin",
      "naf_admin_pw",
      "thabolao_naf_db"
    );
  }
  protected function tearDown()
  {
    $this->db_repo->close();
  }

  private function _addUserMessages(): void
  {
    $this->db_repo->query("DELETE FROM message WHERE from_user = 1 OR to_user = 1");
    $this->db_repo->query("INSERT INTO message(text, from_user, to_user) VALUES ('t1', 1, 2)");
    $this->db_repo->query("INSERT INTO message(text, from_user, to_user) VALUES ('t2', 2, 1)");
    $this->db_repo->query("INSERT INTO message(text, from_user, to_user) VALUES ('t3', 1, 3)");
    $this->db_repo->query("INSERT INTO message(text, from_user, to_user) VALUES ('t4', 1, 3)");
    $this->db_repo->query("INSERT INTO message(text, from_user, to_user) VALUES ('t5', 3, 1)");
  }
  private function _removeUserMessages(): void
  {
    $this->db_repo->query("DELETE FROM message WHERE from_user = 1 OR to_user = 1");
  }
  public function testGetUserMessagesReturnsAllTheUsersMessages(): void
  {
    $user_id = 1;
    $user_handle = "w/testHandle";
    $expected_number_of_messages = 5;
    $this->_addUserMessages();
    $messages = $this->db_repo->get_user_messages($user_id);
    $this->_removeUserMessages();
    $this->assertEquals($expected_number_of_messages, count($messages));
    foreach ($messages as $msg) {
      $is_user_message = $msg['fromHandle'] === $user_handle
        || $msg['toHandle'] === $user_handle;
      $this->assertTrue($is_user_message);
    }
  }
  public function testGetUserIdAndProfileReturnsValidResult(): void
  {
    $test_handle = "w/testHandle";
    $test_token = "testToken";
    [$user_id, $profile] = $this->db_repo->get_user_id_and_profile($test_handle, $test_token);
    $expected_id = 1;
    $expected_profile = array("handle" => "w/testHandle");
    $this->assertEquals($expected_id, $user_id);
    $this->assertArraySubset($expected_profile, $profile);
    $this->assertArraySubset($profile, $expected_profile);
  }
  private function _setUpConnections(): void
  {
    $this->db_repo->query("INSERT IGNORE INTO connection(user_a, user_b) VALUES (1, 2)");
    $this->db_repo->query("INSERT IGNORE INTO connection(user_a, user_b) VALUES (1, 3)");
  }
  public function testGetUserChatsReturnsValidResult(): void
  {
    $this->_setUpConnections();
    $test_id = 1;
    $chats = $this->db_repo->get_user_chats($test_id);
    $expected_chats = [
      array("user" => array("handle" => "w/testHandle2")),
      array("user" => array("handle" => "w/testHandle3"))
    ];
    $this->assertArraySubset($expected_chats, $chats);
    $this->assertArraySubset($chats, $expected_chats);
  }

  public function testDeleteUserChatDeletesConnectionBetweenUserAndChat(): void
  {
    $this->_setUpConnections();
    $test_id = 1;
    $chat_handle = "w/testHandle2";
    $this->db_repo->delete_user_chat($test_id, $chat_handle);
    $chats = $this->db_repo->get_user_chats($test_id);
    $expected_chats = [array("user" => array("handle" => "w/testHandle3"))];
    $this->assertArraySubset($expected_chats, $chats);
    $this->assertArraySubset($chats, $expected_chats);
  }

  public function testAddUserMessageReturnsTimestampOnSuccess(): void
  {
    $this->_setUpConnections();
    $this->db_repo->query("DELETE FROM message WHERE text = 'test text'");
    $user_id = 1;
    $message = array(
      "text" => "test text",
      "toHandle" => "w/testhandle2",
    );
    $message_metadata = $this->db_repo->add_user_message($user_id, $message);
    $timestamp = $message_metadata['timestamp'];
    $timestamp_format = "%d-%d-%d %d:%d:%d";
    $this->assertStringMatchesFormat($timestamp_format, $timestamp);
  }

  public function testAddUserMessageReturnsAnEmptyArrayWhenSendingToUnconnectedUser(): void
  {
    $this->_setUpConnections();
    $this->db_repo->query("DELETE FROM message WHERE text = 'test text 2'");
    $user_id = 1;
    $message = array(
      "text" => "test text 2",
      "toHandle" => "w/testhandle4",
    );
    $message_metadata = $this->db_repo->add_user_message($user_id, $message);
    $this->assertEquals(0, count($message_metadata));
  }

  public function testAddUserReturnsFalseOnDuplicateHandle(): void
  {
    $existing_user = array(
      "handle" => "w/testHandle",
      "token" => "anyToken",
    );
    $user_added = $this->db_repo->add_user($existing_user);
    $this->assertFalse($user_added);
  }

  public function testAddUserReturnsTrueWhenNewUserCreated(): void
  {
    $nonexisting_user = array(
      "handle" => "w/newTestHandle",
      "token" => "anyToken",
    );
    $handle = $nonexisting_user['handle'];
    $this->db_repo->query("DELETE FROM user WHERE handle = '$handle'");
    $user_added = $this->db_repo->add_user($nonexisting_user);
    $this->assertTrue($user_added);
  }
  protected $from_user_id = 1;
  protected $to_user_id = 4;
  protected $to_user_handle = "w/testHandlle4";
  private function _clearConnectionRequest(): void
  {
    $this->db_repo->query("DELETE FROM connection_request WHERE to_user = $this->to_user_id");
  }
  private function _checkUserConnectionRequests($to_user_id)
  {
    $sql_res = $this->db_repo->query(
      "SELECT COUNT(*) AS count, created_at AS `timestamp`
        FROM connection_request
        WHERE from_user = $this->from_user_id AND to_user = $to_user_id
      ",
      MYSQLI_USE_RESULT,
    );
    return $sql_res->fetch_assoc();
  }
  public function testAddConnectionRequestInsertsConnectionToDbAndReturnsTimestampOnSuccess(): void
  {
    $this->_clearConnectionRequest();
    $repo_result = $this->db_repo->add_connection_request(
      $this->from_user_id, $this->to_user_handle
    );
    $this->assertArrayHasKey("timestamp", $repo_result);

    $db_result = $this->_checkUserConnectionRequests($this->to_user_id);
    $connection_added_to_db = $db_result['count'] >= 1;
    $this->assertTrue($connection_added_to_db);
    $this->assertEquals($repo_result['timestamp'], $db_result['timestamp']);
  }
  public function testAddConnectionRequestJustReturnsTimestampOnDuplicateCall(): void
  {
    $this->_clearConnectionRequest();
    $multiple_calls = 2;
    for ($call = 0; $call < $multiple_calls; ++$call) {
      $this->db_repo->add_connection_request($this->from_user_id, $this->to_user_handle);
    }
    $db_result = $this->_checkUserConnectionRequests($this->to_user_id);
    $connection_only_added_once_to_db = $db_result['count'] == 1;
    $this->assertTrue($connection_only_added_once_to_db);
  }

  public function testAddConnectionRequestReturnsTimestampOnAlreadyConnected(): void
  {
    $to_connected_user_id = 2;
    $to_connected_user_handle = "w/testHandle2";
    $repo_result = $this->db_repo->add_connection_request($this->from_user_id, $to_connected_user_handle);
    $this->assertArrayHasKey('timestamp', $repo_result);
    $db_result = $this->_checkUserConnectionRequests($to_connected_user_id);
    $connection_request_not_created = $db_result['count'] == 0;
    $this->assertTrue($connection_request_not_created);
  }
}
?>