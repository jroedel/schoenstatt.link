#submitted 2017-1-14

ALTER TABLE `sch_persons` ADD `PersonTags` VARCHAR(255) NULL DEFAULT NULL AFTER `ReligiousStatus`;
ALTER TABLE `sch_persons` CHANGE `LastName` `LastName` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `LastNameWithoutAccents` `LastNameWithoutAccents` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ReligiousStatus` `ReligiousStatus` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE `sch_persons` DROP `ReligiousStatus`;
ALTER TABLE `sch_persons` DROP `Nationalities`;