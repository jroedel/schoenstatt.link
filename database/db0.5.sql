

ALTER TABLE `sch_persons` CHANGE `FirstName` `FirstName` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `FirstNameWithoutAccents` `FirstNameWithoutAccents` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `trans_phrases` ADD `origin_route` VARCHAR(255) NULL DEFAULT NULL AFTER `added_on`;
