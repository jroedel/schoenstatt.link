#submitted 2017-08-14
ALTER TABLE `lib_collections` ADD `LabelLine1` VARCHAR(255) NULL DEFAULT NULL AFTER `MainShowDisplay`, ADD `LabelLine2` VARCHAR(255) NULL DEFAULT NULL AFTER `LabelLine1`, ADD `LabelLine3` VARCHAR(255) NULL DEFAULT NULL AFTER `LabelLine2`, ADD `DefaultCheckoutTimePeriodInDays` INT NULL DEFAULT NULL AFTER `LabelLine3`, ADD `EnforceCallNumberRegex` BIT(1) NULL DEFAULT NULL AFTER `DefaultCheckoutTimePeriodInDays`, ADD `RequireCallNumbers` BIT(1) NULL DEFAULT NULL AFTER `EnforceCallNumberRegex`;
ALTER TABLE `lib_collections`
  DROP `PublicNotes`,
  DROP `PublicNotesUpdatedOn`,
  DROP `PublicNotesUpdatedBy`;
ALTER TABLE `lib_collections` ADD `CreatedOn` DATETIME NULL DEFAULT NULL AFTER `UpdatedBy`, ADD `CreatedBy` INT NULL DEFAULT NULL AFTER `CreatedOn`;
ALTER TABLE `lib_libraries` ADD `IsActive` BIT(1) NOT NULL DEFAULT b'1' AFTER `CheckoutPersonListKind`, ADD `AdminNotes` VARCHAR(1000) NULL DEFAULT NULL AFTER `IsActive`, ADD `AdminNotesUpdatedOn` DATETIME NULL DEFAULT NULL AFTER `AdminNotes`, ADD `AdminNotesUpdatedBy` INT NULL DEFAULT NULL AFTER `AdminNotesUpdatedOn`;
