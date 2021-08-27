# submitted 2017-01-01
ALTER TABLE `sch_persons`
  DROP `DeaconDate`,
  DROP `PriestDate`,
  DROP `BishopDate`,
  DROP `LeaveDate`,
  DROP `BirthCity`;
 ALTER TABLE `sch_persons` ADD `PostStreet1` VARCHAR(200) NULL DEFAULT NULL AFTER `SlackUser`, ADD `PostStreet2` VARCHAR(200) NULL DEFAULT NULL AFTER `PostStreet1`, ADD `PostCityState` VARCHAR(70) NULL DEFAULT NULL AFTER `PostStreet2`, ADD `PostZip` VARCHAR(20) NULL DEFAULT NULL AFTER `PostCityState`, ADD `PostCountry` VARCHAR(3) NULL DEFAULT NULL AFTER `PostZip`;
ALTER TABLE `sch_associations` ADD `Post1Street1` VARCHAR(200) NULL DEFAULT NULL AFTER `InstagramUser`, ADD `Post1Street2` VARCHAR(200) NULL DEFAULT NULL AFTER `Post1Street1`, ADD `Post1CityState` VARCHAR(70) NULL DEFAULT NULL AFTER `Post1Street2`, ADD `Post1Zip` VARCHAR(20) NULL DEFAULT NULL AFTER `Post1CityState`, ADD `Post1Country` VARCHAR(3) NULL DEFAULT NULL AFTER `Post1Zip`, ADD `Post2Street1` VARCHAR(200) NULL DEFAULT NULL AFTER `Post1Country`, ADD `Post2Street2` VARCHAR(200) NULL DEFAULT NULL AFTER `Post2Street1`, ADD `Post2CityState` VARCHAR(70) NULL DEFAULT NULL AFTER `Post2Street2`, ADD `Post2Zip` VARCHAR(20) NULL DEFAULT NULL AFTER `Post2CityState`, ADD `Post2Country` VARCHAR(3) NULL DEFAULT NULL AFTER `Post2Zip`;
 ALTER TABLE `sch_persons`
  DROP `PrivateInfoUpdatedOn`,
  DROP `PrivateInfoUpdatedBy`;
  
  ALTER TABLE `sch_persons` ADD `LifeCommunity` VARCHAR(50) NULL DEFAULT NULL AFTER `ReligiousStatus`;

  ALTER TABLE `sch_persons` ADD `DataSource` VARCHAR(50) NULL DEFAULT NULL AFTER `ContactInfoUpdatedBy`, ADD `DataSourceId` INT NULL DEFAULT NULL AFTER `DataSource`;
  ALTER TABLE `sch_persons` ADD `DataSourceUpdatedOn` DATETIME NULL DEFAULT NULL AFTER `DataSourceId`;
DROP TABLE `sch_roles`; 
 CREATE TABLE `sch_roles` ( `RoleId` INT NOT NULL AUTO_INCREMENT , `RoleTitle` VARCHAR(100) NOT NULL , `AssociationId` INT NOT NULL , `IsMainRole` BOOLEAN NOT NULL DEFAULT FALSE , `IsSinglePosition` BOOLEAN NOT NULL DEFAULT FALSE , `Sort` INT NOT NULL DEFAULT '500' , `IsActive` BOOLEAN NOT NULL DEFAULT TRUE , `UpdatedOn` DATETIME NULL DEFAULT NULL , `UpdatedBy` INT NULL DEFAULT NULL , `CreatedOn` DATETIME NULL DEFAULT NULL , `CreatedBy` INT NULL DEFAULT NULL , PRIMARY KEY (`RoleId`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
 
 
CREATE TABLE `sch_assignments` (
  `AssignmentId` int(11) NOT NULL,
  `RoleId` int(11) NOT NULL,
  `Personid` int(11) NOT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `CreatedOn` datetime DEFAULT NULL,
  `CreatedBy` int(4) DEFAULT NULL,
  `UpdatedOn` datetime DEFAULT NULL,
  `UpdatedBy` int(4) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `a_data_role_assignment`
--
ALTER TABLE `sch_assignments`
  ADD PRIMARY KEY (`AssignmentId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `a_data_role_assignment`
--
ALTER TABLE `sch_assignments`
  MODIFY `AssignmentId` int(11) NOT NULL AUTO_INCREMENT;