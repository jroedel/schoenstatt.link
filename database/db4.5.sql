
UPDATE `key_abbreviations_english` SET `p` = '1' WHERE `key_abbreviations_english`.`id` = 358;
UPDATE `key_abbreviations_english` SET `p` = '1' WHERE `key_abbreviations_english`.`id` = 359;
ALTER TABLE `key_abbreviations_english` CHANGE `a` `abbreviation` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `key_abbreviations_english` CHANGE `id` `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Abbreviation ID', CHANGE `b` `book_id` INT(11) NOT NULL COMMENT 'ID of book that is abbreviated', CHANGE `p` `is_preferred` BIT(1) NOT NULL DEFAULT b'0' COMMENT 'Whether an abbreviation is the primary one for the book';
ALTER TABLE `key_abbreviations_english` ADD `language` VARCHAR(5) NOT NULL DEFAULT 'en' AFTER `book_id`;
# rename here  key_abbreviations_english -> bib_book_abbreviations
ALTER TABLE `bib_book_abbreviations` DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;