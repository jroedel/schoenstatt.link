# submitted 2020-07-16
ALTER TABLE `mus_compositions` ADD `OpenLicenseUrl` VARCHAR(255) NULL DEFAULT NULL AFTER `AlternateKeyLabel`;
INSERT INTO `predicates` (`PredicateKind`, `SubjectEntityKind`, `ObjectEntityKind`, `PredicateText`, `DescriptionEn`) VALUES ('comment-comments-composition', 'comment', 'composition', 'Comment comments composition', NULL);
