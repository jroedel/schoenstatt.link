<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;

use function dirname;
use function end;
use function explode;
use function file_get_contents;
use function implode;
use function is_array;
use function is_dir;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function token_get_all;

use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_INLINE_HTML;

/**
 * No first-party code reaches `Laminas\Db\TableGateway\Feature\GlobalAdapterFeature`.
 *
 * That class is a process-global registry of db adapters. Until 2026-08-14 it was the
 * only route by which four forms — `JUser\Form\EditUserForm`, `CreateRoleForm`,
 * `DeleteUserForm` and `JTranslate\Form\EditPhraseForm` — reached the adapter their
 * `NoRecordExists`/`RecordExists` validators need, and it was populated in exactly one
 * place: `JUser\Module::onBootstrap()`. So the forms worked inside a booted laminas-mvc
 * request and nowhere else, which is why they were the last thing blocking the
 * `juser/*` and `jtranslate/*` routes from being ported to the Symfony kernel.
 *
 * ## Why a source scan rather than a behavioural test
 *
 * Because the failure this guards against is not a behaviour. Re-introducing the
 * registry read would keep every existing test green: the forms are always built from
 * the container or from a factory that has an adapter to hand, and under laminas-mvc
 * `onBootstrap()` would be there to fill the registry again. What breaks is only the
 * *other* callers — the Symfony kernel, `bin/console`, the fuzz harness — and only the
 * ones nobody has written yet. A test that says "and nothing reads the global" is the
 * only kind that fails at the moment the mistake is made.
 *
 * The three failure modes it covers, all measured before the change:
 *
 * - `CreateRoleForm`, `DeleteUserForm`, `EditPhraseForm` threw
 *   `RuntimeException: No database adapter was found in the static registry` from
 *   `getInputFilterSpecification()`, for every input including benign ones.
 * - `EditUserForm` did something worse, because its two reads sat inside a
 *   `try { … } catch (\Exception $e) {}`: it silently dropped the uniqueness checks on
 *   `username` and `display_name`, so a create-user POST carrying an existing username
 *   validated clean.
 * - `JTranslate\Form\PhraseValidator` papered over the first mode by writing its own
 *   injected adapter into the registry before building the form — honestly documented
 *   as the wrong answer, and the write this test now forbids.
 *
 * ## What changed when laminas-db left (2026-09-22)
 *
 * The class no longer exists — `laminas/laminas-db` is not installed, and the db
 * layer is `SionModel\Db\*`. So a reintroduced read would now be a fatal rather
 * than the silence described above, and this scan is cheaper than the fatal. It is
 * kept because the invariant it states is still the one that matters: a form reaches
 * its connection through a constructor argument, never through a process-global.
 *
 * ## It reads tokens, not text
 *
 * The class name still appears in prose: several of the docblocks explaining the
 * change name it, including this one. A `grep` would fail on those, so the scan
 * tokenizes and skips comments — which also means it cannot be defeated by breaking
 * the reference across lines or aliasing the import, since the `use` statement is
 * itself a token match.
 */
class NoStaticDbAdapterTest extends TestCase
{
    private const FORBIDDEN = 'GlobalAdapterFeature';

    /**
     * First-party PHP, which is what this project can hold to a rule.
     *
     * `vendor/` is excluded because laminas-db defines the class and laminas's own
     * TableGateway feature set uses it; that is not ours to change. The three
     * submodules are included: they are first-party libraries vendored as git
     * submodules, and JUser and JTranslate are where the reads lived.
     *
     * @return list<string>
     */
    private static function roots(): array
    {
        return [
            __DIR__ . '/../../src',
            __DIR__ . '/../../module',
            __DIR__ . '/../../config',
            __DIR__ . '/../../test',
            __DIR__ . '/../../tools',
            __DIR__ . '/../../bin',
        ];
    }

    public function testNoFirstPartyCodeReferencesTheStaticAdapterRegistry(): void
    {
        $offenders = [];

        foreach (self::phpFiles() as $file) {
            $source = file_get_contents($file);
            if (false === $source) {
                continue;
            }

            foreach (self::codeTokens($source) as $line => $text) {
                if (self::FORBIDDEN === $text) {
                    $offenders[] = sprintf('%s:%d', self::relative($file), $line);
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "GlobalAdapterFeature is reachable from first-party code again:\n%s\n\n"
            . 'The adapter is a constructor argument on every form that needs one'
            . ' (JUser\Service\DbAdapterResolver resolves it in a factory). A form that'
            . ' reads the static registry instead works only inside a booted laminas-mvc'
            . ' request, and fails silently in EditUserForm\'s case.',
            implode("\n", $offenders)
        ));
    }

    /**
     * Every token that is not a comment, with its line number.
     *
     * Both halves of a qualified name are yielded separately by the tokenizer in the
     * `\Laminas\Db\...\GlobalAdapterFeature::` form and as one `T_NAME_QUALIFIED`
     * token in others, so the check below matches on the *last* segment rather than on
     * the whole name. That is also what makes an aliased import
     * (`use …\GlobalAdapterFeature as Registry;`) caught: the `use` line still names it.
     *
     * @return iterable<int, string>
     */
    private static function codeTokens(string $source): iterable
    {
        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                continue;
            }

            [$id, $text, $line] = $token;

            if (T_COMMENT === $id || T_DOC_COMMENT === $id || T_INLINE_HTML === $id) {
                continue;
            }

            $segments = explode('\\', $text);

            yield $line => (string) end($segments);
        }
    }

    /** @return iterable<string> */
    private static function phpFiles(): iterable
    {
        foreach (self::roots() as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $path = $file->getPathname();

                //vendor/ can appear under a submodule; it is not ours either.
                if (str_contains($path, '/vendor/') || str_contains($path, '/data/')) {
                    continue;
                }

                //This file names the class it forbids, in a constant rather than a
                //comment, so it has to exempt itself explicitly.
                if (__FILE__ === $path) {
                    continue;
                }

                if (str_ends_with($path, '.php')) {
                    yield $path;
                }
            }
        }
    }

    private static function relative(string $path): string
    {
        $root = dirname(__DIR__, 2) . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
