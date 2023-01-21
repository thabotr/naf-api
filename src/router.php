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
  static function delete(string $route, string $url_resource_to_delete_pattern, callable $callback): void
  {
    if (Router::we_should_handle_request($route, "DELETE")) {
      preg_match("`" . $route . $url_resource_to_delete_pattern . "`", $_SERVER['REQUEST_URI'], $matches);
      call_user_func($callback, $matches);
      exit;
    }
  }

  static function post(string $route, callable $callback): void
  {
    if (Router::we_should_handle_request($route, "POST")) {
      $request_body = file_get_contents('php://input');
      $url = $_SERVER['REQUEST_URI'];
      preg_match("`/(?<handle>w/[a-zA-Z0-9-_]+)`", $url, $matches);
      $rest_of_url = count($matches) >= 2 ? $matches[1] : '';
      call_user_func($callback, $request_body, $rest_of_url);
      exit;
    }
  }
}
?>