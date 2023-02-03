<?php
namespace repository\database;

require_once(realpath(dirname(__FILE__) . '/CommonTest.php'));
require_once(realpath(dirname(__FILE__) . '/../../src/repository.php'));
require_once(realpath(dirname(__FILE__) . '/../../src/types/profile.php'));
use Profile;

class AddConnectionRequestTest extends CommonTest {
  public function testAddsRequestIntoDbAndReturnsTimestampOnSuccess(): void
  {
    $repo_result = $this->db_repo->add_connection_request(
      $this->me->id, $this->other_user->handle
    );
    $this->assertArrayHasKey("timestamp", $repo_result);
    $db_result = $this->getConnectionRequestBetween($this->me, $this->other_user)[0];
    $this->assertEquals($repo_result['timestamp'], $db_result['timestamp']);
    $this->clearRequestsBetween($this->me, $this->other_user);
  }
  public function testReturnsTimestampForPreviousRequestOnDuplicateCall(): void
  {
    $multiple_calls = 2;
    $timestamps_are_the_same = true;
    $prev_result = null;
    for ($call = 0; $call < $multiple_calls; ++$call) {
      $result = $this->db_repo->add_connection_request(
        $this->me->id,
        $this->other_user->handle
      );
      if($call > 0) {
        $timestamps_are_the_same = $result["timestamp"] === $prev_result["timestamp"] && 
        $timestamps_are_the_same;
      }
      $prev_result = $result;
    }
    $db_result = $this->getConnectionRequestBetween($this->me, $this->other_user);
    $connection_only_added_once_to_db = count($db_result) == 1;
    $this->assertTrue($connection_only_added_once_to_db);
    $this->clearRequestsBetween($this->me, $this->other_user);
  }

  public function testReturnsConnectionTimestampOnAlreadyConnected(): void
  {
    $connection = $this->connectUsers($this->me, $this->other_user);
    $this->assertArrayHasKey('timestamp', $connection);
    $request = $this->db_repo->add_connection_request(
      $this->me->id,
      $this->other_user->handle,
    );
    $this->assertArrayHasKey('timestamp', $request);
    $this->assertEquals($connection['timestamp'], $request['timestamp']);
    $this->disconnectUsers($this->me, $this->other_user);
  }

  public function testAddsConnectionBetweenUsersAndReturnsConnectionTimestampOnMutualRequest(): void
  {
    $this->db_repo->add_connection_request($this->other_user->id, $this->me->handle);
    $this->db_repo->add_connection_request($this->me->id, $this->other_user->handle);
    $users_are_connected = $this->areConnected($this->me, $this->other_user);
    $this->assertTrue($users_are_connected);

    $request_btwn = $this->getConnectionRequestBetween($this->me, $this->other_user);
    $request_to_connect_does_not_exist = count($request_btwn) == 0;
    $this->assertTrue($request_to_connect_does_not_exist);
    $this->disconnectUsers($this->me, $this->other_user);
  }

  private function areConnected(Profile $userA, Profile $userB): bool
  {
    $res = $this->db_repo->execute_result_query(
      "SELECT count(*) AS count FROM connection WHERE user_a = ? AND user_b = ? " .
      "OR user_a = ? AND user_b = ?",
      "iiii",
      $userA->id,
      $userB->id,
      $userB->id,
      $userA->id,
    );
    return $res[0]['count'] == 1;
  }

  private function connectUsers(Profile $userA, Profile $userB): array
  {
    $user_1_id = $userA->id;
    $user_2_id = $userB->id;
    if( $user_1_id > $user_2_id) {
      $user_1_id += $user_2_id;
      $user_2_id = $user_1_id - $user_2_id;
      $user_1_id -= $user_2_id;
    }
    $this->db_repo->query(
      "INSERT INTO connection(user_a, user_b) VALUES ($user_1_id, $user_2_id)"
    );
    return $this->db_repo->execute_result_query(
      "SELECT created_at AS timestamp FROM connection WHERE user_a = ? AND user_b = ?",
      "ii",
      $user_1_id,
      $user_2_id,
    )[0];
  }

  private function disconnectUsers(Profile $userA, Profile $userB): void
  {
    $this->db_repo->query(
      "DELETE FROM connection WHERE user_a = $userA->id AND user_b = $userB->id " .
      "OR user_a = $userB->id AND user_b = $userA->id"
    );
  }

  private function clearRequestsBetween(Profile $userA, Profile $userB): void
  {
    $this->db_repo->query(
      "DELETE FROM connection_request " .
      "WHERE from_user = $userA->id AND to_user = $userB->id " .
      "OR from_user = $userB->id AND to_user = $userA->id"
    );
  }

  private function getConnectionRequestBetween(Profile $userA, Profile $userB): array
  {
    return $this->db_repo->execute_result_query(
      "SELECT created_at AS timestamp FROM connection_request WHERE from_user = ? " .
      "AND to_user = ? OR to_user = ? AND from_user = ?",
      "iiii",
      $userA->id,
      $userB->id,
      $userA->id,
      $userB->id,
    );
  }
  private $me;
  private $other_user;
  public function setUp(): void
  {
    parent::setUp();
    $this->me = new Profile(1, "w/testHandle", "testToken");
    $this->other_user = new Profile(2, "w/testHandle2", "testToken2");
    // ensure clearing of potentially pre-existing connections and requests
    $this->clearUsers([$this->me, $this->other_user]);
    $this->setUsers([$this->me, $this->other_user]);
  }

  public function tearDown(): void
  {
    $this->clearUsers([$this->me, $this->other_user]);
    parent::tearDown();
  }
}