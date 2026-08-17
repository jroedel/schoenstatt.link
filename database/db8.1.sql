-- db8.1 — drop the five schema.org digest columns nothing ever read
--
-- @phase: post
-- @kind: ddl
-- @idempotent: yes
-- @destructive: yes
-- @tables: sch_associations
-- @verify: SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sch_associations' AND COLUMN_NAME IN ('SchemaOrgJsonMd5V1En','SchemaOrgJsonMd5V1Es','SchemaOrgJsonMd5V1Pt','SchemaOrgJsonMd5V1De','SchemaOrgJsonMd5V1It')
--
-- `@destructive: yes` means older code cannot run once this has been applied, so
-- `tools/deploy.sh` refuses to roll back to a release that does not ship this file.
-- Added 2026-08-17 *after* this migration took the site down in exactly that way:
-- the deploy's automatic rollback restored the previous release, which went on
-- SELECTing these five columns. See docs/incident-2026-08-17-stale-opcache.md.
--
-- `post`, and this one is not a coin flip. During a deploy the pre phase runs while
-- the PREVIOUS release is still being served, and that release's
-- SchoenstattTable::getSelectPrototype() names all five columns in its SELECT for
-- every association read. Dropping them before the swap would take out every
-- association page on the live site for the length of the composer install. After
-- the swap the new code no longer mentions them, so the drop is unobservable.
--
-- `@tables: sch_associations` even though the data is worthless. The columns hold
-- md5 digests recomputable from the row itself, and this migration is the only
-- thing here that destroys a column rather than adding one — 498 rows is a cheap
-- snapshot for the one case where "we were sure nothing read it" could be wrong.

-- Why these columns are going, recorded here because the code that explains it is
-- being deleted in the same commit:
--
--   * They were written by two separate paths that disagreed. The association save
--     path called getAssociationSchemaV1($newData) with NO locale argument inside a
--     five-iteration loop, so all five columns received an identical digest. The
--     sweep behind /en/associations/do-work called it WITH the locale, so it wrote
--     five different ones. Measured on 2026-08-17: of 498 associations, 490 held
--     five distinct digests and 8 held five identical ones — the 8 being rows edited
--     since the last deploy ran the sweep. The deploy had been quietly papering over
--     the write-path bug on every release.
--
--   * Nothing read either version. Three methods appeared to —
--     SchoenstattTable::getAssociationListSchemaV1()/V2() and
--     Books\Model\MusicTable::getCompositionListSchemaV1(), which returned the
--     digests through a by-reference $resultingMd5s parameter — but no code in the
--     repository called any of the three. They were the list endpoints of the v1
--     and v2 APIs, retired 2026-08-14, and the digests were their change-detection
--     mechanism. All three are deleted alongside the columns; no v3 endpoint
--     exposes them.
--
-- getAssociationSchemaV1() itself stays: it renders the JSON-LD on every association
-- page. It is the stored *digest* of its output that had no reader.

ALTER TABLE `sch_associations`
    DROP COLUMN IF EXISTS `SchemaOrgJsonMd5V1En`,
    DROP COLUMN IF EXISTS `SchemaOrgJsonMd5V1Es`,
    DROP COLUMN IF EXISTS `SchemaOrgJsonMd5V1Pt`,
    DROP COLUMN IF EXISTS `SchemaOrgJsonMd5V1De`,
    DROP COLUMN IF EXISTS `SchemaOrgJsonMd5V1It`;
