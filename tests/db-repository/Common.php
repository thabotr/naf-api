<?php
namespace repository\database;

require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/../../src/common.php'));
require_once(realpath(__DIR__ . '/../../src/repository.php'));

use common\Config;
use PHPUnit\Framework\TestCase;
use repository\database\DBRepository;

class Common extends TestCase
{
  public $db_repo;
  public function setUp(): void
  {

    $config = new Config();
    $this->db_repo = new DBRepository(
      $config->dbConfig->host,
      $config->dbConfig->username,
      $config->dbConfig->password,
      $config->dbConfig->database,
    );
  }
}