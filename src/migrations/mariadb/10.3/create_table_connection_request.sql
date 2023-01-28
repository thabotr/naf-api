CREATE TABLE `connection_request` (
	`from_user` INT(10) UNSIGNED NOT NULL,
	`to_user` INT(10) UNSIGNED NOT NULL,
	`created_at` TIMESTAMP(6) NOT NULL DEFAULT current_timestamp(6),
	INDEX `connection_request_from_user_user_id_fk` (`from_user`) USING BTREE,
	INDEX `connection_request_to_user_user_id_fk` (`to_user`) USING BTREE,
	CONSTRAINT `connection_request_from_user_user_id_fk` FOREIGN KEY (`from_user`) REFERENCES `user` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT `connection_request_to_user_user_id_fk` FOREIGN KEY (`to_user`) REFERENCES `user` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
;
