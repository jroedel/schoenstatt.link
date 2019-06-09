#submitted 2019-06-08
ALTER TABLE `bib_verses` DROP `book_old_id`;
ALTER TABLE `bib_verses` CHANGE `translation_id` `translation_id` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `bib_verses` CHANGE `verse` `verse` SMALLINT NOT NULL;
ALTER TABLE `bib_verses` CHANGE `chapter` `chapter` SMALLINT NOT NULL;
INSERT INTO bib_verses
(translation_id, verse_id, book_id, chapter, verse, text)
SELECT 'jeresp' AS translation_id, CONCAT(LPAD(b.book_id, 2, '0'), LPAD(j.chapter, 3, '0'), LPAD(j.verse, 3, '0')) AS verse_id, b.book_id, j.chapter, j.verse, j.text FROM `bib_jeru_es_temp` j INNER JOIN `bib_books` b ON b.`alt_id_1` = j.`book_id`;
