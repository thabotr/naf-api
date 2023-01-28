<?php
namespace repository\database {
use middleware\rules\UserNotFoundException;
  require_once(realpath(dirname(__FILE__) . '/validations.php'));
  use mysqli;
  use mysqli_sql_exception;
  use mysqli_stmt;
  use Exception;
  use middleware\rules\NoConnectionRequestTimestampException;

  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  class DBRepository extends mysqli
  {
    function datetime_now(): string
    {
      $res = $this->query("SELECT CURRENT_TIMESTAMP(6) AS `now`");
      return $res->fetch_array()['now'];
    }
    function count_records_created_after(
      $user_id,
      \DateTimePerRelation $dateTimePerRelation
    ): \CountPerRelation
    {
      $stmt = <<< 'SQL'
        SELECT * FROM
          (SELECT COUNT(*) AS messageCount FROM message WHERE created_at > ? AND
            (from_user = ? OR to_user = ?)
          ) M,
          (SELECT COUNT(*) AS connectionsCount FROM connection WHERE created_at > ? AND
            (user_a = ? OR user_b = ?)
          ) C
      SQL;
      $result = $this->execute_result_query(
        $stmt,
        "siisii",
        $dateTimePerRelation->messagesAfter,
        $user_id,
        $user_id,
        $dateTimePerRelation->connectionsAfter,
        $user_id,
        $user_id,
      );
      $counts = $result[0];
      return new \CountPerRelation($counts['messageCount'], $counts['connectionsCount']);
    }
    function abandon_user(int $user_id, string $handle): void
    {
      $this->execute_typed_query(
        "DELETE FROM connection WHERE user_a = ? AND user_b IN " .
        "(SELECT id FROM user WHERE handle = ?)",
        "is",
        $user_id,
        $handle
      );
      $this->execute_typed_query(
        "DELETE FROM connection_request WHERE from_user = ? AND to_user IN " .
        "(SELECT id FROM user WHERE handle = ?)",
        "is",
        $user_id,
        $handle
      );
    }

    function delete_user_account(int $user_id): void
    {
      $this->execute_typed_query("DELETE FROM user WHERE id = ?", "i", $user_id);
    }

    function get_user_messages(int $user_id): array
    {
      $stmt = <<<'SQL'
        SELECT
          message.text,
          message.created_at AS `timestamp`,
          user_sender.handle AS fromHandle,
          user_receipient.handle AS toHandle
        FROM message
        LEFT JOIN user user_sender
        ON message.from_user = user_sender.id
        LEFT JOIN user user_receipient
        ON message.to_user = user_receipient.id
        WHERE user_sender.id = ? OR user_receipient.id = ?
      SQL;
      return $this->execute_result_query(
        $stmt, "ii", $user_id, $user_id
      );
    }

    function delete_user_messages(int $user_id): void
    {
      $this->execute_typed_query(
        "DELETE FROM message WHERE from_user = ? OR to_user = ?",
        "ii",
        $user_id,
        $user_id,
      );
    }

    function get_connection_requests(int $user_id): array
    {
      $stmt = <<<'SQL'
        SELECT 
          user.handle,
          connection_request.created_at as `timestamp`
        FROM connection_request
        INNER JOIN user
        ON connection_request.to_user = user.id
        WHERE from_user = ?
      SQL;
      $result = $this->execute_result_query($stmt, "i", $user_id);
      return $result;
    }

    function add_user(array $new_user)
    {
      $stmt = $this->prepare("INSERT INTO user(handle, token) VALUES (?, ?)");
      $stmt->bind_param("ss", $new_user
        ['handle'], $new_user['token']);
      try {
        $stmt->execute();
      } catch (mysqli_sql_exception $exception) {
        $user_already_exists = preg_match('/duplicate entry/i', $exception);
        if ($user_already_exists) {
          return false;
        }
        throw $exception;
      }
      return true;
    }

    function get_user_id_and_profile(string $handle, string $token): array
    {
      $result = $this->execute_result_query(
        "SELECT id FROM user WHERE handle = ? AND token = ?",
        "ss",
        $handle,
        $token,
      );
      if (count($result) !== 1) {
        throw new UserNotFoundException();
      }
      return [$result[0]['id'], array('handle' => $handle)];
    }
    function get_profiles_for_connected_users(int $user_id): array
    {
      $stmt = <<<'SQL'
      WITH friend
        AS (
          SELECT user_a AS id,
            created_at AS connected_on
            FROM connection WHERE user_b = ?
          UNION
          SELECT user_b AS id,
            created_at AS connected_on
            FROM connection WHERE user_a = ?
        )
      SELECT user.handle, friend.connected_on FROM user
      INNER JOIN friend
      ON friend.id = user.id
      SQL;
      $db_result = $this->execute_result_query($stmt, "ii", $user_id, $user_id);
      return $db_result;
    }
    function add_user_message(int $user_id, array $message): array
    {
      $this->begin_transaction();

      $set_friend_id_variable = "SELECT @friend_id:=(SELECT id FROM user WHERE handle = ?)";
      $stmt = $this->prepare($set_friend_id_variable);
      $recipient_handle = $message['toHandle'];
      $stmt->bind_param("s", $recipient_handle);
      $stmt->execute();
      $stmt->free_result();

      $assert_is_our_friend = "
      SELECT * FROM connection
      WHERE user_a = @friend_id AND user_b = ?
      OR user_b = @friend_id AND user_a = ?";
      $stmt = $this->prepare($assert_is_our_friend);
      $stmt->bind_param("ii", $user_id, $user_id);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res->fetch_array() == NULL) {
        $this->rollback();
        return array();
      }

      $set_msg_created_at_variable = "SELECT @created_at:=(SELECT CURRENT_TIMESTAMP(6))";
      $this->query($set_msg_created_at_variable);

      $add_new_message_statement = "INSERT INTO message( text, from_user, to_user, created_at) ";
      $add_new_message_statement .= "VALUES ( ?, ?, @friend_id, @created_at)";
      $stmt = $this->prepare($add_new_message_statement);
      $message_text = $message['text'];
      $stmt->bind_param("si", $message_text, $user_id);
      $stmt->execute();
      $stmt->free_result();

      $get_msg_created_at = "SELECT @created_at AS created_at";
      $stmt = $this->query($get_msg_created_at);
      $res = $stmt->fetch_assoc();
      $this->commit();

      return array(
        "timestamp" => $res['created_at'],
      );
    }
    function add_connection_request(int $from_user_id, string $to_user_handle): array
    {
      $db_result = $this->execute_result_query(
        "CALL connect_users(?, ?)",
        "is",
        $from_user_id,
        $to_user_handle
      );
      $row = $db_result[0];
      if ($row["created_at"] == NULL) {
        throw new NoConnectionRequestTimestampException();
      }

      return array(
        "timestamp" => $row["created_at"],
      );
    }

    protected static function getResultArray(mysqli_stmt $statement): array
    {
      $statement->execute();
      $result = $statement->get_result();
      $rows = $result->fetch_all(MYSQLI_ASSOC);
      return $rows;
    }

    protected function execute_typed_query(
      string $query,
      string $bind_types,
      &$bind_var,
      &...$bind_vars
    )
    {
      $prepped_stmt = $this->prepare($query);
      if (!$prepped_stmt) {
        throw new Exception($this->error);
      }
      $is_prepped = $prepped_stmt->bind_param($bind_types, $bind_var, ...$bind_vars);
      if (!$is_prepped) {
        throw new Exception($this->error);
      }
      $is_exec = $prepped_stmt->execute();
      if (!$is_exec) {
        throw new Exception($this->error);
      }
      return $prepped_stmt;
    }

    public function execute_result_query(
      string $query,
      string $bind_types,
      &$bind_var,
      &...$bind_vars
    )
    {
      $statement = $this->execute_typed_query($query, $bind_types, $bind_var, ...$bind_vars);
      if (!$statement) {
        throw new Exception($this->error);
      }
      $result = $statement->get_result();
      if (!$result) {
        throw new Exception($this->error);
      }
      $rows = $result->fetch_all(MYSQLI_ASSOC);
      return $rows;
    }
  }
}
?>