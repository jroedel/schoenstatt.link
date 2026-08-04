<?php

namespace SchoenstattTest\Integration;

use Books\Filter\SortText;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Characterization test for Books\Filter\SortText, which used to extend
 * Laminas\Filter\PregReplace (now `@final`) and was moved onto the still-open
 * AbstractFilter. It only ever used the parent for setPattern()/getPattern()
 * validity checking; filter() was overridden wholesale.
 *
 * Written against the pre-change code and passing there.
 *
 * Needs vendor/ (laminas-filter), so it runs in the capsule:
 * php composer.phar integration
 */
class SortTextFilterContractTest extends TestCase
{
    private const PATTERN   = '/^([A-Z]{2})-(\d{3})$/';
    private const SORT_TEXT = '{collectionAbbreviation} %1$s %2$s {author|%-3s}';

    private function filter(): SortText
    {
        return new SortText(self::PATTERN, self::SORT_TEXT);
    }

    private function book(string $callNumber = 'AB-123'): array
    {
        return [
            'callNumber'             => $callNumber,
            'collectionAbbreviation' => 'COLL',
            'authorsText'            => 'Kentenich',
            'title'                  => 'Hacia el Padre',
            'inLanguage'             => ['es'],
        ];
    }

    /**
     * The exact string today's code produces. Note this output is itself odd —
     * the trailing '%-3s' consumes argument 1 ('AB') rather than the author
     * token, because the format mixes positional and sequential printf
     * specifiers. That is pre-existing behaviour and is pinned here on purpose:
     * this test guards the base-class swap, it does not bless the format logic.
     */
    public function testProducesTheSameSortText(): void
    {
        self::assertSame('COLL AB 123 AB ', $this->filter()->filter($this->book()));
    }

    public function testNonArrayInputYieldsNull(): void
    {
        self::assertNull($this->filter()->filter('not an array'));
    }

    public function testCallNumberNotMatchingThePatternYieldsNull(): void
    {
        self::assertNull($this->filter()->filter($this->book('does-not-match')));
    }

    public function testPatternAndSortTextAreReadableBack(): void
    {
        $filter = $this->filter();

        self::assertSame(self::PATTERN, $filter->getPattern());
        self::assertSame(self::SORT_TEXT, $filter->getSortText());
    }

    /**
     * PregReplace's replacement API was explicitly disabled by this subclass.
     */
    public function testSetReplacementIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not available for this class');

        $this->filter()->setReplacement('x');
    }

    public function testGetReplacementIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not available for this class');

        $this->filter()->getReplacement();
    }

    /**
     * A sort-text format with no printf parameters at all is rejected at
     * construction — the check lives in setPattern().
     */
    public function testFormatWithoutParametersIsRejected(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid sort text format, no capture groups set');

        new SortText('/^(\d)$/', 'no tokens no printf');
    }

    /**
     * An unknown {token} name is rejected while resolving the format.
     */
    public function testUnknownTokenIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad token name: nonsense');

        new SortText('/^(\d)$/', '%1$s {nonsense}');
    }

    /**
     * An invalid regex must still be refused — this is the one behaviour that
     * came from PregReplace::setPattern() and had to be reimplemented.
     */
    public function testInvalidPatternIsRejected(): void
    {
        $this->expectException(\Exception::class);

        new SortText('not-a-valid-regex', '%1$s');
    }
}
