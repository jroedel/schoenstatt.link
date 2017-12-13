ALTER TABLE `lib_checkouts` ADD INDEX( `BookId`, `CheckedInOn`);
ALTER TABLE `lib_books` ADD INDEX( `collection_id`, `is_active`);