<?php
namespace middleware\rules;

use DateTime, DateTimeZone, Exception;

class NoConnectionRequestTimestampException extends Exception {
  public function __construct()
  {
    parent::__construct("No connection request timestamp");
  }
}

class Validator
{
  static function is_valid_handle(string $handle): bool
  {
    $valid_handle_pattern = "`^w/[a-zA-Z0-9-_]+$`";
    return preg_match($valid_handle_pattern, $handle);
  }

  static function is_valid_token(string $token): bool
  {
    $is_atleast_length_8 = strlen($token) >= 8;
    return $is_atleast_length_8;
  }

  static function validate_notification_instructions(array $instructions): string
  {

    if (!isset($instructions['messagessince'])) {
      return "missing header 'messagessince'";
    }

    try {
      new DateTime($instructions['messagessince'], new DateTimeZone('UTC'));
    } catch (Exception $e) {
      return "instruction header 'messagessince' should be a UTC time of " +
        "format '%Y-%m-%d %H:%M:%S'";
    }

    return '';
  }

  static function validate_messages_filters(array $filters): string
  {

    if (!isset($filters['since'])) {
      return '';
    }

    try {
      new DateTime($filters['since'], new DateTimeZone('UTC'));
    } catch (Exception $e) {
      return "filter header 'since' should be a UTC time of format '%Y-%m-%d %H:%M:%S'";
    }
    return '';
  }
}
?>