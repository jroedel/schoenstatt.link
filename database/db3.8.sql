#SUBMITTED 2018-12-15
ALTER TABLE `lib_collections` ADD `Abbreviation` VARCHAR(12) NOT NULL AFTER `CollectionName`;

UPDATE `lib_collections` SET `Abbreviation` = 'GEN' WHERE `lib_collections`.`CollectionId` = 1;
UPDATE `lib_collections` SET `Abbreviation` = 'PK' WHERE `lib_collections`.`CollectionId` = 2;
UPDATE `lib_collections` SET `Abbreviation` = 'ZDEP' WHERE `lib_collections`.`CollectionId` = 3;
ALTER TABLE `lib_collections` ADD UNIQUE( `LibraryId`, `Abbreviation`);
ALTER TABLE `lib_collections` ADD `SortTextFormat` VARCHAR(255) NULL DEFAULT NULL AFTER `Description`;
ALTER TABLE `lib_libraries` ADD `SortTextFormat` VARCHAR(255) NULL DEFAULT NULL AFTER `MainShowDisplay`;

