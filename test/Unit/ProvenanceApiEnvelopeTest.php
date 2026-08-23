<?php

namespace SchoenstattTest\Unit;

use App\Provenance\ApiEnvelope;
use App\Provenance\SourceClass;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Provenance/SourceClass.php';
require_once __DIR__ . '/../../src/Provenance/ApiEnvelope.php';

/**
 * The four reserved keys an API caller uses to say where its claim came from.
 *
 * Pinned because every branch here is a 422 an agent has to be able to act on, and because
 * one of them — the future-date refusal — is a hole rather than a nicety: `WriteGate`
 * measures its protection window from `_assertedOn`, so an accepted date in 2030 would let a
 * caller freeze a value until 2030.
 *
 * Vendor-free: the envelope touches no framework.
 */
class ProvenanceApiEnvelopeTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-23 12:00:00', new DateTimeZone('UTC'));
    }

    /**
     * The default is the floor, and this is the test that says so.
     *
     * A caller that declares nothing has established nothing, and the lowest rank is both the
     * honest reading and the one that rewards declaring a source.
     */
    public function testAnEmptyBodyDefaultsToTheLowestRank(): void
    {
        $envelope = ApiEnvelope::parse([], $this->now());

        $this->assertInstanceOf(ApiEnvelope::class, $envelope);
        $this->assertSame(SourceClass::Inference, $envelope->source);
        $this->assertNull($envelope->sourceUrl);
        $this->assertNull($envelope->sourceNote);
        $this->assertEquals($this->now(), $envelope->assertedOn);
    }

    public function testItReadsAllFourKeys(): void
    {
        $envelope = ApiEnvelope::parse([
            '_source'     => 'shrine',
            '_sourceUrl'  => 'https://example.org/hours',
            '_sourceNote' => 'spoke to the rector',
            '_assertedOn' => '2026-07-04',
        ], $this->now());

        $this->assertInstanceOf(ApiEnvelope::class, $envelope);
        $this->assertSame(SourceClass::Shrine, $envelope->source);
        $this->assertSame('https://example.org/hours', $envelope->sourceUrl);
        $this->assertSame('spoke to the rector', $envelope->sourceNote);
        $this->assertSame('2026-07-04', $envelope->assertedOn->format('Y-m-d'));
    }

    public function testAnUnknownSourceIsRefusedAndNamesTheAlternatives(): void
    {
        $result = ApiEnvelope::parse(['_source' => 'wikipedia'], $this->now());

        $this->assertIsString($result);
        $this->assertStringContainsString('_source', $result);
        $this->assertStringContainsString('shrine', $result);
    }

    /**
     * A future assertion date is refused, not clamped.
     *
     * Clamping would silently rewrite what the caller claimed; refusing tells it the claim
     * was not stored. And accepting it would hand any caller an indefinite lock on a value.
     */
    public function testAFutureAssertionDateIsRefused(): void
    {
        $result = ApiEnvelope::parse(['_assertedOn' => '2030-01-01'], $this->now());

        $this->assertIsString($result);
        $this->assertStringContainsString('future', $result);
    }

    public function testAnUnparseableDateIsRefused(): void
    {
        $result = ApiEnvelope::parse(['_assertedOn' => 'last Tuesday-ish'], $this->now());

        $this->assertIsString($result);
        $this->assertStringContainsString('_assertedOn', $result);
    }

    public function testOverlongTextIsRefusedRatherThanTruncated(): void
    {
        $note = ApiEnvelope::parse(['_sourceNote' => str_repeat('x', 256)], $this->now());
        $this->assertIsString($note, 'a 256-character note must be refused');
        $this->assertStringContainsString('_sourceNote', $note);

        $url = ApiEnvelope::parse(['_sourceUrl' => 'https://e.org/' . str_repeat('y', 1000)], $this->now());
        $this->assertIsString($url, 'a 1001-character url must be refused');
        $this->assertStringContainsString('_sourceUrl', $url);
    }

    /** At the bound, not over it. */
    public function testTextExactlyAtTheBoundIsAccepted(): void
    {
        $envelope = ApiEnvelope::parse(['_sourceNote' => str_repeat('x', 255)], $this->now());

        $this->assertInstanceOf(ApiEnvelope::class, $envelope);
        $this->assertSame(255, mb_strlen((string) $envelope->sourceNote));
    }

    /**
     * A note beginning with `!` survives intact.
     *
     * A regression test for a real defect in the first draft: validation errors were signalled
     * by prefixing the message with `!`, which meant a legitimate value starting with `!` was
     * silently discarded as if it had failed. In-band signalling in a string, caught before it
     * shipped and pinned so it cannot come back.
     */
    public function testANoteBeginningWithAnExclamationMarkIsKept(): void
    {
        $envelope = ApiEnvelope::parse(['_sourceNote' => '!! confirmed twice'], $this->now());

        $this->assertInstanceOf(ApiEnvelope::class, $envelope);
        $this->assertSame('!! confirmed twice', $envelope->sourceNote);
    }

    /** An empty string means "not given" rather than an empty value in the database. */
    public function testEmptyStringsBecomeNull(): void
    {
        $envelope = ApiEnvelope::parse(['_sourceUrl' => '', '_sourceNote' => ''], $this->now());

        $this->assertInstanceOf(ApiEnvelope::class, $envelope);
        $this->assertNull($envelope->sourceUrl);
        $this->assertNull($envelope->sourceNote);
    }

    public function testNonStringValuesAreRefused(): void
    {
        foreach (['_source', '_sourceUrl', '_sourceNote', '_assertedOn'] as $key) {
            $this->assertIsString(
                ApiEnvelope::parse([$key => ['an', 'array']], $this->now()),
                $key . ' must refuse a non-string'
            );
        }
    }

    /** The keys the controller strips must be the keys this class reads. */
    public function testKeysCoversEveryKeyParsed(): void
    {
        $this->assertSame(
            ['_source', '_sourceUrl', '_sourceNote', '_assertedOn'],
            ApiEnvelope::keys()
        );
    }
}
