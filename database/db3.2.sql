#submitted 2018-10-18
INSERT INTO `sch_pub_categories` (`PublicationCategoryId`, `ParentId`, `CategoryName`, `SortOrder`, `IsPlaceholder`) VALUES (NULL, NULL, 'Compilation texts', '17', b'0'), (NULL, NULL, 'Spirituality', '60', b'0'), (NULL, NULL, 'Education', '70', b'0'), (NULL, NULL, 'Prayer', '80', b'0');


CREATE TABLE `relationships` (
	`RelationshipId` INT NOT NULL AUTO_INCREMENT,
	`SubjectEntityId` INT NOT NULL,
	`ObjectEntityId` INT NOT NULL,
	`PredicateKind` varchar(50) NOT NULL,
	`Priority` INT NOT NULL DEFAULT '100',
	`PublicNotes` varchar(500) DEFAULT NULL,
	`AdminNotes` varchar(500) DEFAULT NULL,
	`PublicNotesUpdatedOn` DATETIME DEFAULT NULL,
	`PublicNotesUpdatedBy` INT DEFAULT NULL,
	`AdminNotesUpdatedOn` DATETIME DEFAULT NULL,
	`AdminNotesUpdatedBy` INT DEFAULT NULL,
	`UpdatedOn` DATETIME DEFAULT NULL,
	`UpdatedBy` INT DEFAULT NULL,
	PRIMARY KEY (`RelationshipId`)
);

CREATE TABLE `predicates` (
	`PredicateKind` varchar(50) NOT NULL,
	`SubjectEntityKind` varchar(50) NOT NULL,
	`ObjectEntityKind` varchar(50) NOT NULL,
	`PredicateText` varchar(256) DEFAULT NULL,
	`DescriptionEn` varchar(256) DEFAULT NULL,
	`DescriptionEs` varchar(256) DEFAULT NULL,
	`DescriptionDe` varchar(256) DEFAULT NULL,
	`DescriptionPt` varchar(256) DEFAULT NULL,
	`DescriptionFr` varchar(256) DEFAULT NULL,
	PRIMARY KEY (`PredicateKind`)
);

CREATE TABLE `comments` (
	`CommentId` INT NOT NULL AUTO_INCREMENT,
	`Rating` INT(2) DEFAULT NULL,
	`CommentKind` varchar(50) NOT NULL,
	`Comment` varchar(500) NOT NULL,
	`Status` varchar(50) NOT NULL,
	`ReviewedBy` INT DEFAULT NULL,
	`ReviewedOn` DATETIME DEFAULT NULL,
	`CreatedOn` DATETIME DEFAULT NULL,
	`CreatedBy` INT DEFAULT NULL,
	PRIMARY KEY (`CommentId`)
);

ALTER TABLE `relationships` ADD CONSTRAINT `relationships_fk0` FOREIGN KEY (`PredicateKind`) REFERENCES `predicates`(`PredicateKind`);

