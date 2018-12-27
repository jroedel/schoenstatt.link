CREATE TABLE `ourlink_db1`.`sch_dictionary_entries` ( `EntryId` INT NOT NULL AUTO_INCREMENT , `KeyDe` VARCHAR(255) NOT NULL , `Locale` VARCHAR(6) NOT NULL , `DirectTranslation` INT NOT NULL , `Entry` VARCHAR(1000) NULL DEFAULT NULL , PRIMARY KEY (`EntryId`)) ENGINE = InnoDB CHARSET=utf8 COLLATE utf8_general_ci;
ALTER TABLE `sch_dictionary_entries` CHANGE `DirectTranslation` `DirectTranslation` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `sch_dictionary_entries` ADD `UpdatedOn` DATETIME NULL DEFAULT NULL AFTER `Entry`, ADD `UpdatedBy` INT NULL DEFAULT NULL AFTER `UpdatedOn`, ADD `CreatedOn` DATETIME NULL DEFAULT NULL AFTER `UpdatedBy`, ADD `CreatedBy` INT NULL DEFAULT NULL AFTER `CreatedOn`;
ALTER TABLE `sch_dictionary_entries` ADD `Links` VARCHAR(1000) NULL DEFAULT NULL AFTER `Entry`;
ALTER TABLE `sch_dictionary_entries` ADD `Slug` VARCHAR(255) NOT NULL AFTER `KeyDe`;
ALTER TABLE `sch_dictionary_entries` ADD UNIQUE( `Slug`, `Locale`);
INSERT INTO `sch_dictionary_entries` (`EntryId`, `KeyDe`, `Slug`, `Locale`, `DirectTranslation`, `Entry`, `Links`, `UpdatedOn`, `UpdatedBy`, `CreatedOn`, `CreatedBy`) VALUES (NULL, 'Abbild', 'abbild', 'es_ES', NULL, 'trasunto; reflejo; réplica; impronta; copia; representación; retrato; imagen', 'bild', NULL, NULL, NULL, NULL)

