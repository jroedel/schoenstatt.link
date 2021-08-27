DELETE FROM `user_role_linker` WHERE user_id IN (9,13,14,15,20);

DELETE FROM `user` WHERE `user`.`user_id` = 9;
DELETE FROM `user` WHERE `user`.`user_id` = 13;
DELETE FROM `user` WHERE `user`.`user_id` = 14;
DELETE FROM `user` WHERE `user`.`user_id` = 15;
DELETE FROM `user` WHERE `user`.`user_id` = 20;

DELETE FROM `user_role_linker` WHERE (`role_id` = 'patres_administrator') OR (`role_id` = 'patres_contact_moderator') OR (`role_id` = 'patres_course_moderator') OR (`role_id` = 'patres_mass_email') OR (`role_id` = 'patres_moderator_general') OR (`role_id` = 'patres_moderator_territory') OR (`role_id` = 'patres_moderator') OR (`role_id` = 'patres_poweruser') OR (`role_id` = 'patres_user') OR (`role_id` = 'patres_basic');

DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_administrator';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_contact_moderator';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_course_moderator';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_mass_email';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_moderator_general';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_moderator_territory';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_moderator';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_poweruser';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_user';
DELETE FROM `user_role` WHERE `user_role`.`role_id` = 'patres_basic';
