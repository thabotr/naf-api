<?php
namespace repository\database;

require_once(realpath(dirname(__FILE__) . '/../../src/repository.php'));
use PHPUnit\Framework\TestCase;
use repository\database\DBRepository;

class Common extends TestCase
{
  public $db_repo;
  public function setUp(): void
  {
    $this->db_repo = new DBRepository(
      "tartarus.aserv.co.za:3306",
      "thabolao_naf_admin",
      "naf_admin_pw",
      "thabolao_naf_db"
    );
  }
}