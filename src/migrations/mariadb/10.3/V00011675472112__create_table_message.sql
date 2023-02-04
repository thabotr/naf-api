CREATE TABLE `message` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`created_at` TIMESTAMP(6) NOT NULL DEFAULT current_timestamp(6),
	`text` TEXT NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
	`from_user` INT(11) UNSIGNED NOT NULL,
	`to_user` INT(11) UNSIGNED NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `fk_message_user_id_from` (`from_user`) USING BTREE,
	INDEX `fk_message_user_id_to` (`to_user`) USING BTREE,
	CONSTRAINT `fk_message_user_id_from` FOREIGN KEY (`from_user`) REFERENCES `user` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT `fk_message_user_id_to` FOREIGN KEY (`to_user`) REFERENCES `user` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
AUTO_INCREMENT=68
;
