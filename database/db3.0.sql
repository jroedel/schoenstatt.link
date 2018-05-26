RENAME TABLE `ourlink_db1`.`user_role` TO `ourlink_db1`.`user_role_temp`;
RENAME TABLE `ourlink_db1`.`user_role_linker` TO `ourlink_db1`.`user_role_linker_temp`;
ALTER TABLE `user_role_temp` ADD `id` INT UNSIGNED NOT NULL AUTO_INCREMENT AFTER `create_by`, ADD UNIQUE (`id`);

CREATE  TABLE IF NOT EXISTS `user_role` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_id` VARCHAR(255) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `parent_id` INT(11) NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `unique_role` (`role_id` ASC),
  INDEX `idx_parent_id` (`parent_id` ASC),
  CONSTRAINT `fk_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `user_role` (`id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE  TABLE IF NOT EXISTS `user_role_linker` (
  `user_id` INT(11) NOT NULL,
  `role_id` INT(11) NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  INDEX `idx_role_id` (`role_id` ASC),
  INDEX `idx_user_id` (`user_id` ASC),
  CONSTRAINT `fk_role_id` FOREIGN KEY (`role_id`) REFERENCES `user_role` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

ALTER TABLE `user_role` ADD `create_by` INT(11) NULL DEFAULT NULL AFTER `parent_id`, ADD `create_datetime` DATETIME NULL DEFAULT NULL AFTER `create_by`;
ALTER TABLE `user_role_linker` ADD `create_by` INT(11) NULL DEFAULT NULL AFTER `role_id`, ADD `create_datetime` DATETIME NULL DEFAULT NULL AFTER `create_by`;

# without foreign key checks
INSERT INTO `user_role` (`id`, `is_default`, `role_id`, `parent_id`, `create_by`, `create_datetime`)
SELECT r.id, r.`is_default`, r.`role_id`, t.id AS parent_id, r.`create_by`, r.`create_datetime` FROM `user_role_temp` r LEFT JOIN `user_role_temp` t ON r.`parent` = t.`role_id` WHERE 1 ORDER BY t.id

INSERT INTO `user_role_linker` (`user_id`, `role_id`, `create_by`, `create_datetime`) SELECT l.`user_id`, r.id, l.`create_by`, l.`create_datetime` FROM `user_role_linker_temp` l INNER JOIN user_role r ON l.role_id = r.role_id WHERE 1 
