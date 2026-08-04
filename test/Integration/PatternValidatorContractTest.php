<?php

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use SionModel\Validator\Instagram;
use SionModel\Validator\Phone;
use SionModel\Validator\Skype;
use SionModel\Validator\Slack;
use SionModel\Validator\Twitter;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Characterization test for the six pattern validators that used to extend
 * Laminas\Validator\Regex. laminas marked Regex `@final`, so they were moved
 * onto SionModel\Validator\AbstractPatternValidator (which extends the still-open
 * AbstractValidator). Nothing about their observable behaviour may change:
 *
 *  - the same inputs are accepted and rejected;
 *  - failures are keyed 'regexNotMatch' — the key string Regex used, preserved
 *    deliberately so anything reading message keys keeps working;
 *  - the custom message text replaces all three of Regex's templates.
 *
 * Written against the pre-change (extends-Regex) code and passing there, so a
 * behavioural difference introduced by the rewrite fails loudly.
 *
 * Needs vendor/ (laminas-validator), so it lives outside the vendor-free unit
 * suite and runs in the capsule: php composer.phar integration
 */
class PatternValidatorContractTest extends TestCase
{
    /** The key Laminas\Validator\Regex reports a non-matching value under. */
    private const NOT_MATCH = 'regexNotMatch';

    /**
     * @return array<string, array{class-string, string, bool}>
     */
    public static function patternCases(): array
    {
        return [
            // Instagram: letter/number/underscore start, '.' allowed but not doubled, 1-30 chars.
            'instagram plain'          => [Instagram::class, 'good.user', true],
            'instagram single char'    => [Instagram::class, 'a', true],
            'instagram 30 chars'       => [Instagram::class, str_repeat('a', 30), true],
            'instagram 31 chars'       => [Instagram::class, str_repeat('a', 31), false],
            'instagram leading dot'    => [Instagram::class, '.bad', false],
            'instagram with space'     => [Instagram::class, 'has space', false],
            'instagram empty'          => [Instagram::class, '', false],

            // Phone: must start '+', digits/dash/space/parens, optional ' ext. ##'.
            'phone international'      => [Phone::class, '+1 (555) 123-4567', true],
            'phone with extension'     => [Phone::class, '+49 261 12345 ext. 12', true],
            'phone no country code'    => [Phone::class, '555-1234', false],
            'phone too short'          => [Phone::class, '+1', false],
            'phone empty'              => [Phone::class, '', false],

            // Skype: letter start, 6-32 chars total.
            'skype minimum length'     => [Skype::class, 'abcdef', true],
            'skype punctuation'        => [Skype::class, 'a1.,-_bcd', true],
            'skype uppercase start'    => [Skype::class, 'Abcdef', true],
            'skype too short'          => [Skype::class, 'abcd', false],
            'skype digit start'        => [Skype::class, '1abcdef', false],
            'skype empty'              => [Skype::class, '', false],

            // Slack: lowercase/digit start; no uppercase anywhere.
            'slack punctuation'        => [Slack::class, 'a.b-c_d', true],
            'slack digit start'        => [Slack::class, '0abc', true],
            'slack uppercase rejected' => [Slack::class, 'Abc', false],
            'slack leading dot'        => [Slack::class, '.abc', false],
            'slack empty'              => [Slack::class, '', false],

            // Twitter: letters/numbers/underscore, 1-15 chars.
            'twitter plain'            => [Twitter::class, 'jack', true],
            'twitter 15 chars'         => [Twitter::class, str_repeat('a', 15), true],
            'twitter 16 chars'         => [Twitter::class, str_repeat('a', 16), false],
            'twitter dot rejected'     => [Twitter::class, 'has.dot', false],
            'twitter empty'            => [Twitter::class, '', false],
        ];
    }

    /** @param class-string $class */
    #[DataProvider('patternCases')]
    public function testAcceptsAndRejectsTheSameValues(string $class, string $value, bool $expected): void
    {
        $validator = new $class();

        self::assertSame(
            $expected,
            $validator->isValid($value),
            sprintf('%s::isValid(%s)', $class, var_export($value, true))
        );
    }

    /** @param class-string $class */
    #[DataProvider('patternCases')]
    public function testFailuresAreKeyedRegexNotMatch(string $class, string $value, bool $expected): void
    {
        if ($expected) {
            self::markTestSkipped('only failing values carry messages');
        }

        $validator = new $class();
        $validator->isValid($value);

        self::assertSame([self::NOT_MATCH], array_keys($validator->getMessages()));
    }

    /**
     * The whole reason these classes exist: they replace Regex's generic
     * "does not match against pattern '%pattern%'" with human wording. All
     * three templates (INVALID / NOT_MATCH / ERROROUS) got the same string.
     *
     * @param class-string $class
     */
    #[DataProvider('messageCases')]
    public function testCustomMessageSurvives(string $class, string $expectedFragment): void
    {
        $validator = new $class();
        $validator->isValid('!!!definitely invalid!!!');
        $messages = $validator->getMessages();

        self::assertArrayHasKey(self::NOT_MATCH, $messages);
        self::assertStringContainsString($expectedFragment, $messages[self::NOT_MATCH]);
        self::assertStringNotContainsString('%pattern%', $messages[self::NOT_MATCH]);
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function messageCases(): array
    {
        return [
            'instagram' => [Instagram::class, 'Instagram user names should begin with a letter'],
            'phone'     => [Phone::class, "Please begin with '+' and the country code"],
            'skype'     => [Skype::class, 'Skype user names should begin with a letter'],
            'slack'     => [Slack::class, 'Slack user names should begin with a letter or number'],
            'twitter'   => [Twitter::class, 'Twitter user names should contain only letters'],
        ];
    }

    /**
     * Non-scalar input is rejected rather than fatal — Regex reported INVALID
     * for anything that is not string|int|float.
     */
    public function testNonScalarInputIsRejectedNotFatal(): void
    {
        $validator = new Twitter();

        self::assertFalse($validator->isValid([]));
        self::assertNotEmpty($validator->getMessages());
    }

    /**
     * Integers are accepted as input types (Regex cast them to string).
     */
    public function testIntegerInputIsCastToString(): void
    {
        self::assertTrue((new Twitter())->isValid(12345));
    }

    // ---------------------------------------------------------------
    // Schoenstatt\Validator\SchoenstattLinkIdentifier
    //
    // Same base-class swap, but this one is also used across ~15 files as a
    // constants bag (ENTITY_PERSON, ENTITY_TYPE_ROUTES, ...), so the constants
    // must stay reachable on the class.
    // ---------------------------------------------------------------

    /**
     */
    #[DataProvider('linkIdentifierCases')]
    public function testSchoenstattLinkIdentifier(
        ?string $entityType,
        bool $preApril2020,
        string $value,
        bool $expected
    ): void {
        $validator = new SchoenstattLinkIdentifier($entityType, $preApril2020);

        self::assertSame($expected, $validator->isValid($value));
    }

    /**
     * @return array<string, array{?string, bool, string, bool}>
     */
    public static function linkIdentifierCases(): array
    {
        return [
            'general accepts association' => [null, false, 'SL100001A', true],
            'general accepts person'      => [null, false, 'SL300001P', true],
            'general rejects 5 digit'     => [null, false, 'SL10001A', false],
            'person accepts person id'    => ['person', false, 'SL300001P', true],
            'person rejects association'  => ['person', false, 'SL100001A', false],
            'pre-2020 accepts 5 digit'    => [null, true, 'SL10001A', true],
        ];
    }

    public function testSchoenstattLinkIdentifierRejectsUnknownEntityType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid entity type `nonsense`');

        new SchoenstattLinkIdentifier('nonsense');
    }

    /**
     * The constants are the widely-consumed part of this class.
     */
    public function testSchoenstattLinkIdentifierConstantsRemain(): void
    {
        self::assertSame('person', SchoenstattLinkIdentifier::ENTITY_PERSON);
        self::assertSame('association', SchoenstattLinkIdentifier::ENTITY_ASSOCIATION);
        self::assertSame(
            'association',
            SchoenstattLinkIdentifier::ENTITY_TYPE_ROUTES[SchoenstattLinkIdentifier::ENTITY_ASSOCIATION]
        );
        self::assertSame(
            300000,
            SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER[SchoenstattLinkIdentifier::ENTITY_PERSON]
        );
        self::assertArrayHasKey('P', SchoenstattLinkIdentifier::ENTITY_TYPE_ABBRS);
    }
}
