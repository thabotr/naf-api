<?php require_once(realpath(dirname(__FILE__) . '/../src/repository.php'));
use phpDocumentor\Reflection\Types\Void_;
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

  private $user_id = 1;
  protected $user_handle = 'w/testHandle';
  private function _addUserMessages(): void
  {
    $this->_setUpConnections();
    $this->db_repo->query("DELETE FROM message WHERE from_user = $this->user_id OR " .
      "to_user = $this->user_id");
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
    $this->db_repo->query("DELETE FROM connection WHERE user_a = $this->user_id OR " .
      "user_b = $this->user_id");
    $this->db_repo->query("INSERT IGNORE INTO connection(user_a, user_b) VALUES ($this->user_id, 2)");
    $this->db_repo->query("INSERT IGNORE INTO connection(user_a, user_b) VALUES ($this->user_id, 3)");
  }
  public function testGetProfilesForConnectedUsersReturnsCorrectResult(): void
  {
    $this->_setUpConnections();
    $profiles = $this->db_repo->get_profiles_for_connected_users($this->user_id);
    $expected_profiles = [
      array("handle" => "w/testHandle2"),
      array("handle" => "w/testHandle3")
    ];
    $this->assertArraySubset($expected_profiles, $profiles);
    $this->assertArraySubset($profiles, $expected_profiles);
  }

  public function testDeleteUserChatDeletesConnectionBetweenUserAndChat(): void
  {
    $this->_setUpConnections();
    $chat_handle = "w/testHandle2";
    $this->db_repo->delete_user_chat($this->user_id, $chat_handle);
    $user_chats = $this->db_repo->get_user_chats($this->user_id);
    $deleted_chat = array("handle" => $chat_handle);
    foreach ($user_chats as $chat) {
      $this->assertNotEquals($deleted_chat, $chat);
    }
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
  private function _checkConnectionRequestsBetween($user_a_id, $user_b_id)
  {
    $sql_res = $this->db_repo->query(
      "SELECT COUNT(*) AS count, created_at AS `timestamp`
        FROM connection_request
        WHERE from_user = $user_a_id AND to_user = $user_b_id
          OR to_user = $user_a_id AND from_user = $user_b_id
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

    $db_result = $this->_checkConnectionRequestsBetween(
      $this->to_user_id,
      $this->from_user_id
    );
    $connection_added_to_db = $db_result['count'] >= 1;
    $this->assertTrue($connection_added_to_db);
    $this->assertEquals($repo_result['timestamp'], $db_result['timestamp']);
  }
  public function testAddConnectionRequestJustReturnsTimestampOnDuplicateCall(): void
  {
    $this->_clearConnectionRequest();
    $multiple_calls = 2;
    for ($call = 0; $call < $multiple_calls; ++$call) {
      $this->db_repo->add_connection_request(
        $this->from_user_id,
        $this->to_user_handle
      );
    }
    $db_result = $this->_checkConnectionRequestsBetween(
      $this->to_user_id,
      $this->from_user_id
    );
    $connection_only_added_once_to_db = $db_result['count'] == 1;
    $this->assertTrue($connection_only_added_once_to_db);
  }

  public function testAddConnectionRequestReturnsTimestampOnAlreadyConnected(): void
  {
    $to_connected_user_id = 2;
    $to_connected_user_handle = "w/testHandle2";
    $repo_result = $this->db_repo->add_connection_request(
      $this->from_user_id,
      $to_connected_user_handle,
    );
    $this->assertArrayHasKey('timestamp', $repo_result);
    $db_result = $this->_checkConnectionRequestsBetween(
      $this->from_user_id,
      $to_connected_user_id
    );
    $connection_request_not_created = $db_result['count'] == 0;
    $this->assertTrue($connection_request_not_created);
  }

  private function _usersConnected(int $user_id_a, int $user_id_b): bool
  {
    $sql_res = $this->db_repo->query(
      "SELECT COUNT(*) as count FROM connection WHERE " .
      "user_a = $user_id_a AND user_b = $user_id_b",
      MYSQLI_USE_RESULT,
    );
    $db_result = $sql_res->fetch_assoc();
    return $db_result['count'] >= 1;
  }

  private function _disconnectUsers(int $user_1_id, int $user_2_id): void
  {
    if ($user_1_id > $user_2_id) {
      // swap
      $user_1_id += $user_2_id;
      $user_2_id = $user_1_id - $user_2_id;
      $user_1_id -= $user_2_id;
    }
    $this->db_repo->query(
      "DELETE FROM connection WHERE user_a = $user_1_id AND user_b = $user_2_id"
    );
  }

  private function _clearRequestsBetweenUsers(int $user_a_id, int $user_b_id): void
  {
    $this->db_repo->query(
      "DELETE FROM connection_request " .
      "WHERE from_user = $user_a_id AND to_user = $user_b_id " .
      "OR from_user = $user_b_id AND to_user = $user_a_id"
    );
  }
  public function testAddConnRqstOnMutualRqstToConnectAddsConnectionBetweenUsersAndReturnsConnTimestamp(): void
  {
    $user_5_id = 5;
    $user_5_handle = "w/testHandle5";

    $this->db_repo->add_connection_request($user_5_id, $this->user_handle);
    $this->db_repo->add_connection_request($this->user_id, $user_5_handle);
    $users_are_connected = $this->_usersConnected($this->user_id, $user_5_id);

    $this->assertTrue($users_are_connected);

    $db_result = $this->_checkConnectionRequestsBetween($this->user_id, $user_5_id);
    $request_to_connect_does_not_exist = $db_result['count'] == 0;
    $this->assertTrue($request_to_connect_does_not_exist);

    $this->_disconnectUsers($this->user_id, $user_5_id);
    $this->_clearRequestsBetweenUsers($this->user_id, $user_5_id);
  }

  public function testDeleteUserRemovesUserFromDb(): void
  {
    $user_handle = "w/userToDelete";
    $user_id = 11;
    $user_token = "helloMyPW";
    $this->db_repo->query("INSERT IGNORE INTO user (id, handle, token) VALUES " .
      "($user_id, '$user_handle', '$user_token')");
    $this->db_repo->delete_user($user_id);
    $sql_res = $this->db_repo->query(
      "SELECT COUNT(*) as count FROM user WHERE id = $user_id",
      MYSQLI_USE_RESULT,
    );
    $db_result = $sql_res->fetch_assoc();
    $user_deleted = $db_result['count'] == 0;
    $this->assertTrue($user_deleted);
  }
}
?>