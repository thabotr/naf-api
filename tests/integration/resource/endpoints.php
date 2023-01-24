<?php
require_once(realpath(dirname(__FILE__) . '/../../../vendor/autoload.php'));
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class HTTPResourceTests extends TestCase
{
  public $client;
  public function setUp()
  {
    $this->client = new Client([
      'base_uri' => "http://localhost:8000/naf/api",
      'timeout' => 2.0
    ]);
  }

  public function testGetPingReturnsPong(): void
  {
    $response = $this->client->get("/ping");
    $this->assertEquals($response->getStatusCode(), 200);
    $this->assertTrue($response->getBody() == "pong");
  }
}


?>