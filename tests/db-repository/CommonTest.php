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

  public function testSilenceNoTestsWarning(): void
  {
    $this->assertTrue(true);
  }
}