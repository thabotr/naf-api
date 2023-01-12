<?php
namespace middleware\business_rules;

use DateTime, DateTimeZone, Exception;
class Validator {
  static function is_valid_handle(string $handle): bool {
    $valid_handle_pattern = "`w/[a-zA-Z0-9-_]+`";
    return preg_match($valid_handle_pattern, $handle);
  }

  static function is_valid_token(string $token): bool {
    $is_atleast_length_8 = strlen($token) >= 8;
    return $is_atleast_length_8;
  }

  static function validate_notification_instructions(array $instructions): string {

    if (!isset($instructions['messagesSince'])) {
      return "missing field 'messagesSince'";
    }
  
    try {
      new DateTime($instructions['messagesSince'], new DateTimeZone('UTC'));
    } catch( Exception $e) {
      return "instruction field 'messagesSince' should be format '%Y-%m%d %H:%M:%S'";
    }

    return '';
  }
}
?>