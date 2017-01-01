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