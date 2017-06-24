#submitted 2017-06-24
DELETE FROM lib_books WHERE ISNULL(`library_id`);
ALTER TABLE lib_books MODIFY COLUMN original_id varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;
ALTER TABLE lib_books MODIFY COLUMN library_id int(11) NOT NULL ;
ALTER TABLE lib_books ADD inactivation_reason varchar(255) NULL ;
ALTER TABLE lib_books ADD is_active BIT DEFAULT 1 NOT NULL ;
ALTER TABLE lib_books ADD CONSTRAINT lib_books_UN UNIQUE KEY (library_id,original_id) ;
