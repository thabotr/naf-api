CREATE TABLE `connection` (
	`user_a` INT(10) UNSIGNED NOT NULL,
	`user_b` INT(10) UNSIGNED NOT NULL,
	`created_at` TIMESTAMP(6) NOT NULL DEFAULT current_timestamp(6),
	UNIQUE INDEX `user_a_user_b` (`user_a`, `user_b`) USING BTREE,
	INDEX `fk_connection_user_b` (`user_b`) USING BTREE,
	CONSTRAINT `fk_connection_user_a` FOREIGN KEY (`user_a`) REFERENCES `user` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE,
	CONSTRAINT `fk_connection_user_b` FOREIGN KEY (`user_b`) REFERENCES `user` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
;
