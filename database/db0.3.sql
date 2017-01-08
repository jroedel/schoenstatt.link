
CREATE TABLE `sch_visits` (
  `VisitId` int(11) NOT NULL,
  `Entity` varchar(50) NOT NULL,
  `EntityId` int(11) NOT NULL,
  `UserId` int(11) NOT NULL,
  `IpAddress` varchar(255) DEFAULT NULL,
  `VisitedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `sch_visits`
  ADD PRIMARY KEY (`VisitId`);

ALTER TABLE `sch_visits`
  MODIFY `VisitId` int(11) NOT NULL AUTO_INCREMENT;
  
  ALTER TABLE `sch_assignments` CHANGE `Personid` `PersonId` INT(11) NOT NULL;
  
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('user', 'pub_user', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_user', 'pub_institute', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_institute', 'pub_patres', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_user', 'pub_all', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_user', 'pub_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_moderator', 'pub_general_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_moderator', 'pub_institute_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_moderator', 'pub_patres_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_general_moderator', 'pub_administrator', '0', '2017-01-07 00:00:00', '5');
  
  
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('user', 'lib_user', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('lib_user', 'lib_teo_viewer', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('lib_user', 'lib_sch_viewer', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('lib_sch_viewer', 'lib_administrator', '0', '2017-01-07 00:00:00', '5');
  CREATE TABLE `sch_publications` ( `PublicationId` INT NOT NULL AUTO_INCREMENT , `Title` VARCHAR(300) NOT NULL , `ResourceId` VARCHAR(50) NULL DEFAULT NULL , `AuthorPerson1` INT NULL DEFAULT NULL , `AuthorPerson2` INT NULL DEFAULT NULL , `AuthorPerson3` INT NULL DEFAULT NULL , `Authors` VARCHAR(500) NULL DEFAULT NULL , `BookEdition` VARCHAR(50) NULL DEFAULT NULL , `InLanguage` VARCHAR(3) NULL DEFAULT NULL , `Description` TEXT NULL DEFAULT NULL , `Isbn` VARCHAR(30) NULL DEFAULT NULL , `Translator` VARCHAR(50) NULL DEFAULT NULL , `Illustrator` VARCHAR(50) NULL DEFAULT NULL , `NumberOfPages` INT NULL DEFAULT NULL , `CopyrightYear` INT NULL DEFAULT NULL , `Publisher` VARCHAR(255) NULL DEFAULT NULL , `PublishingPlace` VARCHAR(255) NULL DEFAULT NULL , `DatePublished` DATE NULL DEFAULT NULL , `PublishingStatus` VARCHAR(50) NULL DEFAULT NULL , `BookFormatType` VARCHAR(50) NULL DEFAULT NULL , `MainPublicationId` INT NULL DEFAULT NULL , `VolumeNumber` VARCHAR(25) NULL DEFAULT NULL , `ContainedIn` VARCHAR(255) NULL DEFAULT NULL , `ContainedInIsbn` VARCHAR(30) NULL DEFAULT NULL , `Genre` VARCHAR(50) NULL DEFAULT NULL , `PublicTags` VARCHAR(255) NULL DEFAULT NULL , `AdminTags` VARCHAR(255) NULL DEFAULT NULL , `IsAccessableForFree` BOOLEAN NOT NULL DEFAULT FALSE , `IsInternalForPatres` BOOLEAN NOT NULL DEFAULT FALSE , `IsScientificWork` BOOLEAN NOT NULL DEFAULT FALSE , `IsAwaitingMerge` BOOLEAN NOT NULL DEFAULT FALSE , `HasBeenMerged` BOOLEAN NOT NULL DEFAULT FALSE , `JkQuality` VARCHAR(20) NULL DEFAULT NULL , `JkQualityNotes` VARCHAR(255) NULL DEFAULT NULL , `JkPeriod` INT NULL DEFAULT NULL , `JkEventId` INT NULL DEFAULT NULL , `Url1` VARCHAR(1000) NULL DEFAULT NULL , `Url1Label` VARCHAR(50) NULL DEFAULT NULL , `Url2` VARCHAR(1000) NULL DEFAULT NULL , `Url2Label` VARCHAR(50) NULL DEFAULT NULL , `Url3` VARCHAR(1000) NULL DEFAULT NULL , `Url3Label` VARCHAR(50) NULL DEFAULT NULL , `DataSource` VARCHAR(50) NULL DEFAULT NULL , `DataSourceId` INT NULL DEFAULT NULL , `DataSourceUpdatedOn` DATETIME NULL DEFAULT NULL , `PublicNotes` TEXT NULL DEFAULT NULL , `PublicNotesUpdatedOn` DATETIME NULL DEFAULT NULL , `PublicNotesUpdatedBy` INT NULL DEFAULT NULL , `AdminNotes` TEXT NULL DEFAULT NULL , `AdminNotesUpdatedOn` DATETIME NULL DEFAULT NULL , `AdminNotesUpdatedBy` INT NULL DEFAULT NULL , `UpdatedOn` DATETIME NULL DEFAULT NULL , `UpdatedBy` INT NULL DEFAULT NULL , `CreatedOn` DATETIME NULL DEFAULT NULL , `CreatedBy` INT NULL DEFAULT NULL , PRIMARY KEY (`PublicationId`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
  
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('user', 'sch_basic', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('user', 'sch_user', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('sch_user', 'sch_patres', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('sch_user', 'sch_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('sch_moderator', 'sch_general_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('sch_general_moderator', 'sch_administrator', '0', '2017-01-07 00:00:00', '5');
  