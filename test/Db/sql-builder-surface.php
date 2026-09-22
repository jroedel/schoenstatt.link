<?php

/**
 * What the SQL builder writes, for every statement shape the four repositories assemble.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php test/Db/regenerate-sql-builder-surface.php
 *
 * Keyed case => ['sql' => the statement, 'values' => what its placeholders bind, in order].
 *
 * This is the builder's contract, and it is a different one from `test/Db/sql-surface.txt`:
 * that recording is of the statements the *server* received during a full pass over the
 * site, normalised, and it says nothing about which values were bound or in what order.
 * Here nothing is normalised and the values are part of the answer, so a rendering rule
 * that moves a placeholder without changing the statement's shape shows up as a diff.
 *
 * `SchoenstattTest\Db\SqlBuilderCases` is where the cases come from and why each is here.
 *
 * @return array<string, array{sql: string, values: list<mixed>}>
 */

declare(strict_types=1);

return array (
  'select: bare table' => 
  array (
    'sql' => 'SELECT `sch_associations`.* FROM `sch_associations`',
    'values' => 
    array (
    ),
  ),
  'select: columns, mixed aliasing' => 
  array (
    'sql' => 'SELECT `sch_publications`.`Title` AS `Title`, `sch_publications`.`CategoryId` AS `Cat` FROM `sch_publications`',
    'values' => 
    array (
    ),
  ),
  'select: expression column' => 
  array (
    'sql' => 'SELECT `sch_visits`.`EntityId` AS `EntityId`, COUNT(*) AS `TotalVisits` FROM `sch_visits`',
    'values' => 
    array (
    ),
  ),
  'select: group, having, expressions' => 
  array (
    'sql' => 'SELECT MONTH(`UpdatedOn`) AS `TheMonth`, YEAR(`UpdatedOn`) AS `TheYear`, Count(*) AS `Count` FROM `sch_changes` GROUP BY `TheMonth`, `TheYear` HAVING `Count` = ?',
    'values' => 
    array (
      0 => 3,
    ),
  ),
  'select: order, plain list' => 
  array (
    'sql' => 'SELECT `relationships`.* FROM `relationships` ORDER BY `PredicateKind` ASC, `Priority` ASC, `UpdatedOn` ASC',
    'values' => 
    array (
    ),
  ),
  'select: order, direction map' => 
  array (
    'sql' => 'SELECT `trans_phrases`.* FROM `trans_phrases` ORDER BY `text_domain` ASC, `phrase` ASC, `translation_phrase_id` ASC',
    'values' => 
    array (
    ),
  ),
  'select: order, list and map mixed' => 
  array (
    'sql' => 'SELECT `lib_collections`.* FROM `lib_collections` ORDER BY `LibraryId` ASC, `IsActive` DESC, `CollectionName` ASC',
    'values' => 
    array (
    ),
  ),
  'select: order, one comma-separated string' => 
  array (
    'sql' => 'SELECT `sch_changes`.* FROM `sch_changes` ORDER BY `TheYear` ASC, `TheMonth` ASC',
    'values' => 
    array (
    ),
  ),
  'select: order reset' => 
  array (
    'sql' => 'SELECT `bib_dictionary`.* FROM `bib_dictionary`',
    'values' => 
    array (
    ),
  ),
  'select: limit and offset' => 
  array (
    'sql' => 'SELECT `sch_changes`.* FROM `sch_changes` ORDER BY `UpdatedOn` DESC LIMIT ? OFFSET ?',
    'values' => 
    array (
      0 => 250,
      1 => 10,
    ),
  ),
  'select: join, string ON, aliased columns' => 
  array (
    'sql' => 'SELECT `sch_publications`.`Title` AS `Title`, `sch_pub_categories`.`SortOrder` AS `SortOrder`, `sch_pub_categories`.`CategoryName` AS `CategoryName`, `sch_pub_categories`.`ParentId` AS `CategoryParentId` FROM `sch_publications` LEFT JOIN `sch_pub_categories` ON `sch_pub_categories`.`PublicationCategoryId` = `sch_publications`.`CategoryId`',
    'values' => 
    array (
    ),
  ),
  'select: join, both tables aliased, no columns' => 
  array (
    'sql' => 'SELECT `l`.* FROM `user_role_linker` AS `l` INNER JOIN `user_role` AS `r` ON `l`.`role_id` = `r`.`id` WHERE `l`.`user_id` = ? AND `r`.`role_id` = ?',
    'values' => 
    array (
      0 => 5,
      1 => 'admin',
    ),
  ),
  'select: join on a predicate, column to column' => 
  array (
    'sql' => 'SELECT `comments`.* FROM `comments` INNER JOIN `relationships` ON `relationships`.`SubjectEntityId` = `comments`.`CommentId` AND `relationships`.`PredicateKind` = ? AND `relationships`.`ObjectEntityId` IN (?, ?)',
    'values' => 
    array (
      0 => 'about',
      1 => 1,
      2 => 2,
    ),
  ),
  'select: join keeps the parent table star' => 
  array (
    'sql' => 'SELECT `lib_books`.*, `lib_collections`.`CollectionName` AS `CollectionName`, `lib_collections`.`Abbreviation` AS `Abbreviation` FROM `lib_books` INNER JOIN `lib_collections` ON `lib_collections`.`CollectionId` = `lib_books`.`collection_id`',
    'values' => 
    array (
    ),
  ),
  'select: nested OR under AND, with a limit' => 
  array (
    'sql' => 'SELECT `lib_books`.* FROM `lib_books` WHERE `library_id` IN (?, ?) AND (`title` LIKE ? OR `author` LIKE ?) ORDER BY `library_id` ASC, `sort_text` ASC LIMIT ?',
    'values' => 
    array (
      0 => 1,
      1 => 2,
      2 => '%q%',
      3 => '%q%',
      4 => 50,
    ),
  ),
  'select: a group inside a group' => 
  array (
    'sql' => 'SELECT `t`.* FROM `t` WHERE `d` = ? AND ((`a` = ? OR `b` = ?) OR `c` IS NULL)',
    'values' => 
    array (
      0 => 4,
      1 => 1,
      2 => 2,
    ),
  ),
  'select: every array-form conversion at once' => 
  array (
    'sql' => 'SELECT `sch_publications`.* FROM `sch_publications` WHERE `CategoryId` = ? AND `DataSource` IS NULL AND `InLanguage` IN (?, ?)',
    'values' => 
    array (
      0 => 3,
      1 => 'de',
      2 => 'es',
    ),
  ),
  'select: an expression as the compared value' => 
  array (
    'sql' => 'SELECT `sch_visits`.* FROM `sch_visits` WHERE `Total` = COUNT(*)',
    'values' => 
    array (
    ),
  ),
  'select: an expression as a whole condition' => 
  array (
    'sql' => 'SELECT `sch_visits`.* FROM `sch_visits` WHERE `VisitedAt` >= DATE_ADD(NOW(), INTERVAL -1 MONTH)',
    'values' => 
    array (
    ),
  ),
  'select: every comparison operator' => 
  array (
    'sql' => 'SELECT `t`.* FROM `t` WHERE `a` = ? AND `a` <> ? AND `a` < ? AND `a` <= ? AND `a` > ? AND `a` >= ?',
    'values' => 
    array (
      0 => 1,
      1 => 1,
      2 => 1,
      3 => 1,
      4 => 1,
      5 => 1,
    ),
  ),
  'select: a backtick inside a name' => 
  array (
    'sql' => 'SELECT `t`.* FROM `t` WHERE `we``ird` = ?',
    'values' => 
    array (
      0 => 1,
    ),
  ),
  'select: a dotted column' => 
  array (
    'sql' => 'SELECT `p`.* FROM `p` WHERE `p`.`a` = ?',
    'values' => 
    array (
      0 => 1,
    ),
  ),
  'select: a condition written out in full' => 
  array (
    'sql' => 'SELECT `t`.* FROM `t` WHERE `a` = `b`',
    'values' => 
    array (
    ),
  ),
  'select: columns reset' => 
  array (
    'sql' => 'SELECT `t`.* FROM `t`',
    'values' => 
    array (
    ),
  ),
  'insert: a null among bound values' => 
  array (
    'sql' => 'INSERT INTO `sch_provenance` (`Entity`, `EntityId`, `SourceUrl`, `RecordedOn`, `RecordedBy`) VALUES (?, ?, NULL, ?, ?)',
    'values' => 
    array (
      0 => 'association',
      1 => 319,
      2 => '2026-09-22 10:00:00',
      3 => 1,
    ),
  ),
  'insert: an expression value' => 
  array (
    'sql' => 'INSERT INTO `lib_checkouts` (`PersonId`, `CheckedOutOn`) VALUES (?, UTC_TIMESTAMP())',
    'values' => 
    array (
      0 => 3,
    ),
  ),
  'update: several columns' => 
  array (
    'sql' => 'UPDATE `lib_checkouts` SET `DueOn` = ?, `TimesRenewed` = ?, `LastRenewedOn` = ? WHERE `CheckoutId` = ?',
    'values' => 
    array (
      0 => '2026-10-01',
      1 => 2,
      2 => '2026-09-22',
      3 => 7,
    ),
  ),
  'update: a null and an expression' => 
  array (
    'sql' => 'UPDATE `trans_phrases` SET `retired_on` = UTC_TIMESTAMP(), `modified_by` = NULL WHERE `translation_phrase_id` = ?',
    'values' => 
    array (
      0 => 4,
    ),
  ),
  'update: an IN and a second term' => 
  array (
    'sql' => 'UPDATE `trans_phrases` SET `retired_on` = ? WHERE `translation_phrase_id` IN (?, ?, ?) AND `project` = ?',
    'values' => 
    array (
      0 => '2026-09-22',
      1 => 1,
      2 => 2,
      3 => 3,
      4 => 'schoenstatt.link',
    ),
  ),
  'delete: one term' => 
  array (
    'sql' => 'DELETE FROM `trans_translations` WHERE `translation_id` = ?',
    'values' => 
    array (
      0 => 9,
    ),
  ),
  'delete: three terms, one of them IS NULL' => 
  array (
    'sql' => 'DELETE FROM `user_api_token` WHERE `user_id` = ? AND `revoked_on` IS NULL AND `expires_on` < ?',
    'values' => 
    array (
      0 => 1,
      1 => '2026-09-22',
    ),
  ),
);
