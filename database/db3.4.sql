#submitted 2018-11-11
ALTER TABLE `sch_associations` ADD `SchemaOrgJsonMd5` VARCHAR(32) NULL DEFAULT NULL AFTER `HistoryFr`;
ALTER TABLE `sch_associations` ADD `InternalName` VARCHAR(200) NULL DEFAULT NULL AFTER `AssociationName`, ADD `IsInternalNameTranslateable` TINYINT(1) NOT NULL DEFAULT '0' AFTER `InternalName`;

