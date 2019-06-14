
ALTER TABLE `bib_greek_root_words` CHANGE `first_verse_ occurrence` `first_verse_occurrence` INT(8) UNSIGNED ZEROFILL NULL DEFAULT NULL;
ALTER TABLE `bib_greek_root_words` CHANGE `forms_available` `forms_available` VARCHAR(700) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'pipe-separated list of forms';
