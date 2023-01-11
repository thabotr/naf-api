<?php
namespace middleware\business_rules;
class Validator {
  static function is_valid_handle(string $handle): bool {
    $valid_handle_pattern = "`w/[a-zA-Z0-9-_]+`";
    return preg_match($valid_handle_pattern, $handle);
  }

  static function is_valid_token(string $token): bool {
    $is_atleast_length_8 = strlen($token) >= 8;
    return $is_atleast_length_8;
  }
}
?>