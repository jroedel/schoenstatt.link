#submitted 2019-07-19
ALTER TABLE `user_role_linker` ADD `id` INT NOT NULL AUTO_INCREMENT FIRST;
ALTER TABLE `user_role_linker` DROP PRIMARY KEY;
ALTER TABLE `user_role_linker` ADD PRIMARY KEY(`id`);
ALTER TABLE `user_role_linker` ADD UNIQUE( `user_id`, `role_id`);