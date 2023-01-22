CREATE DEFINER=`thabolao_naf_admin`@`%` PROCEDURE `connect_users`(
	IN `from_user` INT,
	IN `to_user_handle` VARCHAR(50)
)
LANGUAGE SQL
NOT DETERMINISTIC
CONTAINS SQL
SQL SECURITY DEFINER
COMMENT ''
BEGIN
	DECLARE to_user INT DEFAULT NULL;
	DECLARE created_at TIMESTAMP DEFAULT NULL;
	
  `body`:
  BEGIN
    SET to_user = (SELECT id FROM user WHERE handle = to_user_handle LIMIT 1);
    IF to_user IS NULL THEN -- user handle does not exist
    	LEAVE `body`;
    END IF;
    
    SET created_at = (
      SELECT `connection`.created_at FROM connection
      WHERE user_a = from_user AND user_b = to_user
        OR user_a = to_user AND user_b = from_user
      LIMIT 1
    );
    IF created_at IS NOT NULL THEN -- connection between users exists
      LEAVE `body`;
    END IF;
    
    SET created_at = (
      SELECT `connection_request`.created_at FROM connection_request
      WHERE `connection_request`.from_user = from_user 
        AND `connection_request`.to_user = to_user
    );
    IF created_at IS NOT NULL THEN -- connection request already created
      LEAVE `body`;
    END IF;
    
    SET created_at = (
      SELECT `connection_request`.created_at FROM connection_request
    WHERE `connection_request`.from_user = to_user 
      AND `connection_request`.to_user = from_user
    );
    IF created_at IS NOT NULL THEN -- connection request already sent by to_user_handle
      -- we remove the connection request by to_user_handle
      DELETE FROM connection_request
      WHERE `connection_request`.from_user = to_user 
        AND `connection_request`.to_user = from_user;
      -- and connect the two users
      INSERT INTO connection(created_at, user_a, user_b)
      SELECT 
        created_at,
        IF( from_user < to_user, from_user, to_user) AS user_a, 
        IF( from_user < to_user, to_user, from_user) AS user_b;
      LEAVE `body`;
    END IF;

    -- we create the connection request
    SET created_at = (SELECT CURRENT_TIMESTAMP());
    INSERT INTO connection_request(created_at, from_user, to_user)
    VALUES( created_at, from_user, to_user);
  END `body`;
	SELECT created_at;
END