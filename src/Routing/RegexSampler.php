<?php

declare(strict_types=1);

namespace App\Routing;

use function in_array;
use function preg_match;
use function str_repeat;
use function strcspn;
use function strlen;
use function strpos;
use function substr;

/**
 * Generates one concrete string that a regular expression matches.
 *
 * ## Why this exists
 *
 * `tools/acl-table.php` has to answer "does a Symfony route now serve the URLs
 * this laminas route used to serve?", and a router matches URLs, not patterns.
 * Handing it `/books/:book_id/edit` throws; handing it `/books/0/edit` answers.
 * Turning the first into the second means producing a value that satisfies the
 * route's constraint, which is what this does.
 *
 * Before this existed the tool passed the pattern straight through, so **every**
 * laminas route with a parameter was skipped: 0 of its 31 shadowed rows had one,
 * and roughly 90 routes were never examined at all. That is the blind spot that
 * let nine guarded routes become unreachable in production without the
 * authorization oracle saying a word.
 *
 * ## Why a deliberately partial implementation is safe
 *
 * Producing a string from an arbitrary regex is not something to attempt in
 * general, and this does not try. It walks the small grammar route constraints
 * actually use — literals, character classes, groups, alternation, bounded
 * quantifiers — and takes the shortest branch at every choice.
 *
 * **What makes the result trustworthy is the check after it, not the generator.**
 * Every candidate is matched back against the pattern it came from, and one that
 * does not satisfy it is discarded. So the generator is free to be wrong: a
 * lookahead it steps over, a negated class it guesses at, an alternation branch
 * it picks badly all end as `null`. Callers report that as *uncomparable* rather
 * than as *no match*, so the failure mode is a visible gap rather than a false
 * claim.
 *
 * Never use this to generate test fixtures or user-facing values: the sample is
 * the shortest thing that matches, not a representative one.
 */
final class RegexSampler
{
    /**
     * A string the pattern matches, or null if none could be generated.
     *
     * The pattern is a bare regex with no delimiters — the form route
     * constraints are written in.
     */
    public static function sample(string $pattern): ?string
    {
        // Two passes: the first takes `*` and `?` once, the second takes them
        // zero times. Which one satisfies the pattern depends on the pattern,
        // and trying both is cheaper than reasoning about it.
        foreach ([1, 0] as $optionalRepeats) {
            $position = 0;
            $sample   = self::alternation($pattern, $position, $optionalRepeats);
            if ($sample === null || $position !== strlen($pattern)) {
                continue;
            }
            if (@preg_match('#^(?:' . $pattern . ')$#', $sample) === 1) {
                return $sample;
            }
        }

        return null;
    }

    /** Alternation: the first branch that yields anything wins. */
    private static function alternation(string $regex, int &$position, int $optionalRepeats): ?string
    {
        $branches = [self::concatenation($regex, $position, $optionalRepeats)];
        while (($regex[$position] ?? '') === '|') {
            $position++;
            $branches[] = self::concatenation($regex, $position, $optionalRepeats);
        }

        foreach ($branches as $branch) {
            if ($branch !== null) {
                return $branch;
            }
        }

        return null;
    }

    /** A run of quantified atoms, up to the end of the enclosing group or alternation. */
    private static function concatenation(string $regex, int &$position, int $optionalRepeats): ?string
    {
        $out    = '';
        $length = strlen($regex);
        while ($position < $length && $regex[$position] !== '|' && $regex[$position] !== ')') {
            $piece = self::quantified($regex, $position, $optionalRepeats);
            if ($piece === null) {
                return null;
            }
            $out .= $piece;
        }

        return $out;
    }

    /** One atom, repeated the fewest times its quantifier allows. */
    private static function quantified(string $regex, int &$position, int $optionalRepeats): ?string
    {
        $atom = self::atom($regex, $position, $optionalRepeats);
        if ($atom === null) {
            return null;
        }

        $minimum    = 1;
        $quantified = false;
        $character  = $regex[$position] ?? '';
        if ($character === '*' || $character === '?') {
            $position++;
            $minimum    = $optionalRepeats;
            $quantified = true;
        } elseif ($character === '+') {
            $position++;
            $quantified = true;
        } elseif ($character === '{') {
            $close = strpos($regex, '}', $position);
            if ($close === false) {
                return null;
            }
            $specification = substr($regex, $position + 1, $close - $position - 1);
            if (preg_match('/^\d+(?:,\d*)?$/', $specification) !== 1) {
                return null;
            }
            $minimum    = (int) substr($specification, 0, strcspn($specification, ','));
            $position   = $close + 1;
            $quantified = true;
        }

        // A lazy (`?`) or possessive (`+`) suffix changes which match is
        // preferred, never which strings match, so it is consumed and ignored.
        if ($quantified && (($regex[$position] ?? '') === '?' || ($regex[$position] ?? '') === '+')) {
            $position++;
        }

        return $minimum === 0 ? '' : str_repeat($atom, $minimum);
    }

    /** A single atom: group, class, escape, dot, anchor or literal. */
    private static function atom(string $regex, int &$position, int $optionalRepeats): ?string
    {
        $length = strlen($regex);
        if ($position >= $length) {
            return null;
        }
        $character = $regex[$position];

        // Anchors contribute no characters. Route constraints are implicitly
        // anchored anyway, and several are written with them left in by a trim().
        if ($character === '^' || $character === '$') {
            $position++;
            return '';
        }

        if ($character === '(') {
            return self::group($regex, $position, $optionalRepeats);
        }

        if ($character === '[') {
            return self::characterClass($regex, $position);
        }

        if ($character === '\\') {
            $position++;
            if ($position >= $length) {
                return null;
            }
            $escaped = $regex[$position];
            $position++;
            return self::escape($escaped);
        }

        if ($character === '.') {
            $position++;
            return 'a';
        }

        $position++;
        return $character;
    }

    /** A parenthesised group, capturing or otherwise. */
    private static function group(string $regex, int &$position, int $optionalRepeats): ?string
    {
        $position++; // '('
        $lookaround = false;

        if (($regex[$position] ?? '') === '?') {
            $after = $regex[$position + 1] ?? '';
            if ($after === ':') {
                $position += 2;
            } elseif ($after === '=' || $after === '!') {
                $position  += 2;
                $lookaround = true;
            } elseif ($after === '<' && in_array($regex[$position + 2] ?? '', ['=', '!'], true)) {
                $position  += 3;
                $lookaround = true;
            } elseif ($after === '<' || $after === "'") {
                $closer = $after === '<' ? '>' : "'";
                $end    = strpos($regex, $closer, $position);
                if ($end === false) {
                    return null;
                }
                $position = $end + 1;
            } elseif ($after === 'P' && ($regex[$position + 2] ?? '') === '<') {
                $end = strpos($regex, '>', $position);
                if ($end === false) {
                    return null;
                }
                $position = $end + 1;
            } else {
                // Inline modifiers, conditionals, atomic groups: bail rather than
                // guess. Verification would catch a bad guess, but a clean "no
                // sample" is a better answer than a lucky one.
                return null;
            }
        }

        $inner = self::alternation($regex, $position, $optionalRepeats);
        if ($inner === null || ($regex[$position] ?? '') !== ')') {
            return null;
        }
        $position++;

        // A lookaround matches a position, not text. Contributing its inner
        // sample would corrupt the string; contributing nothing may produce a
        // candidate that violates it, which verification then rejects.
        return $lookaround ? '' : $inner;
    }

    /** The character an escape sequence stands for. */
    private static function escape(string $escaped): string
    {
        return match ($escaped) {
            'd' => '0',
            'w' => 'a',
            's' => ' ',
            'D', 'S' => 'a',
            'W' => '-',
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            default => $escaped,
        };
    }

    /**
     * One character from a bracketed class.
     *
     * Takes the first member, so `[a-z0-9-]` yields `a` and `[0-9]` yields `0`.
     * Negated classes are guessed at from a small pool, and the members collected
     * for them are only the range *starts*, so the guess can be wrong — which is
     * what the verification in sample() is for.
     */
    private static function characterClass(string $regex, int &$position): ?string
    {
        $length = strlen($regex);
        $position++; // '['
        $negated = false;
        if (($regex[$position] ?? '') === '^') {
            $negated = true;
            $position++;
        }

        $members = [];
        $first   = true;
        while ($position < $length) {
            // A `]` in first position is a literal member, not the terminator.
            if ($regex[$position] === ']' && ! $first) {
                break;
            }
            $first = false;

            if ($regex[$position] === '\\') {
                $position++;
                if ($position >= $length) {
                    return null;
                }
                $member = self::escape($regex[$position]);
                $position++;
            } else {
                $member = $regex[$position];
                $position++;
            }
            $members[] = $member;

            // A range `a-z`: consume the dash and its endpoint, keeping the start
            // as the member. A trailing dash before `]` is a literal member and is
            // left where it is.
            if (($regex[$position] ?? '') === '-' && ($regex[$position + 1] ?? ']') !== ']') {
                $position++;
                if (($regex[$position] ?? '') === '\\') {
                    $position++;
                }
                $position++;
            }
        }

        if (($regex[$position] ?? '') !== ']') {
            return null;
        }
        $position++;

        if (! $negated) {
            return $members[0] ?? null;
        }

        foreach (['a', '1', 'x', '-', '_', 'Z'] as $candidate) {
            if (! in_array($candidate, $members, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
