#submitted 2017-12-06

UPDATE `lib_books` SET lang=NULL WHERE lang='';
UPDATE `lib_books` SET lang='en' WHERE lang='eng';
UPDATE `lib_books` SET lang='es|de|en' WHERE lang='es;de;en';
UPDATE `lib_books` SET lang=NULL WHERE lang='xx';
UPDATE `lib_books` SET lang='es' WHERE lang='ceb';
UPDATE `lib_books` SET lang='de' WHERE lang='bs';
UPDATE `lib_books` SET `author` = REPLACE(`author`, '; ', '|') WHERE (`author` LIKE '%; %');
UPDATE `lib_books` SET `author` = REPLACE(`author`, ';', '|') WHERE (`author` LIKE '%;%');

ALTER TABLE `lib_libraries` ADD `CallNumberPlaceholder` VARCHAR(50) NULL DEFAULT NULL AFTER `Description`;