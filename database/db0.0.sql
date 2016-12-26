
CREATE TABLE `sch_roles` (
  `RoleId` int(11) NOT NULL,
  `RoleTitle` varchar(50) NOT NULL,
  `AssociationId` int(11) NOT NULL,
  `MainRole` tinyint(1) NOT NULL DEFAULT '0',
  `SinglePosition` tinyint(1) NOT NULL DEFAULT '0',
  `Sort` smallint(4) NOT NULL DEFAULT '1000',
  `Active` tinyint(1) NOT NULL DEFAULT '1',
  `UpdatedOn` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `CreatedOn` datetime DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `a_data_role`
--
ALTER TABLE `sch_roles`
  ADD PRIMARY KEY (`RoleId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `a_data_role`
--
ALTER TABLE `sch_roles`
  MODIFY `RoleId` int(11) NOT NULL AUTO_INCREMENT;
  
  CREATE TABLE `jk`.`sch_persons` ( `PersonId` INT NOT NULL AUTO_INCREMENT , `LastName` VARCHAR(100) NOT NULL , `FirstName` VARCHAR(100) NOT NULL , `LastNameWithoutAccents` VARCHAR(100) NOT NULL , `FirstNameWithoutAccents` VARCHAR(100) NOT NULL , `ReligiousStatus` VARCHAR(50) NULL DEFAULT 'Lay' , `Title` VARCHAR(10) NULL DEFAULT NULL , `TitleAutomatic` BOOLEAN NOT NULL DEFAULT TRUE , `Country` VARCHAR(3) NULL DEFAULT NULL , `BirthDate` DATE NULL DEFAULT NULL , `NameDay` DATE NULL DEFAULT NULL , `DeaconDate` DATE NULL DEFAULT NULL , `PriestDate` DATE NULL DEFAULT NULL , `BishopDate` DATE NULL DEFAULT NULL , `DeathDate` DATE NULL DEFAULT NULL , `LeaveDate` DATE NULL DEFAULT NULL , `PublicNotes` TEXT NULL DEFAULT NULL , `PublicNotesUpdatedOn` DATETIME NULL DEFAULT NULL , `PublicNotesUpdatedBy` INT NULL DEFAULT NULL , `PersonalInfoUpdatedOn` DATETIME NULL DEFAULT NULL , `PersonalInfoUpdatedBy` INT NULL DEFAULT NULL , PRIMARY KEY (`PersonId`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

  ALTER TABLE `sch_persons` ADD `AdminTags` VARCHAR(255) NULL DEFAULT NULL AFTER `PersonalInfoUpdatedBy`, ADD `BirthCity` VARCHAR(255) NULL DEFAULT NULL AFTER `AdminTags`, ADD `Nationalities` VARCHAR(50) NULL DEFAULT NULL AFTER `BirthCity`, ADD `AdminNotes` TEXT NULL DEFAULT NULL AFTER `Nationalities`, ADD `AdminNotesUpdatedOn` DATETIME NULL DEFAULT NULL AFTER `AdminNotes`, ADD `AdminNotesUpdatedBy` INT NULL DEFAULT NULL AFTER `AdminNotesUpdatedOn`, ADD `PrivateInfoUpdatedOn` DATETIME NULL DEFAULT NULL AFTER `AdminNotesUpdatedBy`, ADD `PrivateInfoUpdatedBy` INT NULL DEFAULT NULL AFTER `PrivateInfoUpdatedOn`, ADD `Email` VARCHAR(255) NULL DEFAULT NULL AFTER `PrivateInfoUpdatedBy`, ADD `Email2` VARCHAR(255) NULL DEFAULT NULL AFTER `Email`, ADD `EmailsUpdatedOn` DATETIME NULL DEFAULT NULL AFTER `Email2`, ADD `EmailsUpdatedBy` INT NULL DEFAULT NULL AFTER `EmailsUpdatedOn`, ADD `CellPhone` VARCHAR(70) NULL DEFAULT NULL AFTER `EmailsUpdatedBy`, ADD `CellPhoneHasWhatsApp` BOOLEAN NOT NULL DEFAULT FALSE AFTER `CellPhone`, ADD `Phone1` VARCHAR(70) NULL DEFAULT NULL AFTER `CellPhoneHasWhatsApp`, ADD `Phone1Label` VARCHAR(50) NULL DEFAULT NULL AFTER `Phone1`, ADD `Phone2` VARCHAR(70) NULL DEFAULT NULL AFTER `Phone1Label`, ADD `Phone2Label` VARCHAR(50) NULL DEFAULT NULL AFTER `Phone2`, ADD `Phone3` VARCHAR(70) NULL DEFAULT NULL AFTER `Phone2Label`, ADD `Phone3Label` VARCHAR(50) NULL DEFAULT NULL AFTER `Phone3`;
  ALTER TABLE `sch_persons` ADD `PhoneUpdatedOn` DATETIME NULL DEFAULT NULL AFTER `Phone3Label`, ADD `PhoneUpdatedBy` INT NULL DEFAULT NULL AFTER `PhoneUpdatedOn`, ADD `Url1` VARCHAR(1000) NULL DEFAULT NULL AFTER `PhoneUpdatedBy`, ADD `Url1Label` VARCHAR(50) NULL DEFAULT NULL AFTER `Url1`, ADD `Url2` VARCHAR(1000) NULL DEFAULT NULL AFTER `Url1Label`, ADD `Url2Label` VARCHAR(50) NULL DEFAULT NULL AFTER `Url2`, ADD `Url3` VARCHAR(1000) NULL DEFAULT NULL AFTER `Url2Label`, ADD `Url3Label` VARCHAR(50) NULL DEFAULT NULL AFTER `Url3`, ADD `FacebookUrl` VARCHAR(1000) NULL DEFAULT NULL AFTER `Url3Label`, ADD `SkypeUser` VARCHAR(50) NULL DEFAULT NULL AFTER `FacebookUrl`, ADD `TwitterUser` VARCHAR(50) NULL DEFAULT NULL AFTER `SkypeUser`, ADD `InstagramUser` VARCHAR(50) NULL DEFAULT NULL AFTER `TwitterUser`, ADD `SlackUser` VARCHAR(50) NULL DEFAULT NULL AFTER `InstagramUser`, ADD `ContactNotes` TEXT NULL DEFAULT NULL AFTER `SlackUser`, ADD `ContactInfoUpdatedOn` DATETIME NULL DEFAULT NULL AFTER `ContactNotes`, ADD `ContactInfoUpdatedBy` INT NULL DEFAULT NULL AFTER `ContactInfoUpdatedOn`, ADD `UpdatedOn` DATETIME NULL DEFAULT NULL AFTER `ContactInfoUpdatedBy`, ADD `UpdatedBy` INT NULL DEFAULT NULL AFTER `UpdatedOn`, ADD `CreatedOn` DATETIME NULL DEFAULT NULL AFTER `UpdatedBy`, ADD `CreatedBy` INT NULL DEFAULT NULL AFTER `CreatedOn`;
	ALTER TABLE `sch_persons` CHANGE `PhoneUpdatedOn` `PhonesUpdatedOn` DATETIME NULL DEFAULT NULL, CHANGE `PhoneUpdatedBy` `PhonesUpdatedBy` INT(11) NULL DEFAULT NULL;
	
CREATE TABLE `sch_changes` (
  `ChangeID` int(11) NOT NULL,
  `ChangedEntity` varchar(100) NOT NULL,
  `ChangedField` varchar(100) NOT NULL,
  `ChangedIDValue` int(11) NOT NULL,
  `NewValue` varchar(1000) DEFAULT NULL,
  `OldValue` varchar(1000) DEFAULT NULL,
  `UpdatedOn` datetime NOT NULL,
  `UpdatedBy` int(11) NOT NULL,
  `IpAddress` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `sch_changes`
  ADD PRIMARY KEY (`ChangeID`);

ALTER TABLE `sch_changes`
  MODIFY `ChangeID` int(11) NOT NULL AUTO_INCREMENT;