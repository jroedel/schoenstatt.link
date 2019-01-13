#submitted 2019-01-12
INSERT INTO `user_role` (`id`, `role_id`, `is_default`, `parent_id`, `create_by`, `create_datetime`) VALUES (NULL, 'dict_administrator', '0', NULL, '5', '2019-01-10 00:00:00');
ALTER TABLE `sch_visits` CHANGE `EntityId` `EntityId` INT(11) NULL DEFAULT NULL COMMENT 'If null, it refers to some entity index';
ALTER TABLE `sch_visits` ADD `UserAgent` VARCHAR(255) NULL DEFAULT NULL AFTER `IpAddress`;

