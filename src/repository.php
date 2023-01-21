<?php
namespace repository\database {
  require_once(realpath(dirname(__FILE__) . './validations.php'));
  use mysqli;
  use mysqli_sql_exception;
  use mysqli_stmt;
  use Exception;
  use middleware\rules\NoConnectionRequestTimestampException;

  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  class DBRepository extends mysqli
  {
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

    protected function execute_result_query(
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
      $preped_stmt = $this->prepare($stmt);
      $preped_stmt->bind_param("ii", $user_id, $user_id);
      $messages = $this->getResultArray($preped_stmt);
      return $messages;
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
      $stmt = $this->prepare("SELECT id FROM user WHERE handle=? AND token=?");
      $stmt->bind_param("ss", $handle, $token);
      $rows = DBRepository::getResultArray($stmt);
      if (count($rows) !== 1) {
        header('HTTP/1.0 401 Unauthorized');
        exit;
      }
      return [$rows[0]['id'], array("handle" => $handle)];
    }
    function get_user_chats(int $user_id): array
    {
      $stmt = <<<'SQL'
      WITH friend
        AS (
          SELECT user_a as id FROM connection WHERE user_b = ?
          UNION
          SELECT user_b as id FROM connection WHERE user_a = ?
        )
      SELECT user.handle FROM user
      INNER JOIN friend
      ON friend.id = user.id
      SQL;

      $prepared_stmt = $this->prepare($stmt);
      $prepared_stmt->bind_param("ii", $user_id, $user_id);
      $row_per_chat = DBRepository::getResultArray($prepared_stmt);
      $chats = array_map(
        function ($row) {
          return array("user" => array("handle" => $row['handle']));
        }
        ,
        $row_per_chat
      );
      return $chats;
    }
    function delete_user_chat(int $user_id, string $chat_handle): void
    {
      $stmt = <<<'SQL'
      DELETE FROM connection
      WHERE connection.user_a = ? AND connection.user_b IN (SELECT id FROM user WHERE handle = ?)
      OR
      connection.user_b = ? AND connection.user_a IN (SELECT id FROM user WHERE handle = ?)
      SQL;
      $prepared_stmt = $this->prepare($stmt);
      $prepared_stmt->bind_param("isis", $user_id, $chat_handle, $user_id, $chat_handle);
      $prepared_stmt->execute();
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

      $set_msg_created_at_variable = "SELECT @created_at:=(SELECT CURRENT_TIMESTAMP())";
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
      if (!$this->begin_transaction()) {
        throw new Exception("Failed to start transaction");
      }
      $add_connection_request_stmt = <<<'SQL'
        INSERT INTO connection_request(created_at, from_user, to_user)
        WITH users(sender, receipient) AS (
          SELECT ? AS sender, id AS receipient FROM user WHERE handle = ?
        ),
        conn_request_between_users AS (
          SELECT NULL FROM connection_request
          INNER JOIN users
          ON users.receipient = connection_request.to_user
          WHERE connection_request.from_user = users.sender
        ),
        connection_between_users AS (
          SELECT NULL FROM connection, users
          WHERE connection.user_a = users.sender AND connection.user_b = users.receipient
            OR connection.user_a = users.receipient AND connection.user_b = users.sender
        )
        SELECT
          CURRENT_TIMESTAMP() AS created_at,
          users.sender AS from_user ,
          users.receipient AS to_user
        FROM users
        WHERE NOT EXISTS (
          SELECT NULL FROM conn_request_between_users
          UNION
          SELECT NULL FROM connection_between_users
        )
      SQL;
      $preped_stmt = $this->execute_typed_query(
        $add_connection_request_stmt,
        "is",
        $from_user_id,
        $to_user_handle,
      );
      $preped_stmt->free_result();

      $get_connection_or_connection_request_timestamp_stmt = <<<'SQL'
        WITH users(sender, receipient) AS (
          SELECT ? AS sender, id AS receipient FROM user WHERE handle = ?
        )
        SELECT created_at FROM connection, users
        WHERE connection.user_a = users.sender AND connection.user_b = users.receipient
          OR connection.user_a = users.receipient AND connection.user_b = users.sender
        UNION
        SELECT created_at FROM connection_request, users
        WHERE connection_request.from_user = users.sender
          AND connection_request.to_user = users.receipient
          OR connection_request.from_user = users.receipient
          AND connection_request.to_user = users.sender
      SQL;
      $db_result = $this->execute_result_query(
        $get_connection_or_connection_request_timestamp_stmt,
        "is",
        $from_user_id,
        $to_user_handle,
      );
      if (!$this->commit()) {
        throw new Exception("Failed to commit transaction");
      }
      if (!isset($db_result[0])) {
        throw new NoConnectionRequestTimestampException();
      }
      $row = $db_result[0];

      return array(
        "timestamp" => $row["created_at"],
      );
    }
  }
}
?>