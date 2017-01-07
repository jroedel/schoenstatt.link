
CREATE TABLE `sch_visits` (
  `VisitId` int(11) NOT NULL,
  `Entity` varchar(50) NOT NULL,
  `EntityId` int(11) NOT NULL,
  `UserId` int(11) NOT NULL,
  `IpAddress` varchar(255) DEFAULT NULL,
  `VisitedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `sch_visits`
  ADD PRIMARY KEY (`VisitId`);

ALTER TABLE `sch_visits`
  MODIFY `VisitId` int(11) NOT NULL AUTO_INCREMENT;
  
  ALTER TABLE `sch_assignments` CHANGE `Personid` `PersonId` INT(11) NOT NULL;
  
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('user', 'pub_user', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_user', 'pub_institute', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_institute', 'pub_patres', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_user', 'pub_all', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_user', 'pub_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_moderator', 'pub_general_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_moderator', 'pub_institute_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_moderator', 'pub_patres_moderator', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('pub_general_moderator', 'pub_administrator', '0', '2017-01-07 00:00:00', '5');
  
  
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('user', 'lib_user', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('lib_user', 'lib_teo_viewer', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('lib_user', 'lib_sch_viewer', '0', '2017-01-07 00:00:00', '5');
  INSERT INTO `user_role` (`parent`, `role_id`, `is_default`, `create_datetime`, `create_by`) VALUES ('lib_sch_viewer', 'lib_administrator', '0', '2017-01-07 00:00:00', '5');