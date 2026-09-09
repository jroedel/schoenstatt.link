<?php

namespace SchoenstattTest\Unit;

use App\Routing\RegexSampler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Routing/RegexSampler.php';

/**
 * Pins the value generator behind the authorization oracle's shadow check.
 *
 * `tools/acl-table.php` decides whether a Symfony route has taken over a laminas
 * route's URLs by instantiating the laminas pattern into a concrete URL and
 * matching it. Every such URL comes from here, so a wrong sample is a wrong row
 * in `docs/acl-baseline.json` — and the tool's whole purpose is to be believed when it
 * says who can reach what.
 *
 * The property that matters is not "the sample looks right" but **"the sample
 * matches the pattern, or is null"**. That is asserted generically in
 * {@see testEverySampleSatisfiesItsOwnPattern} against every constraint in the
 * application's routes plus a pile of hostile ones; the named cases below exist
 * to pin the *specific* values the oracle's committed snapshot depends on, so
 * that a change in the shortest-branch policy shows up as a test failure rather
 * than as unexplained churn in a reviewed baseline file.
 *
 * The class touches no framework code, so this lives in the vendor-free unit
 * suite and requires the file directly.
 */
class RegexSamplerTest extends TestCase
{
    /**
     * The constraints this application's routes actually declare.
     *
     * Kept as literals rather than read from the merged config on purpose: the
     * unit suite must stay runnable with no vendor tree and no database, and a
     * constraint that vanishes from the config should not silently stop being
     * tested here. If a route grows a constraint shape not represented below,
     * the generic property test in the integration suite is what catches it.
     *
     * @return array<string, array{string, string}> name => [pattern, expected sample]
     */
    public static function realConstraints(): array
    {
        return [
            'numeric id, bounded'      => ['[0-9]{1,5}', '0'],
            'numeric id, unbounded'    => ['[0-9]+', '0'],
            'small numeric id'         => ['[0-9]{1,3}', '0'],
            'large numeric id'         => ['[0-9]{1,8}', '0'],
            'entity name'              => ['[a-zA-Z_-]{1,25}', 'a'],
            'slug'                     => ['[a-z0-9-]{1,200}', 'a'],
            // The identifier regexes, as the routes carry them: trimmed of
            // '/^$' by SchoenstattLinkIdentifier, capture group and all.
            'association identifier'   => ['SL(1[0-9]{5,5})A', 'SL100000A'],
            'person identifier'        => ['SL(3[0-9]{5,5})P', 'SL300000P'],
            'text identifier'          => ['SL(4[0-9]{5,5})T', 'SL400000T'],
            'publication identifier'   => ['SL(2[0-9]{5,5})L', 'SL200000L'],
            'composition identifier'   => ['SL(5[0-9]{5,5})C', 'SL500000C'],
            'event identifier'         => ['SL(6[0-9]{5,5})E', 'SL600000E'],
            // association-delete's constraint before it was corrected: five
            // digits where every real identifier has six. The sampler has no
            // opinion about that — it faithfully produces what the route asks
            // for, which is how the mismatch became visible in the first place.
            'short association id'     => ['SL1[0-9]{4,4}A', 'SL10000A'],
            'two-prefix identifier'    => ['SL[12][0-9]{4,4}A', 'SL10000A'],
            'the general old form'     => ['SL([0-9]{5,5})([APLC])', 'SL00000A'],
            // The api catch-all, a Regex route rather than a Segment one.
            'api catch-all'            => ['/api(/.*)?', '/api/a'],
        ];
    }

    #[DataProvider('realConstraints')]
    public function testProducesTheExpectedSampleForARealConstraint(string $pattern, string $expected): void
    {
        self::assertSame($expected, RegexSampler::sample($pattern));
    }

    /**
     * Patterns chosen to break the generator rather than to exercise it.
     *
     * @return array<string, array{string}>
     */
    public static function hostilePatterns(): array
    {
        return [
            'negated class'            => ['[^/]+'],
            'negated class with range' => ['[^a-z]+'],
            'literal dash last'        => ['[a-z-]+'],
            'literal bracket first'    => ['[]a]+'],
            'alternation'              => ['cat|dog|bird'],
            'nested alternation'       => ['(a|b)(c|d)'],
            'optional group'           => ['colou?r'],
            'star'                     => ['ab*c'],
            'nested quantified group'  => ['(ab){2,3}'],
            'escaped metacharacters'   => ['\\d{3}-\\d{4}'],
            'word and space classes'   => ['\\w+\\s\\w+'],
            'dot'                      => ['a.c'],
            'anchored'                 => ['^abc$'],
            'non-capturing group'      => ['(?:ab)+'],
            'named group'              => ['(?<year>[0-9]{4})'],
            'perl named group'         => ['(?P<year>[0-9]{4})'],
            'lookahead'                => ['(?!delete)[a-z]+'],
            'negative lookbehind'      => ['(?<!x)[a-z]+'],
            'lazy quantifier'          => ['[a-z]+?'],
            'possessive quantifier'    => ['[a-z]++'],
            'exact repetition'         => ['[0-9]{4}'],
            'open-ended repetition'    => ['[0-9]{2,}'],
            'empty alternation branch' => ['(a|)b'],
            'the reserved-verb guard'  => ['(?!(?:delete|edit)$)[a-z0-9-]{1,200}'],
        ];
    }

    /**
     * Every pattern either suite knows about, real and hostile alike.
     *
     * @return array<string, array{string}>
     */
    public static function everyPattern(): array
    {
        $patterns = [];
        foreach (self::realConstraints() as $name => $case) {
            $patterns[$name] = [$case[0]];
        }

        return $patterns + self::hostilePatterns();
    }

    /**
     * The one invariant the oracle relies on.
     *
     * A sample that does not match its own pattern would put a URL in the shadow
     * table that no route ever serves, which is worse than no row at all: it
     * reads as a checked fact. Returning null instead is always acceptable —
     * callers report that as uncomparable — so this asserts the disjunction, not
     * that a sample exists.
     */
    #[DataProvider('everyPattern')]
    public function testEverySampleSatisfiesItsOwnPattern(string $pattern): void
    {
        $sample = RegexSampler::sample($pattern);

        if ($sample === null) {
            self::assertNull($sample, 'null is a permitted answer');
            return;
        }

        self::assertSame(
            1,
            preg_match('#^(?:' . $pattern . ')$#', $sample),
            sprintf('sample %s does not match its own pattern %s', var_export($sample, true), $pattern)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsamplablePatterns(): array
    {
        return [
            'unterminated class'   => ['[a-z'],
            'unterminated group'   => ['(abc'],
            'unbalanced close'     => ['abc)'],
            'inline modifier'      => ['(?i)abc'],
            'unterminated brace'   => ['[0-9]{2'],
        ];
    }

    /**
     * Malformed or unmodelled patterns must decline rather than improvise.
     *
     */
    #[DataProvider('unsamplablePatterns')]
    public function testDeclinesWhatItCannotModel(string $pattern): void
    {
        self::assertNull(RegexSampler::sample($pattern));
    }

    /**
     * A conditional pattern is refused outright, not sampled by luck.
     *
     * `(?(1)a|b)` is valid PCRE the generator does not model. It bails at the
     * group prefix, which is the designed behaviour: verification could not be
     * trusted to catch a lucky guess here, because either branch is a literal
     * that might well match.
     */
    public function testRefusesConditionalGroups(): void
    {
        self::assertNull(RegexSampler::sample('(a)(?(1)b|c)'));
    }

    /**
     * The sample is the shortest match, which is what keeps probe URLs stable.
     *
     * `{1,200}` producing a 200-character slug would make every row in the
     * committed baseline unreadable and would churn whenever a bound changed.
     */
    public function testTakesTheShortestBranch(): void
    {
        self::assertSame('a', RegexSampler::sample('[a-z]{1,200}'));
        self::assertSame('0', RegexSampler::sample('[0-9]+'));
        self::assertSame('ab', RegexSampler::sample('(ab)+'));
    }

    /**
     * An empty string is a legitimate sample and must not be confused with
     * failure — `[0-9]*` matches the empty string and nothing shorter.
     */
    public function testDistinguishesAnEmptySampleFromNoSample(): void
    {
        self::assertSame('0', RegexSampler::sample('[0-9]*'));
        self::assertSame('', RegexSampler::sample('^$'));
    }
}
