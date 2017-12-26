UPDATE `lib_imports` SET `Status` = 'completed'
WHERE (NOT(ISNULL(`BooksCreated`)) || NOT(ISNULL(`BooksUpdated`)) || NOT(ISNULL(`BooksDeleted`)));
