<?php

namespace SchoenstattTest\Unit;

use JTranslate\I18n\TranslatableMessage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../module/JTranslate/src/I18n/TranslatableMessage.php';

/**
 * Pins the one property that makes this class worth having: the data never reaches
 * the translator.
 *
 * The messengers translate whatever string they are handed at render time, and a
 * translator miss is exactly what files a row in `trans_phrases`. So a message built
 * by concatenation put four real JWTs, six strangers' mistyped email hostnames and a
 * row per book-id combination into a table other accounts can read — none of them
 * translatable, all of them permanent. If a refactor ever makes `render()` translate
 * the finished string instead of the template, `testOnlyTheTemplateIsTranslated`
 * fails and that is the whole point of this file.
 *
 * The class touches no framework code, so this lives in the vendor-free unit suite
 * and requires the file directly.
 */
class TranslatableMessageTest extends TestCase
{
    /** Records every string handed to the translator, and translates nothing. */
    private array $asked = [];

    private function translator(array $catalog = []): callable
    {
        return function (string $message, ?string $domain) use ($catalog): string {
            $this->asked[] = [$domain, $message];
            return $catalog[$message] ?? $message;
        };
    }

    public function testOnlyTheTemplateIsTranslated(): void
    {
        $jwt     = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.payload.signature';
        $message = new TranslatableMessage('Token issued, copy it now: %s', [$jwt]);

        $rendered = $message->render($this->translator());

        self::assertSame([[null, 'Token issued, copy it now: %s']], $this->asked);
        self::assertSame('Token issued, copy it now: ' . $jwt, $rendered);
    }

    public function testTheTranslationIsWhatGetsTheData(): void
    {
        $message = new TranslatableMessage('%s is invalid', ['42']);

        self::assertSame(
            '42 non è valido',
            $message->render($this->translator(['%s is invalid' => '%s non è valido']))
        );
    }

    public function testAnExplicitTextDomainReachesTheTranslator(): void
    {
        (new TranslatableMessage('Hello %s', ['world'], 'JUser'))->render($this->translator());

        self::assertSame([['JUser', 'Hello %s']], $this->asked);
    }

    public function testBothHalvesAreEscapedWhenAnEscaperIsGiven(): void
    {
        $message = new TranslatableMessage('Could not issue a token: %s', ['<script>&']);

        $rendered = $message->render(
            $this->translator(['Could not issue a token: %s' => 'Impossibile & %s']),
            static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES)
        );

        self::assertSame('Impossibile &amp; &lt;script&gt;&amp;', $rendered);
    }

    public function testNoEscaperMeansNoEscaping(): void
    {
        $message = new TranslatableMessage('a %s b', ['<i>']);

        self::assertSame('a <i> b', $message->render($this->translator()));
    }

    /**
     * A translation is data written by a translator, so a translation that invents a
     * placeholder the key does not have is a matter of when, not if — and vsprintf
     * throws ArgumentCountError for it rather than warning. That must not take the
     * page down, and the data must still be shown, so it falls back to the source
     * template. (A translator who writes `%d` where the key says `%s` gets a wrong
     * number rather than an exception; PHP coerces, and there is nothing to catch.)
     */
    public function testABrokenTranslationFallsBackToTheSourceTemplate(): void
    {
        $message = new TranslatableMessage('Issued: %s', ['abc']);

        self::assertSame(
            'Issued: abc',
            $message->render($this->translator(['Issued: %s' => 'Rilasciato: %s a %s']))
        );
    }

    public function testATemplateWithNoPlaceholderIsLeftAlone(): void
    {
        $message = new TranslatableMessage('Nothing to interpolate');

        self::assertSame('Nothing to interpolate', $message->render($this->translator()));
    }

    /** Non-string parameters are coerced once, at construction, so serialization is plain. */
    public function testParametersAreCoercedToStrings(): void
    {
        $message = new TranslatableMessage('%s and %s', [148, true]);

        self::assertSame(['148', '1'], $message->getParameters());
        self::assertSame('148 and 1', $message->render($this->translator()));
    }
}
