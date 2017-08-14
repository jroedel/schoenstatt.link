#submitted 2017-08-14
ALTER TABLE `sch_persons` ADD `SpousePersonId` INT NULL DEFAULT NULL AFTER `CreatedBy`, ADD `PriestDate` DATE NULL DEFAULT NULL AFTER `SpousePersonId`, ADD `BishopDate` DATE NULL DEFAULT NULL AFTER `PriestDate`, ADD `PrimaryLocale` VARCHAR(10) NULL DEFAULT NULL AFTER `BishopDate`, ADD `IsAuthor` BIT(1) NOT NULL DEFAULT b'0' AFTER `PrimaryLocale`, ADD `IsBorrower` BIT(1) NOT NULL DEFAULT b'0' AFTER `IsAuthor`;
ALTER TABLE `lib_libraries` ADD `EnableCheckouts` BIT(1) NOT NULL DEFAULT b'0' AFTER `DefaultCheckoutTimePeriodInDays`, ADD `IsPublicallyListed` BIT(1) NOT NULL DEFAULT b'0' AFTER `EnableCheckouts`, ADD `CheckoutPersonListKind` VARCHAR(50) NOT NULL DEFAULT 'all-borrowers' AFTER `IsPublicallyListed`;
ALTER TABLE `lib_books` ADD `new_call_number` VARCHAR(50) NULL DEFAULT NULL AFTER `original_id`, ADD `is_selected` BIT(1) NOT NULL DEFAULT b'0' AFTER `new_call_number`;
ALTER TABLE `lib_collections` CHANGE `ReferenceRegex` `CallNumberRegex` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `lib_collections` ADD `CallNumberHelpText` VARCHAR(255) NULL DEFAULT NULL AFTER `CallNumberRegex`, ADD `CallNumberExplanation` VARCHAR(1000) NULL DEFAULT NULL AFTER `CallNumberHelpText`, ADD `MainShowDisplay` VARCHAR(50) NOT NULL DEFAULT 'show-categories' AFTER `CallNumberExplanation`;

