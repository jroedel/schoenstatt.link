ALTER TABLE `user` ADD `verification_token` VARCHAR(32) NULL DEFAULT NULL AFTER `PersID`;
ALTER TABLE `user` ADD `verification_expiration` DATETIME NULL DEFAULT NULL AFTER `verification_token`;

