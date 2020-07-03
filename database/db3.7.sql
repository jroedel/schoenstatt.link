#SUBMITTED 2018-12-12
ALTER TABLE `sch_associations` CHANGE `Country` `Country` VARCHAR(6) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
CREATE TABLE IF NOT EXISTS `csp_reports` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `full_report` mediumtext NOT NULL,
 `document_uri` mediumtext NOT NULL,
 `referrer` mediumtext NOT NULL,
 `violated_directive` mediumtext NOT NULL,
 `original_policy` mediumtext NOT NULL,
 `blocked_uri` mediumtext NOT NULL,
 `source_file` mediumtext NOT NULL,
 `line_number` mediumtext NOT NULL,
 `column_number` mediumtext NOT NULL,
 `status_code` mediumtext NOT NULL,
 PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
