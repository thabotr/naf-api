<?php
namespace middleware\rules;

use DateTime, Exception;

class NoConnectionRequestTimestampException extends Exception {
  public function __construct()
  {
    parent::__construct("No connection request timestamp");
  }
}
class UserNotFoundException extends Exception {
  public function __construct()
  {
    parent::__construct("Requested user not found");
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

  static function is_valid_datetime(string $datetime): bool
  {
    try
    {
      new DateTime($datetime);
      return true;
    }catch( Exception $_) {
      return false;
    }
  }
}
?>