CREATE TABLE IF NOT EXISTS `connection_request` (
	`from_user` INT UNSIGNED NOT NULL,
	`to_user` INT UNSIGNED NOT NULL,
	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	CONSTRAINT `connection_request_from_user_user_id_fk` 
    FOREIGN KEY (`from_user`) REFERENCES `user` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT `connection_request_to_user_user_id_fk` 
    FOREIGN KEY (`to_user`) REFERENCES `user` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE
)
COLLATE='latin1_swedish_ci';