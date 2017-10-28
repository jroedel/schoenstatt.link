ALTER TABLE `sch_publications` CHANGE `Editor` `EditorId` INT(11) NULL DEFAULT NULL;
ALTER TABLE `sch_publications` ADD `Editor` VARCHAR(50) NULL DEFAULT NULL AFTER `Illustrator`, ADD `IllustratorId` INT NULL DEFAULT NULL AFTER `Editor`, ADD `TranslatorId` INT NULL DEFAULT NULL AFTER `IllustratorId`;
ALTER TABLE `sch_publications` ADD `HasNoExplictEditionNumber` BIT(1) NOT NULL DEFAULT b'0' AFTER `TranslatedFromPublicationId`, ADD `EditionNotes` TEXT NULL DEFAULT NULL AFTER `HasNoExplictEditionNumber`, ADD `CategoryId` INT NULL DEFAULT NULL AFTER `EditionNotes`;

CREATE TABLE `sch_pub_categories` (
  `PublicationCategoryId` int(11) NOT NULL,
  `ParentId` int(11) DEFAULT NULL,
  `CategoryName` varchar(50) NOT NULL,
  `SortOrder` int(11) NOT NULL DEFAULT '100',
  `IsPlaceholder` bit(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `sch_pub_categories` ADD PRIMARY KEY(`PublicationCategoryId`);
ALTER TABLE `sch_pub_categories` CHANGE `PublicationCategoryId` `PublicationCategoryId` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sch_pub_categories` CHANGE `IsPlaceholder` `IsPlaceholder` BIT(1) NULL DEFAULT b'0';
INSERT INTO `sch_pub_categories` (`PublicationCategoryId`, `ParentId`, `CategoryName`, `SortOrder`, `IsPlaceholder`) VALUES (NULL, NULL, 'Fr. Kentenich', '1', b'1');
INSERT INTO `sch_pub_categories` (`PublicationCategoryId`, `ParentId`, `CategoryName`, `SortOrder`, `IsPlaceholder`) VALUES (NULL, '1', 'Pre-Schoenstatt (1899-1912)', '10', NULL), (NULL, '1', 'Founding Era (1912-1919)', '11', NULL), (NULL, '1', 'The Growing Movement (1920-1941)', '12', NULL), (NULL, '1', 'Koblenz and Dachau (1941-45)', '13', NULL), (NULL, '1', 'Internationalization and Confrontation (1945-51)', '14', NULL), (NULL, '1', 'The Exile (1952-65)', '15', NULL), (NULL, '1', 'The Final Years (1965-68)', '16', NULL), (NULL, NULL, 'Biographies', '20', NULL), (NULL, NULL, 'Schoenstatt History', '30', NULL), (NULL, NULL, 'Schoenstatt Movement', '40', NULL), (NULL, NULL, 'Mary & Mariology', '50', NULL);

ALTER TABLE `sch_publications` ADD `AuthorPerson4` INT NULL DEFAULT NULL AFTER `AuthorPerson3`, ADD `AuthorPerson5` INT NULL DEFAULT NULL AFTER `AuthorPerson4`;
ALTER TABLE `sch_associations` ADD `IsAuthor` BIT(1) NOT NULL DEFAULT b'0' COMMENT 'Lets association appear in author lists' AFTER `OverrideNameFormat`;
