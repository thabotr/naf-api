<?php
namespace repository\database;

require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/../../src/repository.php'));
use PHPUnit\Framework\TestCase;
use repository\database\DBRepository;

class CommonTest extends TestCase
{
  public $db_repo;
  public function setUp(): void
  {

    $this->db_repo = new DBRepository();
  }

  public function testSilenceWarningAboutMissingTests(): void
  {
    $this->assertTrue(true);
  }

  protected function setUsers(array $profiles): void
  {
    foreach( $profiles as $profile) {
      $this->db_repo->query(
        "INSERT IGNORE INTO user(id, handle, token) " .
        "VALUES ($profile->id, '$profile->handle', '$profile->token')"
      );
    }
  }

  protected function clearUsers(array $users = null): void
  {
    foreach($users as $user) {
      $this->db_repo->query("DELETE FROM user WHERE id = " . $user->id);
    }
  }
}