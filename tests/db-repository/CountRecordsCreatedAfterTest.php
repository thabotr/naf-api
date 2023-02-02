<?php
namespace repository\database;
require_once(realpath(dirname(__FILE__) . '/CommonTest.php'));
require_once(realpath(dirname(__FILE__) . '/../../src/repository.php'));
require_once(realpath(dirname(__FILE__) . '/../../src/types/profile.php'));
require_once(realpath(dirname(__FILE__) . '/../../src/types/notifications.php'));


class CountRecordsCreatedAfterTest extends CommonTest
{
  public function testCheckRecordsAfterReturnsCorrectResult(): void
  {

    $counts = $this->db_repo->count_records_created_after(
      $this->me->id,
      new \DateTimePerRelation($this->timeB4MsgSend, $this->timeB4UserConnect)
    );
    $this->assertEquals(1, $counts->messageCount);
    $this->assertEquals(1, $counts->connectionsCount);

    $now = $this->db_repo->datetime_now();
    $latestCounts = $this->db_repo->count_records_created_after(
      $this->me->id,
      new \DateTimePerRelation($now, $now)
    );
    $this->assertEquals(0, $latestCounts->messageCount);
    $this->assertEquals(0, $latestCounts->connectionsCount);
  }
  private $me;
  private $other_user;
  public function setUp(): void
  {
    parent::setUp();
    $this->me = new \Profile(1, "w/testHandle", "testToken");
    $this->other_user = new \Profile(2, "w/testHandle2", "testToken2");
    $this->setUsers([$this->me, $this->other_user]);
    $this->setUserConnections();
    $this->setMessages();
  }

  public function tearDown(): void
  {
    // will also clear connections, conn_requests and messages
    $this->clearUsers();
  }

  public $timeB4MsgSend;
  public $timeB4UserConnect;

  private function setUsers(array $profiles): void
  {
    foreach( $profiles as $profile) {
      $this->db_repo->query(
        "INSERT IGNORE INTO user(id, handle, token) " .
        "VALUES ($profile->id, '$profile->handle', '$profile->token')"
      );
    }
  }

  private function clearUsers(): void
  {
    $this->db_repo->query("DELETE FROM user WHERE id in (1, 2)");
  }

  private function setMessages(): void
  {
    $message = array(
      "text" => "TestNofitications->setMessages",
      "toHandle" => $this->other_user->handle,
    );
    $this->timeB4MsgSend = $this->db_repo->datetime_now();
    $this->db_repo->add_user_message($this->me->id, $message);
  }

  private function setUserConnections(): void
  {
    $this->timeB4UserConnect = $this->db_repo->datetime_now();
    $this->db_repo->query(sprintf(
      "INSERT INTO connection(user_a, user_b) VALUES (%d, %d)",
      $this->me->id,
      $this->other_user->id
    ));
  }
}