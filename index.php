<?php
require_once(realpath(dirname(__FILE__) . '/src/repository.php'));
require_once(realpath(dirname(__FILE__) . '/src/router.php'));
require_once(realpath(dirname(__FILE__) . '/src/validations.php'));

use middleware\rules\NoConnectionRequestTimestampException;
use repository\database\DBRepository;
use resource\Router;
use middleware\rules\Validator;

Router::get("/ping", function () {
  echo "pong";
  exit;
});

$db_repo = new DBRepository(
  "tartarus.aserv.co.za:3306",
  "thabolao_naf_admin",
  "naf_admin_pw",
  "thabolao_naf_db"
);
if (!isset($_SERVER['PHP_AUTH_USER'])) {
  header('WWW-Authenticate: BASIC realm="user profile"');
  header('HTTP/1.0 401 Unauthorized');
  exit;
}

$handle = $_SERVER['PHP_AUTH_USER'];
$token = $_SERVER['PHP_AUTH_PW'];

function validate_token(string $token): void
{
  $is_valid_token = Validator::is_valid_token($token);
  if (!$is_valid_token) {
    header('HTTP/1.0 400 Bad Request');
    echo "token too weak. Must be atleast 8 characters";
    exit;
  }
}

function validate_handle(string $handle): void
{
  $is_valid_handle = Validator::is_valid_handle($handle);
  if (!$is_valid_handle) {
    header('HTTP/1.0 400 Bad Request');
    echo "invalid handle '$handle'. Valid handle matches regexp 'w/[a-zA-Z0-9-_]+'";
    exit;
  }
}

Router::post("/profiles/my-profile", function () {
  global $handle, $token, $db_repo;
  validate_handle($handle);
  validate_token($token);
  $new_user = array(
    "handle" => $handle,
    "token" => $token,
  );
  $user_added = $db_repo->add_user($new_user);
  if ($user_added) {
    header("HTTP/1.0 201 Created");
    echo "Notifications Are Free $handle!";
    exit;
  }

  header('HTTP/1.0 409 Conflict');
  echo "We already know someone by the handle '$handle'";
  exit;
});


[$user_id, $profile] = $db_repo->get_user_id_and_profile($handle, $token);

class EventType
{
  static $IDLE = 0;
  static $NEW_MESSAGE = 1;
}

function user_has_new_msg(
  DateTime $since,
  int $user_id,
  string $handle,
  DBRepository $db_repo
)
{
  $messages = $db_repo->get_user_messages($user_id);
  $received_messages = count(
    array_filter(
      $messages,
      function ($msg) use ($since, $handle) {
        $msg_timestamp = new DateTime($msg['timestamp']);
        $is_new_message = $msg_timestamp > $since;
        $msg_is_to_user = $msg['toHandle'] === $handle;
        return $is_new_message && $msg_is_to_user;
      }
    )
  );
  return $received_messages > 0;
}

function validate_instructions(array $instructions)
{
  $validation_result = Validator::validate_notification_instructions($instructions);
  if ($validation_result !== '') {
    header('HTTP/1.0 400 Bad Request');
    echo $validation_result;
    exit;
  }
}

Router::get("/notifications", function () use ($user_id, $handle, $db_repo) {
  $selectors = getallheaders();
  validate_instructions($selectors);
  $msg_since = new DateTime($selectors['messagessince'], new DateTimeZone('UTC'));
  header('HTTP/1.0 200 OK');
  if (user_has_new_msg($msg_since, $user_id, $handle, $db_repo)) {
    echo EventType::$NEW_MESSAGE;
    exit;
  }
  echo EventType::$IDLE;
  exit;
});

function padded_event(int $event): string
{
  return $event . str_repeat(' ', 4093) . "\r\n";
}

Router::post("/notifications", function ($request_body) use ($user_id, $handle, $db_repo) {
  set_time_limit(0);
  ob_implicit_flush(true);
  ob_end_flush();
  $iter = 0;
  $instructions = json_decode($request_body, true);
  validate_instructions($instructions);

  while ($iter < 10) {
    $msg_since = new DateTime($instructions['messagesSince'], new DateTimeZone('UTC'));
    if (user_has_new_msg($msg_since, $user_id, $handle, $db_repo)) {
      echo padded_event(EventType::$NEW_MESSAGE);
    } else {
      echo padded_event(EventType::$IDLE);
    }
    sleep(1);
    $iter += 1;
  }
  exit;
});

Router::deleteParamed("/connections", function (array $params) use ($user_id, $db_repo) {
  if (!isset($params["toHandle"])) {
    header('HTTP/1.0 400 Bad Request');
    echo "missing URL parameter 'toHandle'";
    exit;
  }
  $disconnect_handle = $params["toHandle"];
  validate_handle($disconnect_handle);
  $db_repo->abandon_user($user_id, $disconnect_handle);
  header('HTTP/1.0 200 OK');
  echo "abandoned user $disconnect_handle";
  exit;
});

Router::delete(
  "/connections",
  "/(?<chat_handle>w/[a-zA-Z0-9-_]+)",
  function (array $matched_patterns) use ($user_id, $db_repo) {
    if (count($matched_patterns) == 0) {
      header('HTTP/1.0 400 Bad Request');
      echo "missing handle in url";
      exit;
    }
    $chat_handle = $matched_patterns['chat_handle'];
    $db_repo->delete_user_chat($user_id, $chat_handle);
    header('HTTP/1.0 200 OK');
    echo "disconnected from $chat_handle";
    exit;
  }
);

Router::delete("/profiles/my-profile", "", function (array $_) use ($user_id, $db_repo) {

  $db_repo->delete_user($user_id);
  header("HTTP/1.0 200 OK");
  echo "Notifications Are Free and so are you! Cheers😉";
  exit;
});

Router::get("/profiles/connected-users", function () use ($user_id, $db_repo) {
  $profiles = $db_repo->get_profiles_for_connected_users($user_id);
  echo json_encode($profiles);
  header('HTTP/1.0 200 OK');
  exit;
});

Router::get("/connections/pending", function () use ($user_id, $db_repo) {
  $connection_requests = $db_repo->get_connection_requests($user_id);
  echo json_encode($connection_requests);
  header('HTTP/1.0 200 OK');
  exit;
});

Router::get("/chats", function () {
  global $user_id, $db_repo;
  $chats = $db_repo->get_user_chats($user_id);
  echo json_encode($chats);
  header('HTTP/1.0 200 OK');
  exit;
});

Router::getParamed("/messages", function ($filters) use ($handle, $user_id, $db_repo) {
  $messages = $db_repo->get_user_messages($user_id);

  if (isset($filters['since'])) {
    try {
      $since = new DateTime($filters['since'], new DateTimeZone("UTC"));
      $messages = array_values(
        array_filter(
          $messages,
          function ($msg) use ($since) {
            $msg_time = new DateTime($msg['timestamp'], new DateTimeZone("UTC"));
            $is_after_since = $msg_time > $since;
            return $is_after_since;
          }
        )
      );
    } catch (Exception $_) {
      header('HTTP/1.0 400 Bad Request');
      echo "parameter 'since' should be a UTC time string of format '%Y-%m-%d %H:%M:%S'";
      exit;
    }
  }

  if (isset($filters['toMe'])) {
    $toMe = $filters['toMe'] == 1;
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
  }

  header("HTTP/1.0 200 OK");
  echo json_encode($messages);
  exit;
});

Router::get("/profiles/my-profile", function () use ($profile) {
  echo json_encode($profile);
  exit;
});


class MessageFormatException extends Exception
{
}

function validateMessage(array $message)
{
  if (!isset($message['toHandle']) || !Validator::is_valid_handle($message["toHandle"])) {
    throw new MessageFormatException("message missing or has invalid field 'toHandle'");
  }
  if (!isset($message['text'])) {
    throw new MessageFormatException("message missing field 'text'");
  }
}

Router::post("/messages", function (string $body) {
  global $user_id, $db_repo;
  $message = (array) json_decode($body);
  try {
    validateMessage($message);
    $message_metadata = $db_repo->add_user_message($user_id, $message);
    if (count($message_metadata) == 0) {
      header('HTTP/1.0 404 Not Found');
      echo "user " . $message["toHandle"] . " not found";
      exit;
    }
    header('HTTP/1.0 201 Created');
    echo json_encode($message_metadata);
    exit;
  } catch (MessageFormatException $e) {
    header('HTTP/1.0 400 Bad Request');
    echo $e->getMessage();
    exit;
  } catch (Throwable $e) {
    var_dump($e);
    header('HTTP/1.0 500 Internal Server Error');
    exit;
  }
});

Router::post("/connections", function (string $to_handle) use ($db_repo, $user_id) {
  if (!Validator::is_valid_handle($to_handle)) {
    header("HTTP/1.0 400 Bad Request");
    echo "invalid or missing handle in request body. Expected handle matching " .
      "'w/[a-zA-Z0-9-_]+'";
    exit;
  }

  $repo_result = array();
  try {
    $repo_result = $db_repo->add_connection_request($user_id, $to_handle);
  } catch (NoConnectionRequestTimestampException $_) {
    header("HTTP/1.0 404 Not Found");
    echo "user " . $to_handle . " not found";
    exit;
  } catch (Exception $_) {
    var_dump($_);
    header("HTTP/1.0 500 Internal Server Error");
    exit;
  }
  echo json_encode($repo_result);
  exit;
});

header('HTTP/1.0 404 Not Found');
exit;
?>