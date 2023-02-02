<?php
namespace common;

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
use Dotenv\Dotenv;

class DBConfig {
  public $host;
  public $username;
  public $password;
  public $database;

  public function __construct($host, $username, $password, $database) {
    $this->host = $host;
    $this->username = $username;
    $this->password = $password;
    $this->database = $database;
  }
}

class Config {
  public $dbConfig;
  public function __construct() {
    $dotenv = Dotenv::createImmutable(realpath(__DIR__ . '/../'));
    $dotenv->load();

    if( $_ENV["TEST_ENVIRONMENT"] == "PROD") {
      $this->dbConfig = new DBConfig(
        $_ENV["PROD_DB_HOST"],
        $_ENV["PROD_DB_USERNAME"],
        $_ENV["PROD_DB_PASSWORD"],
        $_ENV["PROD_DB_SCHEMA"],
      );
    } else {
      $this->dbConfig = new DBConfig(
        $_ENV["DEV_DB_HOST"],
        $_ENV["DEV_DB_USERNAME"],
        $_ENV["DEV_DB_PASSWORD"],
        $_ENV["DEV_DB_SCHEMA"],
      );
    }
  }
}