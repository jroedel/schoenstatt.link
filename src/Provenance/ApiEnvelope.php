<?php

declare(strict_types=1);

namespace App\Provenance;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

use function implode;
use function is_string;
use function mb_strlen;
use function sprintf;

/**
 * The provenance an API caller declares alongside its patch: four reserved keys.
 *
 * ```jsonc
 * {
 *   "openingHoursHuman": "Mon-Sat 9-17",
 *   "_source":     "website",              // where this came from
 *   "_sourceUrl":  "https://…/hours",      // the evidence, if it has a URL
 *   "_sourceNote": "parish bulletin, p2",  // or in words
 *   "_assertedOn": "2026-08-01"            // when it was true, not when you sent it
 * }
 * ```
 *
 * Underscored for the reason `PhrasesV3Controller::NOTE_KEY` is: the association PATCH body
 * validates strictly and refuses unknown fields with a 422, so all four keys were a 422
 * before this existed and no caller can be sending them meaning something else. No field of
 * `sch_associations` starts with an underscore and none can, so there is no collision to come.
 *
 * ## The default is the floor, on purpose
 *
 * A caller that declares nothing gets {@see SourceClass::Inference} — the lowest rank. Two
 * reasons. It is honest: a write that will not say where it came from has not established
 * anything. And it is incentive-compatible: declaring `website` with a URL earns an agent
 * more authority than staying silent, which is exactly the behaviour worth rewarding.
 *
 * This is not a breaking change for the existing bot even though it looks like one. The gate
 * only withholds a write when a **strictly higher-ranked** claim is already on file, and the
 * table starts empty — so until humans and offices begin asserting, every undeclared write
 * still applies exactly as it did before. {@see WriteGate}.
 */
final class ApiEnvelope
{
    public const SOURCE_KEY = '_source';
    public const URL_KEY    = '_sourceUrl';
    public const NOTE_KEY   = '_sourceNote';
    public const ASSERTED_KEY = '_assertedOn';

    private const URL_MAX  = 1000;
    private const NOTE_MAX = 255;

    private function __construct(
        public readonly SourceClass $source,
        public readonly DateTimeImmutable $assertedOn,
        public readonly ?string $sourceUrl,
        public readonly ?string $sourceNote,
    ) {
    }

    /** @return list<string> the keys this envelope claims, to strip before field validation */
    public static function keys(): array
    {
        return [self::SOURCE_KEY, self::URL_KEY, self::NOTE_KEY, self::ASSERTED_KEY];
    }

    /**
     * Parse the envelope out of a decoded body, or return the reason it is unacceptable.
     *
     * Returns a string on failure rather than throwing, because every failure here is a 422
     * the caller can fix and the message is the whole point of sending one.
     *
     * @param array<array-key, mixed> $body
     * @return self|string the envelope, or a message naming what is wrong
     */
    public static function parse(array $body, DateTimeImmutable $now): self|string
    {
        $source = SourceClass::Inference;
        if (isset($body[self::SOURCE_KEY])) {
            $raw = $body[self::SOURCE_KEY];
            if (! is_string($raw) || null === SourceClass::tryFrom($raw)) {
                return sprintf(
                    '`%s` must be one of: %s.',
                    self::SOURCE_KEY,
                    implode(', ', SourceClass::values())
                );
            }
            $source = SourceClass::from($raw);
        }

        $url = null;
        if (isset($body[self::URL_KEY])) {
            $raw = $body[self::URL_KEY];
            if (! is_string($raw)) {
                return sprintf('`%s` must be a string.', self::URL_KEY);
            }
            if (mb_strlen($raw) > self::URL_MAX) {
                return sprintf('`%s` must be at most %d characters.', self::URL_KEY, self::URL_MAX);
            }
            $url = '' === $raw ? null : $raw;
        }

        $note = null;
        if (isset($body[self::NOTE_KEY])) {
            $raw = $body[self::NOTE_KEY];
            if (! is_string($raw)) {
                return sprintf('`%s` must be a string.', self::NOTE_KEY);
            }
            if (mb_strlen($raw) > self::NOTE_MAX) {
                return sprintf('`%s` must be at most %d characters.', self::NOTE_KEY, self::NOTE_MAX);
            }
            $note = '' === $raw ? null : $raw;
        }

        $assertedOn = $now;
        if (isset($body[self::ASSERTED_KEY])) {
            $raw = $body[self::ASSERTED_KEY];
            if (! is_string($raw)) {
                return sprintf('`%s` must be a date string.', self::ASSERTED_KEY);
            }
            try {
                $assertedOn = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
            } catch (Throwable) {
                return sprintf('`%s` is not a date this API can read.', self::ASSERTED_KEY);
            }
            if ($assertedOn > $now) {
                //A claim about the future is not evidence, and accepting one would let a
                //caller hold a value indefinitely: WriteGate measures the protection window
                //from assertedOn, so a date in 2030 would protect it until 2030.
                return sprintf('`%s` cannot be in the future.', self::ASSERTED_KEY);
            }
        }

        return new self($source, $assertedOn, $url, $note);
    }

    /** @return array<string, mixed> what the schema document says about these keys */
    public static function describe(): array
    {
        return [
            self::SOURCE_KEY   => [
                'enum'    => SourceClass::values(),
                'default' => SourceClass::Inference->value,
                'purpose' => 'Where this claim came from. Ranked: the shrine itself outranks an '
                    . 'office, then its own website, then a third-party directory, then an '
                    . 'agent inference. Undeclared means the lowest rank.',
            ],
            self::URL_KEY      => ['maxLength' => self::URL_MAX, 'purpose' => 'The evidence, if it has a URL.'],
            self::NOTE_KEY     => ['maxLength' => self::NOTE_MAX, 'purpose' => 'The evidence in words.'],
            self::ASSERTED_KEY => [
                'purpose' => 'When the claim was true, not when you sent it. Defaults to now; '
                    . 'may not be in the future. The freshness window is measured from this.',
            ],
        ];
    }
}
