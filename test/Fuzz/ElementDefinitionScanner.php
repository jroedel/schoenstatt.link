<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

/**
 * Finds `filters` and `validators` keys written into an **element definition**,
 * where laminas-form throws them away without a word.
 *
 * This is the one check in the harness that has to be static, and it is static
 * for a reason that is easy to miss: by the time the form object exists, the
 * evidence is gone. `Laminas\Form\Factory::configureElement()` reads exactly
 * `name`, `type`, `options` and `attributes` from the spec array it is handed;
 * every other key is dropped on the floor. So this:
 *
 *     $this->add([
 *         'name'       => 'lastName',
 *         'type'       => 'Text',
 *         'validators' => [['name' => StringLength::class, 'options' => ['max' => 100]]],
 *     ]);
 *
 * is not a validated field with a 100-character bound. It is an unvalidated
 * field, and the author's intent is recorded nowhere the runtime can see. There
 * is no exception, no deprecation, no log line — the field is simply naked, and
 * it *looks* protected to anyone reading the source. Validators belong in the
 * form's `getInputFilterSpecification()`, keyed by element name.
 *
 * Detection walks PHP's own token stream rather than matching brackets with a
 * regex, because the argument to `add()` is a nested array literal several levels
 * deep and the interesting keys live at *exactly* depth one: `'validators'`
 * nested inside `'options'` is a different (legitimate) thing, and a regex cannot
 * tell those apart. The token walk also survives the codebase's habit of
 * scattering comments and commented-out keys through these arrays.
 *
 * The scan covers `$this->add([...])` and `$fieldset->add([...])`. Every `add()`
 * call in `module/&ast;/src/Form/` today passes a literal array, so coverage is
 * complete — but `unanalyzableCalls()` reports any call passing a *variable*
 * instead, so the day someone writes `$this->add($spec)` the harness says so out
 * loud rather than quietly returning a clean result.
 */
final class ElementDefinitionScanner
{
    /**
     * Keys that laminas-form's element factory silently discards but which a
     * reader plausibly expects to take effect. `required` is deliberately absent:
     * it is discarded too, but it is discarded from `options` where the codebase
     * writes it *as documentation*, and the input filter spec is what decides.
     */
    private const DEAD_KEYS = ['filters', 'validators'];

    /** @var list<string> */
    private array $findings = [];

    /** @var list<string> */
    private array $unanalyzable = [];

    /**
     * @param list<string> $files absolute paths to form/fieldset sources
     */
    public function __construct(private readonly array $files)
    {
    }

    /**
     * One entry per dead key, as `relative/path.php: element "name" carries a
     * dead 'validators' key`. Sorted, so it is diffable and baseline-able.
     *
     * @return list<string>
     */
    public function findings(): array
    {
        $this->scan();

        return $this->findings;
    }

    /**
     * `add()` calls this scanner cannot see into. Empty today; a non-empty result
     * means the coverage claim in the class docblock has expired.
     *
     * @return list<string>
     */
    public function unanalyzableCalls(): array
    {
        $this->scan();

        return $this->unanalyzable;
    }

    private bool $scanned = false;

    private function scan(): void
    {
        if ($this->scanned) {
            return;
        }
        $this->scanned = true;

        foreach ($this->files as $file) {
            $source = file_get_contents($file);
            if (false === $source) {
                continue;
            }
            $this->scanSource($file, $source);
        }

        sort($this->findings);
        sort($this->unanalyzable);
    }

    private function scanSource(string $file, string $source): void
    {
        $tokens   = token_get_all($source);
        $relative = self::relativePath($file);
        $count    = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! self::isAddCallAt($tokens, $i)) {
                continue;
            }

            // $i points at the `->`; advance past `add` and `(`.
            $cursor = self::skipTrivia($tokens, $i + 1);          // `add`
            $cursor = self::skipTrivia($tokens, $cursor + 1);     // `(`
            if (! self::isChar($tokens[$cursor] ?? null, '(')) {
                continue;
            }

            $argument = self::skipTrivia($tokens, $cursor + 1);
            $token    = $tokens[$argument] ?? null;

            $isArrayLiteral = self::isChar($token, '[')
                || (is_array($token) && $token[0] === T_ARRAY);

            if (! $isArrayLiteral) {
                $line             = is_array($token) ? $token[2] : 0;
                $this->unanalyzable[] = sprintf(
                    '%s:%d add() called with something other than an array literal',
                    $relative,
                    $line
                );
                continue;
            }

            $this->scanDefinition($tokens, $argument, $relative);
        }
    }

    /**
     * Walk one element-definition array literal, collecting the `name` and any
     * dead key found at depth 1.
     */
    private function scanDefinition(array $tokens, int $start, string $relative): void
    {
        $count    = count($tokens);
        $depth    = 0;
        $name     = null;
        $dead     = [];
        $firstDeadLine = 0;

        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];

            if (self::isChar($token, '[') || (is_array($token) && $token[0] === T_ARRAY)) {
                // `array(` counts once: the following `(` is consumed below.
                if (is_array($token) && $token[0] === T_ARRAY) {
                    $paren = self::skipTrivia($tokens, $i + 1);
                    if (self::isChar($tokens[$paren] ?? null, '(')) {
                        $i = $paren;
                    }
                }
                $depth++;
                continue;
            }

            if (self::isChar($token, ']') || self::isChar($token, ')')) {
                $depth--;
                if ($depth <= 0) {
                    break;
                }
                continue;
            }

            if ($depth !== 1 || ! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $arrow = self::skipTrivia($tokens, $i + 1);
            if (! is_array($tokens[$arrow] ?? null) || $tokens[$arrow][0] !== T_DOUBLE_ARROW) {
                continue;
            }

            $key = trim($token[1], "'\"");

            if ('name' === $key) {
                $value = self::skipTrivia($tokens, $arrow + 1);
                if (is_array($tokens[$value] ?? null) && $tokens[$value][0] === T_CONSTANT_ENCAPSED_STRING) {
                    $name = trim($tokens[$value][1], "'\"");
                }
                continue;
            }

            if (in_array($key, self::DEAD_KEYS, true)) {
                $dead[]        = $key;
                $firstDeadLine = $firstDeadLine ?: $token[2];
            }
        }

        foreach ($dead as $key) {
            $this->findings[] = sprintf(
                "%s:%d element '%s' carries a dead '%s' key in its element definition",
                $relative,
                $firstDeadLine,
                $name ?? '<unnamed>',
                $key
            );
        }
    }

    /** True when $tokens[$i] is the `->` of a `->add(` call. */
    private static function isAddCallAt(array $tokens, int $i): bool
    {
        $token = $tokens[$i];
        $arrow = is_array($token)
            ? in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)
            : false;

        if (! $arrow) {
            return false;
        }

        $method = self::skipTrivia($tokens, $i + 1);
        if (! is_array($tokens[$method] ?? null) || $tokens[$method][0] !== T_STRING) {
            return false;
        }
        if ('add' !== $tokens[$method][1]) {
            return false;
        }

        $paren = self::skipTrivia($tokens, $method + 1);

        return self::isChar($tokens[$paren] ?? null, '(');
    }

    private static function skipTrivia(array $tokens, int $i): int
    {
        $count = count($tokens);
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $i++;
                continue;
            }
            break;
        }

        return $i;
    }

    private static function isChar(array|string|null $token, string $char): bool
    {
        return is_string($token) && $token === $char;
    }

    private static function relativePath(string $file): string
    {
        $root = dirname(__DIR__, 2) . '/';

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }
}
