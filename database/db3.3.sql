ALTER TABLE `predicates` DROP `DescriptionEs`, DROP `DescriptionDe`, DROP `DescriptionPt`, DROP `DescriptionFr`;
TRUNCATE `relationships`;
TRUNCATE `predicates`;
INSERT INTO `predicates`(`PredicateKind`, `SubjectEntityKind`, `ObjectEntityKind`, `PredicateText`, `DescriptionEn`) VALUES ('publication-publishes-text', 'publication', 'text', 'Publication publishes text', NULL), ('file-represents-text', 'file', 'text', 'File represents text', NULL), ('text-summarizes-text', 'text', 'text', 'Text summarizes text', NULL), ('text-cites-text', 'text', 'text', 'Text cites text', NULL), ('publication-cites-text', 'publication', 'text', 'Publication cites text', NULL), ('text-comments-text-(commentary)', 'text', '(commentary)', 'Text comments text (commentary)', NULL), ('text-represents-event', 'text', 'event', 'Text represents event', NULL), ('person-is-tagged-in-file-(media)', 'person', '(media)', 'Person is tagged in file (media)', NULL), ('association-is-tagged-in-file-(media)', 'association', '(media)', 'Association is tagged in file (media)', NULL), ('person-authored-text', 'person', 'text', 'Person authored text', NULL), ('association-authored-text', 'association', 'text', 'Association authored text', NULL), ('person-edited-text', 'person', 'text', 'Person edited text', NULL), ('association-edited-text', 'association', 'text', 'Association edited text', NULL), ('person-witnessed-event', 'person', 'event', 'Person witnessed event', NULL), ('association-witnessed-event', 'association', 'event', 'Association witnessed event', NULL), ('event-took-place-at-place', 'event', 'place', 'Event took place at place', NULL), ('event-took-place-at-association', 'event', 'association', 'Event took place at association', NULL), ('event-involves-association', 'event', 'association', 'Event involves association', NULL), ('event-involves-person', 'event', 'person', 'Event involves person', NULL), ('event-began-at-place', 'event', 'place', 'Event began at place', NULL), ('event-began-at-association', 'event', 'association', 'Event began at association', NULL), ('event-ended-at-place', 'event', 'place', 'Event ended at place', NULL), ('event-ended-at-association', 'event', 'association', 'Event ended at association', NULL), ('person-authored-publication', 'person', 'publication', 'Person authored publication', NULL), ('association-authored-publication', 'association', 'publication', 'Association authored publication', NULL), ('person-edited-publication', 'person', 'publication', 'Person edited publication', NULL), ('association-edited-publication', 'association', 'publication', 'Association edited publication', NULL), ('person-translated-publication', 'person', 'publication', 'Person translated publication', NULL), ('association-translated-publication', 'association', 'publication', 'Association translated publication', NULL), ('person-illustrated-publication', 'person', 'publication', 'Person illustrated publication', NULL), ('association-illustrated-publication', 'association', 'publication', 'Association illustrated publication', NULL), ('comment-reviews-publication', 'comment', 'publication', 'Comment reviews publication', NULL), ('comment-comments-text', 'comment', 'text', 'Comment comments text', NULL), ('comment-comments-event', 'comment', 'event', 'Comment comments event', NULL), ('comment-comments-file', 'comment', 'file', 'Comment comments file', NULL), ('user-watches-publication', 'user', 'publication', 'User watches publication', NULL), ('user-watches-text', 'user', 'text', 'User watches text', NULL), ('user-watches-event', 'user', 'event', 'User watches event', NULL), ('user-watches-file', 'user', 'file', 'User watches file', NULL), ('user-likes-publication', 'user', 'publication', 'User likes publication', NULL), ('user-likes-text', 'user', 'text', 'User likes text', NULL), ('user-likes-event', 'user', 'event', 'User likes event', NULL), ('user-likes-file', 'user', 'file', 'User likes file', NULL), ('user-read-publication', 'user', 'publication', 'User read publication', NULL), ('user-read-text', 'user', 'text', 'User read text', NULL);

INSERT INTO `user_role` (`id`, `role_id`, `is_default`, `parent_id`, `create_by`, `create_datetime`) VALUES (NULL, 'pub_ladies', '0', '11', NULL, NULL), (NULL, 'pub_sisters', '0', '11', NULL, NULL), (NULL, 'pub_brothers', '0', '11', NULL, NULL), (NULL, 'pub_families', '0', '11', NULL, NULL);
INSERT INTO `user_role` (`id`, `role_id`, `is_default`, `parent_id`, `create_by`, `create_datetime`) VALUES (NULL, 'pub_ladies_moderator', '0', '12', '5', '2018-11-02 00:00:00'), (NULL, 'pub_sisters_moderator', '0', '12', '5', '2018-11-02 00:00:00'), (NULL, 'pub_brothers_moderator', '0', '12', '5', '2018-11-02 00:00:00'), (NULL, 'pub_families_moderator', '0', '12', '5', '2018-11-02 00:00:00');

# guest is no longer default role
UPDATE `user_role` SET `is_default` = '0' WHERE `user_role`.`id` = 2;
# make lib_user default
UPDATE `user_role` SET `is_default` = '1' WHERE `user_role`.`id` = 7;
# make pub_user default
UPDATE `user_role` SET `is_default` = '1' WHERE `user_role`.`id` = 16;
#add some more title columns
ALTER TABLE `sch_publications` ADD `TitleNoAccents` VARCHAR(300) NULL DEFAULT NULL AFTER `Title`, ADD `Subtitle` VARCHAR(300) NULL DEFAULT NULL AFTER `TitleNoAccents`, ADD `SubtitleNoAccents` VARCHAR(300) NULL DEFAULT NULL AFTER `Subtitle`;
#drop unnecessary columns
ALTER TABLE `sch_publications`
  DROP `AuthorPerson1`,
  DROP `AuthorPerson2`,
  DROP `AuthorPerson3`,
  DROP `AuthorPerson4`,
  DROP `AuthorPerson5`,
  DROP `AuthorAssociationId1`,
  DROP `AuthorAssociationId2`,
  DROP `AuthorAssociationId3`,
  DROP `IllustratorId`,
  DROP `TranslatorId`,
  DROP `Translator2Id`,
  DROP `Translator3Id`,
  DROP `EditorId`,
  DROP `Editor2Id`,
  DROP `Editor3Id`,
  DROP `EditorAssociationId1`,
  DROP `PublisherAssociationId`;
ALTER TABLE `sch_publications` ADD `AuthorsNoAccents` VARCHAR(500) NULL DEFAULT NULL AFTER `Authors`;
ALTER TABLE `sch_publications` ADD `DatePublishedText` VARCHAR(12) NULL DEFAULT NULL AFTER `DatePublished`;
ALTER TABLE `sch_publications` ADD `CopyrightInfo` VARCHAR(500) NULL DEFAULT NULL AFTER `CopyrightYear`;
ALTER TABLE `sch_publications` ADD `EditorNoAccents` VARCHAR(255) NULL DEFAULT NULL AFTER `Editor`;
ALTER TABLE `sch_publications` CHANGE `Editor` `Editor` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

