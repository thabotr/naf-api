<?php

require_once(realpath(dirname(__FILE__) . '/../src/router.php'));
use PHPUnit\Framework\TestCase;
use function resource\route_matches_uri;

class RouterHelpersTest extends TestCase
{
  function testRouteMatchesUriReturnsCorrectResult(): void {
    $route = "/messages";
    $matching_uris = ["/backend/messages", "/messages/", "/d/messages/this/that", "/messages?helo=1", "/messages/1/"];
    $nonmatching_uris = ["messages", "/message", "/message/here"];
    foreach( $matching_uris as $uri) {
      $this->assertTrue(route_matches_uri($route, $uri));
    }
    foreach( $nonmatching_uris as $uri) {
      $this->assertFalse(route_matches_uri($route, $uri));
    }
  }
}
?>