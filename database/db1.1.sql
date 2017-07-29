# submitted 2017-07-23
CREATE TABLE lib_imports (
	ImportId INT NOT NULL AUTO_INCREMENT,
	ImportName varchar(100) NOT NULL,
	LibraryId INT NOT NULL,
	Description varchar(1000) DEFAULT NULL NULL,
	Status varchar(100) DEFAULT 'pending' NOT NULL,
	ColumnMapping varchar(2000) DEFAULT NULL NULL,
	Worksheet varchar(255) DEFAULT NULL NULL,
	FilePath varchar(1000) NOT NULL,
	IsCompleteImport tinyint(1) DEFAULT 0 NOT NULL,
	BooksCreated int(11) DEFAULT NULL NULL,
	BooksUpdated int(11) DEFAULT NULL NULL,
	BooksDeleted int(11) DEFAULT NULL NULL,
	UpdatedOn DATETIME DEFAULT NULL NULL,
	UpdatedBy INT DEFAULT NULL NULL,
	CreatedOn DATETIME DEFAULT NULL NULL,
	CreatedBy INT DEFAULT NULL NULL,
    PRIMARY KEY (ImportId),
	CONSTRAINT lib_imports_lib_libraries_FK FOREIGN KEY (LibraryId) REFERENCES lib_libraries(LibraryId) ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_general_ci ;
