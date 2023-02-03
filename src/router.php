<?php
namespace resource;

function route_matches_uri(string $route, string $uri): bool {
  $regexp = "`$route(/.*|\?.*|$)`";
  return preg_match($regexp, $uri);
}

class Router
{
  static function we_should_handle_request(string $route, string $method): bool
  {
    $request_uri = $_SERVER['REQUEST_URI'];
    $request_method = $_SERVER['REQUEST_METHOD'];
    $uri_matches_our_route = route_matches_uri($route, $request_uri);
    $method_matches_ours = $request_method === $method;
    return $uri_matches_our_route and $method_matches_ours;
  }
  static function get(string $route, callable $callback): void
  {
    if (Router::we_should_handle_request($route, "GET")) {
      call_user_func($callback, $_GET);
      exit;
    }
  }
  static function delete(string $route, callable $callback): void
  {
    if (Router::we_should_handle_request($route, "DELETE")) {
      call_user_func($callback, $_GET);
      exit;
    }
  }

  static function post(string $route, callable $callback): void
  {
    if (Router::we_should_handle_request($route, "POST")) {
      $request_body = file_get_contents('php://input');
      call_user_func($callback, $request_body);
      exit;
    }
  }

  static function setStatusHeader(int $status = null): void
  {
    switch($status) {
      case 200:
        header("HTTP/1.0 200 OK");
        break;
      case 201:
        header("HTTP/1.0 201 Created");
        break;
      case 400:
        header("HTTP/1.0 400 Bad Request");
        break;
      case 401:
        header('HTTP/1.0 401 Unauthorized');
        break;
      case 404:
        header('HTTP/1.0 404 Not Found');
        break;
      case 409:
        header('HTTP/1.0 409 Conflict');
        break;
      default:
        header("HTTP/1.0 500 Internal Server Error");
    }
  }
  
  static function sendJSON(array $data, int $status = null) {
    Router::setStatusHeader($status);
    header("Content-Type: application/json");
    $resp_str = json_encode($data);
    header("Content-Length: " . strlen($resp_str));
    echo $resp_str;
    exit;
  }
  static function sendText(string $text, int $status = null) {
    Router::setStatusHeader($status);
    header("Content-Type: text/plain");
    header("Content-Length: " . strlen($text));
    echo $text;
    exit;
  }
}
?>