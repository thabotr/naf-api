CREATE TABLE `user` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`created_at` TIMESTAMP(6) NOT NULL DEFAULT current_timestamp(6),
	`handle` VARCHAR(255) NOT NULL COLLATE 'latin1_swedish_ci',
	`token` TINYTEXT NOT NULL COLLATE 'latin1_swedish_ci',
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `handle` (`handle`) USING BTREE
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
AUTO_INCREMENT=1551
;
