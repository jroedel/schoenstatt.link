
ALTER TABLE `sch_publications` ADD `AuthorAssociationId1` INT NULL DEFAULT NULL AFTER `Authors`, ADD `AuthorAssociationId2` INT NULL DEFAULT NULL AFTER `AuthorAssociationId1`, ADD `AuthorAssociationId3` INT NULL DEFAULT NULL AFTER `AuthorAssociationId2`;

ALTER TABLE `sch_publications` ADD `PublisherAssociationId` INT NULL DEFAULT NULL AFTER `Publisher`;
ALTER TABLE `sch_publications` ADD `Editor` INT NULL DEFAULT NULL AFTER `Illustrator`;
