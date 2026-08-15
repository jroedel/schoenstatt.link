<?php

/**
 * Regenerates SionModel's ISO 639-2/3 language table from ICU.
 *
 *     docker compose exec -T app php tools/generate-iso639-2-languages.php
 *
 * ## Why the table is generated once and committed, rather than read from ICU at runtime
 *
 * The obvious implementation is to ask `ResourceBundle` for the language list whenever a form
 * needs it. That would make the option list a property of **whichever ICU the runtime happens
 * to carry** — and this application's two runtimes do not agree: the capsule builds against
 * ICU 76.1 and production serves ICU 72.1 (measured 2026-08-11 from a live `phpinfo()`; the
 * hoster's build went *backwards* from 76.1 at the 8.3 → 8.4 flip and did not come back at
 * 8.5). A language code offered locally but unknown to production's ICU would make a book
 * saveable in the capsule and refused on the live site, and nothing in the source would say
 * why.
 *
 * Committing the table removes the runtime from the question entirely. It is then reviewable
 * in git, identical everywhere, and changes only when someone deliberately regenerates it.
 * `LanguageSupport`'s existing 184 ISO 639-1 entries are already a committed table for the
 * same reason; this only extends the same decision to the three-letter codes.
 *
 * ## What it selects
 *
 * ICU's `Languages` bundle for `en` lists 663 keys. Three groups are dropped:
 *
 * - the 184 two-letter codes, which `LanguageSupport` already carries with hand-written
 *   translations that are better than ICU's for the six languages this site speaks. They are
 *   excluded by their length, not by asking LanguageSupport — see the note at the loop;
 * - 23 keys that are not languages at all but locale variants (`de_AT`, `pt_BR`, `zh_Hans`);
 * - `und`, ICU's "undetermined" placeholder, which `Locale::canonicalize()` maps to the empty
 *   string and which would mean "language unknown" — a thing an empty select already says.
 *
 * That leaves 455. Display names come from each of the six supported locales' own bundle,
 * falling back to English where ICU has no translation (87 of them in Spanish and Portuguese).
 * The first letter is upper-cased: ICU follows the Romance convention of lowercase language
 * names, and the curated 184 do not, so leaving them as-is would put `cebuano` next to
 * `Bengalí` in the same dropdown.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

const SUPPORTED = ['en', 'es', 'de', 'pt', 'it', 'fr'];
const TARGET    = __DIR__ . '/../module/SionModel/src/I18n/language-names-iso639-2.php';

$names = [];
foreach (SUPPORTED as $locale) {
    $bundle = ResourceBundle::create($locale, 'ICUDATA-lang');
    if (null === $bundle) {
        fwrite(STDERR, "no ICUDATA-lang bundle for $locale\n");
        exit(1);
    }
    foreach ($bundle->get('Languages') as $code => $name) {
        $names[$locale][(string) $code] = (string) $name;
    }
}

$ucfirstUtf8 = static fn(string $value): string => '' === $value
    ? $value
    : mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);

//The three-character test is the only separator between this table and LanguageSupport's, and
//it has to be: asking LanguageSupport what it already knows makes the script circular, because
//once this file exists getValidLanguages() answers with its contents too. Run that way once and
//it emptied the file it had just written, reporting "wrote 0 codes" as if that were a result.
$table = [];
foreach (array_keys($names['en']) as $code) {
    if (1 !== preg_match('/^[a-z]{3}$/', $code)) {
        continue;
    }
    if ($code !== Locale::canonicalize($code)) {
        continue;
    }
    $row = [];
    foreach (SUPPORTED as $locale) {
        $row[$locale] = $ucfirstUtf8($names[$locale][$code] ?? $names['en'][$code]);
    }
    $table[$code] = $row;
}

uasort($table, static fn(array $a, array $b): int => strcmp($a['en'], $b['en']));

$lines = [];
foreach ($table as $code => $row) {
    $parts = [];
    foreach (SUPPORTED as $locale) {
        $parts[] = sprintf("'%s' => \"%s\"", $locale, str_replace(['\\', '"'], ['\\\\', '\\"'], $row[$locale]));
    }
    $lines[] = sprintf("    '%s' => [%s],", $code, implode(', ', $parts));
}

$header = <<<'PHP'
<?php

/**
 * ISO 639-2/3 language codes and their names in the six languages this site speaks.
 *
 * GENERATED FILE — do not hand-edit. Regenerate with:
 *
 *     docker compose exec -T app php tools/generate-iso639-2-languages.php
 *
 * That script explains what is selected and why the table is committed instead of read from
 * ICU at request time. The short version: the capsule and production carry different ICU
 * versions, so a runtime lookup would make the set of saveable languages differ between them.
 *
 * These extend the 184 two-letter codes written out in LanguageSupport, which keep their
 * hand-written translations. They are appended after those rather than merged alphabetically,
 * so a moderator sees the languages a book is actually likely to be in before the long tail.
 *
 * @return array<string, array<string, string>>
 */

declare(strict_types=1);

return [
PHP;

file_put_contents(TARGET, $header . "\n" . implode("\n", $lines) . "\n];\n");

printf("wrote %d codes to %s\n", count($table), realpath(TARGET));
