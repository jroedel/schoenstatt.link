UPDATE `lib_books` SET lang=NULL WHERE lang='';
UPDATE `lib_books` SET lang='en' WHERE lang='eng';
UPDATE `lib_books` SET lang='es|de|en' WHERE lang='es;de;en';
UPDATE `lib_books` SET lang=NULL WHERE lang='xx';
UPDATE `lib_books` SET lang='es' WHERE lang='ceb';
UPDATE `lib_books` SET lang='de' WHERE lang='bs';
UPDATE `lib_books` SET `author` = REPLACE(`author`, '; ', '|') WHERE (`author` LIKE '%; %');
UPDATE `lib_books` SET `author` = REPLACE(`author`, ';', '|') WHERE (`author` LIKE '%;%');


CREATE TABLE `lib_categories` (
  `LibraryId` int(11) NOT NULL,
  `CategoryName` varchar(500) NOT NULL,
  `Abbreviation` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `lib_categories`
--
ALTER TABLE `lib_categories`
  ADD PRIMARY KEY (`LibraryId`,`CategoryName`);
