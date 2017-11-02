#submitted 2017-10-31
ALTER TABLE `sch_publications` ADD `Translator2Id` INT NULL DEFAULT NULL AFTER `TranslatorId`, ADD `Translator3Id` INT NULL DEFAULT NULL AFTER `Translator2Id`;
ALTER TABLE `sch_publications` ADD `Editor2Id` INT NULL DEFAULT NULL AFTER `EditorId`, ADD `Editor3Id` INT NULL DEFAULT NULL AFTER `Editor2Id`;
ALTER TABLE `sch_publications` ADD `EditorAssociationId1` INT NULL DEFAULT NULL AFTER `Editor3Id`;