CREATE TABLE `texts` (
`TextId` INT NOT NULL AUTO_INCREMENT,
`TextKind` varchar(50) NOT NULL,
`Language` varchar(2) NOT NULL,
`MarkdownText` TEXT,
`HtmlText` TEXT,
`PlainText` TEXT,
`WordCount` INT DEFAULT NULL,
`JkTextQuality` varchar(10) DEFAULT NULL,
`Tags` varchar(255) DEFAULT NULL,
`AdminTags` varchar(255) DEFAULT NULL,
`AclResourceId` varchar(50) NOT NULL DEFAULT 'txt_public',
`PublicNotes` varchar(1000) DEFAULT NULL,
`PublicNotesUpdatedBy` INT DEFAULT NULL,
`PublicNotesUpdatedOn` DATETIME DEFAULT NULL,
`AdminNotes` varchar(1000) DEFAULT NULL,
`AdminNotesUpdatedBy` INT DEFAULT NULL,
`AdminNotesUpdatedOn` DATETIME DEFAULT NULL,
`LegacyEventId` INT DEFAULT NULL,
`LegacyFile` varchar(300) DEFAULT NULL,
`LegacyPathDate` DATE DEFAULT NULL,
`LegacyFileDateModified` DATE DEFAULT NULL,
`UpdatedOn` DATETIME DEFAULT NULL,
`UpdatedBy` INT DEFAULT NULL,
`CreatedOn` DATETIME DEFAULT NULL,
`CreatedBy` INT DEFAULT NULL,
PRIMARY KEY (`TextId`)
);
ALTER TABLE `texts` ADD `Title` VARCHAR(255) NOT NULL AFTER `TextId`;
ALTER TABLE `texts` DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;
ALTER TABLE `texts` ADD `Slug` VARCHAR(50) NOT NULL AFTER `Language`, ADD `IsDraft` TINYINT(0) NULL AFTER `Slug`;
ALTER TABLE `texts` ADD UNIQUE( `Slug`);
#update char sets
ALTER TABLE `texts` CHANGE `Title` `Title` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `TextKind` `TextKind` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `Language` `Language` VARCHAR(2) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `MarkdownText` `MarkdownText` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `HtmlText` `HtmlText` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `PlainText` `PlainText` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `JkTextQuality` `JkTextQuality` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Tags` `Tags` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `AdminTags` `AdminTags` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `AclResourceId` `AclResourceId` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'txt_public', CHANGE `PublicNotes` `PublicNotes` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `AdminNotes` `AdminNotes` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `LegacyFile` `LegacyFile` VARCHAR(300) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
INSERT INTO `user_role` (`id`, `role_id`, `is_default`, `parent_id`, `create_by`, `create_datetime`) VALUES (NULL, 'blog_contributor', '0', NULL, NULL, NULL);
INSERT INTO `user_role` (`id`, `role_id`, `is_default`, `parent_id`, `create_by`, `create_datetime`) VALUES (NULL, 'blog_administrator', '0', '38', NULL, NULL);

