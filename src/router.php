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
      call_user_func($callback);
      exit;
    }
  }
  static function getParamed(string $route, callable $callback): void
  {
    if (Router::we_should_handle_request($route, "GET")) {
      call_user_func($callback, $_GET);
      exit;
    }
  }
  static function delete(string $route, string $url_resource_to_delete_pattern, callable $callback): void
  {
    if (Router::we_should_handle_request($route, "DELETE")) {
      preg_match("`" . $route . $url_resource_to_delete_pattern . "`", $_SERVER['REQUEST_URI'], $matches);
      call_user_func($callback, $matches);
      exit;
    }
  }
  static function deleteParamed(string $route, callable $callback): void
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
}
?>