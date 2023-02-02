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
  echo "pong";
  exit;
});

$db_repo = new DBRepository();
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

Router::post("/profiles/my-profile", function () use($handle, $token, $db_repo) {
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

$user_id;
$profile;
try
{
  [$user_id, $profile] = $db_repo->get_user_id_and_profile($handle, $token);
} catch( UserNotFoundException $_) {
  header("HTTP/1.0 401 Unauthorized");
  exit;
}

class TimeFormatException extends Exception {
  function __construct(string $timeFieldName = null)
  {
    if( $timeFieldName == null)
    {
      parent::__construct("invalid datetime format. " .
        "Expected a time string of format '%Y-%m-%d %H:%i:%s:v'"
      );
    } else {
      parent::__construct(
        "field '". $timeFieldName .
        "' should be a time string of format '%Y-%m-%d %H:%i:%s:v'"
      );
    }
  }
}

function validate_timestamp(string $timestamp, $name = null): void
{
  try
  {
    new DateTime($timestamp);
  } catch( Exception $_) {
    throw new TimeFormatException($name);
  }
}

$notificationsLabels = ['messagesAfter', 'connectionsAfter'];
function validate_notifications_selectors(array $selectors): void
{
  global $notificationsLabels;
  foreach( $notificationsLabels as $label) {
    if(isset($selectors[$label])) {
      try {
        validate_timestamp($selectors[$label], $label);
      } catch (TimeFormatException $e) {
        Router::sendText($e->getMessage(), 400);
      }
    }  
  }
}

Router::get(
  "/notifications",
  function ($params) use ($user_id, $db_repo) {
    $bitString = "00";
    validate_notifications_selectors($params);
    $now = $db_repo->datetime_now();
    $msgsAfterTimestamp = isset($params['messagesAfter']) 
      ? $params['messagesAfter'] : $now;
    $connsAfterTimestamp = isset($params['connectionsAfter'])
      ? $params['connectionsAfter'] : $now;

    $counts = $db_repo->count_records_created_after(
      $user_id,
      new \DateTimePerRelation($msgsAfterTimestamp, $connsAfterTimestamp)
    );
    $bitString[0] = $counts->messageCount > 0 ? '1' : '0';
    $bitString[1] = $counts->connectionsCount > 0 ? '1' : '0';
    Router::sendText($bitString);
  }
);

Router::delete("/connections", function (array $params) use ($user_id, $db_repo) {
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

Router::delete("/profiles/my-profile", function () use ($user_id, $db_repo) {
  $db_repo->delete_user_account($user_id);
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

Router::get("/messages", function (array $filters) use ($handle, $user_id, $db_repo) {
  $messages = $db_repo->get_user_messages($user_id);

  if (isset($filters['after'])) {
    try {
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
    } catch (Exception $_) {
      header('HTTP/1.0 400 Bad Request');
      echo "parameter 'after' should be a time string of format '%Y-%m-%d %H:%M:%S'";
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
  header("Content-Type: application/json");
  $resp_str = json_encode($profile);
  header("Content-Length: " . strlen($resp_str));
  echo $resp_str;
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
  } catch (Throwable $_) {
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
    header("HTTP/1.0 500 Internal Server Error");
    exit;
  }
  echo json_encode($repo_result);
  exit;
});

header('HTTP/1.0 404 Not Found');
exit;
?>