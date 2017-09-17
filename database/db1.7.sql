
CREATE TABLE `files` ( `StoreFileName` VARCHAR(256) NOT NULL , `OriginalFileName` VARCHAR(256) NOT NULL , `FileKind` VARCHAR(50) NULL DEFAULT NULL , `Description` VARCHAR(500) NULL DEFAULT NULL , `Size` INT NOT NULL , `Sha1` VARCHAR(40) NOT NULL , `ContentTags` VARCHAR(256) NULL DEFAULT NULL , `StructureTags` VARCHAR(256) NULL DEFAULT NULL , `MimeType` VARCHAR(256) NULL DEFAULT NULL , `IsPublic` BIT(1) NOT NULL DEFAULT b'0' , `IsEncrypted` BIT(1) NOT NULL DEFAULT b'0' , `EncryptedEncryptionKey` VARCHAR(256) NULL DEFAULT NULL , `UpdatedOn` DATETIME NULL DEFAULT NULL , `UpdatedBy` INT NULL DEFAULT NULL , `CreatedOn` DATETIME NULL DEFAULT NULL , `CreatedBy` INT NULL DEFAULT NULL ) ENGINE = InnoDB;
ALTER TABLE `files` ADD `FileId` INT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`FileId`);
UPDATE `user_role` SET `parent` = 'pub_patres_moderator' WHERE `user_role`.`role_id` = 'pub_general_moderator';
UPDATE `user_role` SET `parent` = 'pub_institute_moderator' WHERE `user_role`.`role_id` = 'pub_patres_moderator';
UPDATE `sch_publications` SET ResourceId = 'publication_public';
UPDATE `sch_publications` SET ResourceId = 'publication_patres' WHERE (IsInternalForPatres = 1);
ALTER TABLE `sch_publications` CHANGE `IsInternalForPatres` `IsInternalForPatres` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'DEPRECATED';
ALTER TABLE `sch_visits` CHANGE `UserId` `UserId` INT(11) NULL;
