<?php
require_once(realpath(dirname(__FILE__) . '/src/common.php'));
require_once(realpath(dirname(__FILE__) . '/src/repository.php'));
require_once(realpath(dirname(__FILE__) . '/src/router.php'));
require_once(realpath(dirname(__FILE__) . '/src/types/notifications.php'));
require_once(realpath(dirname(__FILE__) . '/src/validations.php'));

use middleware\rules\NoConnectionRequestTimestampException;
use middleware\rules\UserNotFoundException;
use repository\database\DBRepository;
use resource\Router;
use middleware\rules\Validator;

Router::get("/ping", function () {
  Router::sendText("pong", 200);
});

$db_repo = new DBRepository();
if (!isset($_SERVER['PHP_AUTH_USER'])) {
  header('WWW-Authenticate: BASIC realm="user profile"');
  Router::sendText("", 401);
}

$handle = $_SERVER['PHP_AUTH_USER'];
$token = $_SERVER['PHP_AUTH_PW'];

Router::post("/profiles/my-profile", function () use ($handle, $token, $db_repo) {
  if (!Validator::is_valid_handle($handle)) {
    Router::sendText(
      "invalid handle '$handle'. Valid handle matches regexp 'w/[a-zA-Z0-9-_]+'",
      400
    );
  }
  if (!Validator::is_valid_token($token)) {
    Router::sendText("token too weak. Must be atleast 8 characters", 400);
  }
  $new_user = array(
    "handle" => $handle,
    "token" => $token,
  );
  $user_added = $db_repo->add_user($new_user);
  if ($user_added) {
    Router::sendText("Notifications Are Free $handle!", 201);
  }
  Router::sendText("We already know someone by the handle '$handle'", 409);
});

$user_id;
$profile;
try {
  [$user_id, $profile] = $db_repo->get_user_id_and_profile($handle, $token);
} catch (UserNotFoundException $_) {
  Router::sendText("", 401);
}

Router::get(
  "/notifications",
  function ($params) use ($user_id, $db_repo) {
    $bitString = "00";
    $now = $db_repo->datetime_now();
    $msgsAfterTimestamp = isset($params['messagesAfter'])
      ? $params['messagesAfter'] : $now;
    if (!Validator::is_valid_datetime($msgsAfterTimestamp)) {
      Router::sendText(
        "parameter 'messagesAfter' should be of format '%Y-%m-%d %H:%i:%s:v'",
        400,
      );
    }
    $connsAfterTimestamp = isset($params['connectionsAfter'])
      ? $params['connectionsAfter'] : $now;
    if (!Validator::is_valid_datetime($connsAfterTimestamp)) {
      Router::sendText(
        "parameter 'connectionsAfter' should be of format '%Y-%m-%d %H:%i:%s:v'",
        400,
      );
    }
    $counts = $db_repo->count_records_created_after(
      $user_id,
      new \DateTimePerRelation($msgsAfterTimestamp, $connsAfterTimestamp)
    );
    $bitString[0] = $counts->messageCount > 0 ? '1' : '0';
    $bitString[1] = $counts->connectionsCount > 0 ? '1' : '0';
    Router::sendText($bitString, 200);
  }
);

function array_get(array $array, $key_name, $default_value)
{
  return isset($array[$key_name]) ? $array[$key_name] : $default_value;
}

Router::delete("/connections", function (array $params) use ($user_id, $db_repo) {
  $disconnect_handle = array_get($params, 'toHandle', '');
  if (!Validator::is_valid_handle($disconnect_handle)) {
    Router::sendText(
      "expected 'toHandle' parameter matching 'w/[a-zA-Z0-9-_]+'. " .
      "Found '$disconnect_handle' instead",
      400
    );
  }
  $db_repo->abandon_user($user_id, $disconnect_handle);
  Router::sendText("abandoned user $disconnect_handle", 200);
});

Router::delete("/profiles/my-profile", function () use ($user_id, $db_repo) {
  $db_repo->delete_user_account($user_id);
  Router::sendText("Notifications Are Free and so are you! Cheers😉", 200);
});

Router::get("/profiles/connected-users", function ($params) use ($user_id, $db_repo) {
  $after = new DateTime('1970-01-01');
  if (isset($params['after'])) {
    if (!Validator::is_valid_datetime($params['after'])) {
      Router::sendText("parameter 'after' should be of format '%Y-%m-%d %H:%i:%s.v'", 400);
    }
    $after = new DateTime($params['after']);
  }
  $profiles = $db_repo->get_profiles_for_connected_users($user_id);
  $profiles = array_values(
    array_filter(
      $profiles,
      function ($profile) use ($after) {
        $connectedOn = new DateTime($profile['connected_on']);
        $isAfter = $connectedOn > $after;
        return $isAfter;
      }
    )
  );
  Router::sendJSON($profiles, 200);
});

Router::get("/connections/pending", function () use ($user_id, $db_repo) {
  $connection_requests = $db_repo->get_connection_requests($user_id);
  Router::sendJSON($connection_requests, 200);
});

Router::get("/messages", function (array $filters) use ($handle, $user_id, $db_repo) {
  $messages = $db_repo->get_user_messages($user_id);
  if (isset($filters['after'])) {
    if (!Validator::is_valid_datetime($filters['after'])) {
      Router::sendText("parameter 'after' should be of format '%Y-%m-%d %H:%i:%s:v'", 400);
    }
    $after = new DateTime($filters['after']);
    $messages = array_values(
      array_filter(
        $messages,
        function ($msg) use ($after) {
          $msg_time = new DateTime($msg['timestamp']);
          $is_after = $msg_time > $after;
          return $is_after;
        }
      )
    );
  }
  $toMe = array_get($filters, 'toMe', '0');
  if ($toMe) {
    $messages = array_values(
      array_filter(
        $messages,
        function ($msg) use ($handle) {
          $is_to_me = $msg["toHandle"] == $handle;
          return $is_to_me;
        }
      )
    );
  }
  Router::sendJSON($messages, 200);
});

Router::get("/profiles/my-profile", function () use ($profile) {
  Router::sendJSON($profile, 200);
});


class MessageFormatException extends Exception
{
}

function validateMessage(array $message)
{
  if (!isset($message['toHandle']) || !Validator::is_valid_handle($message["toHandle"])) {
    throw new MessageFormatException("message missing or has invalid 'toHandle' field. " .
      "Valid handle matches regexp 'w/[a-zA-Z0-9-_]+'"
    );
  }
  if (!isset($message['text'])) {
    throw new MessageFormatException("message missing field 'text'");
  }
}

Router::post("/messages", function (string $body) use ($user_id, $db_repo) {
  $message = json_decode($body, true);
  try {
    validateMessage($message);
    $message_metadata = $db_repo->add_user_message($user_id, $message);
    if (count($message_metadata) == 0) {
      Router::sendText("user '" . $message["toHandle"] . "' not found", 404);
    }
    Router::sendJSON($message_metadata, 201);
  } catch (MessageFormatException $e) {
    Router::sendText($e->getMessage(), 400);
  }
});

Router::post("/connections", function (string $to_handle) use ($db_repo, $user_id) {
  if (!Validator::is_valid_handle($to_handle)) {
    Router::sendText(
      "invalid handle in request body. Expected handle matching " .
      "'w/[a-zA-Z0-9-_]+'. Found '$to_handle' instead",
      400,
    );
  }
  try {
    $result = $db_repo->add_connection_request($user_id, $to_handle);
    Router::sendJSON($result, 200);
  } catch (NoConnectionRequestTimestampException $_) {
    Router::sendText("user '" . $to_handle . "' not found", 404);
  }
});

Router::sendText("", 404);