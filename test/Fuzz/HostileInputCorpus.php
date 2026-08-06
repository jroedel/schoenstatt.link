<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

/**
 * The garbage this harness pushes through every form, written out by name so a
 * reader can see the coverage instead of inferring it from a generator.
 *
 * Two rules govern what belongs here.
 *
 * **Every entry must be something a browser or a script can actually send.** An
 * HTTP form body is bytes; `$_POST` is strings, and arrays where the name has
 * `[]` in it. So `null`, `[]` and nested arrays are in the corpus not as
 * curiosities but because `name[]=x` and a missing key are both trivially
 * producible by hand, and laminas passes whatever arrives straight into
 * validators that were written expecting a string. `PHP_INT_MAX` and `1e400` are
 * here as their string forms for the same reason: nothing arrives from the wire
 * as an int.
 *
 * **Every entry must correspond to a way the database can end up wrong.** With
 * MariaDB 10.11 in STRICT_TRANS_TABLES an over-length varchar is SQLSTATE 22001 —
 * loud, a 500, findable. The values that matter more are the quiet ones: a
 * wrong-domain string in an enum-ish varchar (`religiousStatus = '../../etc/passwd'`
 * is 16 characters and fits any column), an absurd-but-parseable date
 * (`'+500 years'` becomes 2526-08-05 and inserts happily), and invalid UTF-8,
 * which `SionTable` will join with `|` and hand to a utf8 column. Those pass every
 * length check and are permanent.
 *
 * Boundary values are not listed here because they are per-field: see
 * `boundaryValues()`, which is handed the tightest bound the field declares.
 */
final class HostileInputCorpus
{
    /**
     * Fixed seed for the combination phase. Printed by the test on every run.
     *
     * A fuzzer that finds a different thing each time is a fuzzer that gets
     * deleted the first time it fails on someone else's branch, so nothing here
     * consumes entropy that is not derived from this constant. `0x5C4` is
     * arbitrary; changing it is a deliberate act that will surface new findings and
     * needs a baseline regeneration.
     */
    public const SEED = 0x5C4;

    /** How many seeded cross-field combinations to try per form. */
    public const COMBINATION_ROUNDS = 12;

    /**
     * label => value. Labels are stable identifiers: they appear verbatim in
     * findings and in the baseline, so renaming one invalidates baseline entries.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            // --- emptiness. 'required' has to distinguish these three, and the
            // ToNull filter is what turns two of them into a NULL column.
            'empty-string'          => '',
            'whitespace-only'       => "   \t \n ",
            'null'                  => null,

            // --- size. 100 KB is past every varchar in the schema and past TEXT's
            // practical use; it is also enough to make an unanchored Regex crawl,
            // which is worth knowing.
            'string-100kb'          => str_repeat('A', 100 * 1024),

            // --- encoding. StringLength counts characters only if it is told an
            // encoding; the emoji and the combining sequence are where a
            // byte-counting bound and a character-counting bound disagree, and the
            // invalid sequences are where mb_* and the database disagree about
            // whether the value is representable at all.
            'emoji-4-byte'          => 'a👍b🇩🇪c',
            'combining-marks'       => "e\u{0301}a\u{0300}\u{0327}\u{0335}n\u{0303}",
            'nul-byte'              => "a\0b",
            'invalid-utf8-lone-continuation' => "a\x80\x81b",
            'invalid-utf8-truncated-sequence' => "a\xC3",
            'invalid-utf8-overlong'  => "a\xC0\xAFb",

            // --- markup. StripTags is the codebase's usual answer; these prove
            // whether it is actually wired in for the field.
            'script-tag'            => '<script>alert(1)</script>',
            'html-comment'          => '<!-- comment -->plain',
            'unclosed-tag'          => '<div onmouseover="x"',

            // --- SQL metacharacters. laminas-db parameterises, so these are not
            // expected to inject; they are here because they are what a string
            // field is *for* the day someone concatenates one, and because the
            // backslash and backtick are the characters that break the `|` join
            // SionTable does before insert.
            'sql-tautology'         => "' OR 1=1--",
            'backslash'             => 'a\\b\\\\c',
            'backtick'              => 'a`b`c',
            'pipe-separator'        => 'a|b|c',

            // --- traversal. 16 characters: fits every column, wrong in every one.
            'path-traversal'        => '../../etc/passwd',
            'absolute-path'         => '/etc/passwd',

            // --- numerics as strings, which is how they arrive.
            'negative-one'          => '-1',
            'zero'                  => '0',
            'zero-string'           => '"0"',
            'zero-padded'           => '00',
            'int-max'               => (string) PHP_INT_MAX,
            'int-min'               => (string) PHP_INT_MIN,
            'int-max-plus-one'      => '9223372036854775808',
            'float-overflow'        => '1e400',
            'not-a-number'          => 'NaN',
            'float-where-int-wanted' => '3.7',
            'leading-plus'          => '+5',
            'hex-literal'           => '0x1F',

            // --- structure. `field[]=x` in a query string produces these, and a
            // validator expecting a string gets an array.
            'empty-array'           => [],
            'flat-array'            => ['a', 'b'],
            'nested-array'          => ['a' => ['b' => ['c' => 'd']]],

            // --- dates. Everything here parses or fails inside
            // `new DateTime($value)`, which SionModel\Filter\ToDateTime calls with
            // no try/catch. '+500 years' and 'tomorrow' are the dangerous ones:
            // they succeed, so they reach the column as a real date nobody meant.
            'date-garbage'          => 'asdf',
            'date-relative-word'    => 'tomorrow',
            'date-relative-500y'    => '+500 years',
            'date-timestamp-at'     => '@99999999999',
            'date-all-zero'         => '0000-00-00',
            'date-impossible-day'   => '2020-02-30',
            'date-day-first'        => '31-12-2020',
            'date-year-zero'        => '0000-01-01',
            'date-far-future'       => '999999-01-01',
        ];
    }

    /**
     * A benign value, used to fill the fields the attribution phase is not
     * currently probing. Deliberately dull: seven ASCII letters, no markup, inside
     * every length bound in the schema.
     */
    public const BENIGN = 'placebo';

    /**
     * Exactly-at-the-limit and one-over values for a field with a known bound.
     *
     * Both matter and for opposite reasons. One-over must be rejected, or the
     * insert is a 500. Exactly-at must be *accepted*, or the bound is one character
     * too tight and a legitimate value is refused — a bug in the other direction
     * that a fuzzer looking only for crashes never finds.
     *
     * @return array<string, string>
     */
    public static function boundaryValues(?int $max): array
    {
        if (null === $max || $max < 1 || $max > 100000) {
            return [];
        }

        return [
            'boundary-exactly-at-' . $max  => str_repeat('x', $max),
            'boundary-one-over-' . $max    => str_repeat('x', $max + 1),
            // A multi-byte value at the character limit: over the *byte* limit,
            // inside the character limit. Whether that is accepted tells you which
            // one the bound is really counting.
            'boundary-multibyte-at-' . $max => str_repeat('é', $max),
        ];
    }
}
