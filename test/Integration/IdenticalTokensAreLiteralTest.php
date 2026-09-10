<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Books\LibraryDeleteForm;
use Laminas\Form\Form;
use Laminas\Validator\Identical;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;

use function implode;
use function is_array;
use function is_string;
use function method_exists;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * An `Identical` token is a value to compare against, never a key into the submission.
 *
 * ## The default, and what it cost
 *
 * `Laminas\Validator\Identical::isValid()` begins:
 *
 *     if (! $this->getLiteral() && $context !== null) {
 *         ...
 *         $token = $context[$token];
 *     }
 *
 * `literal` defaults to **false**, and `BaseInputFilter::validateInputs()` passes
 * `array_merge($this->getRawValues(), $data)` as the context — the submitted request. So
 * a token that happens to name a submitted field is replaced by that field's value, and
 * the submitter chooses both sides of the comparison.
 *
 * Two validators in this application were written that way, and both were defeated by
 * adding one field to the POST:
 *
 *   - `App\Books\LibraryDeleteForm` asks for the library's name to be typed before it
 *     deletes up to 16,383 books with no undo. `library_name=x&Bellavista=x` satisfied it.
 *   - `Books\Form\TextForm` pins `kind` to `jk-text`. `kind=other&jk-text=other` stored
 *     any kind at all.
 *
 * Neither is reachable without a CSRF token and the row's own permission, so this was a
 * confirmation and an enumeration that could be walked past rather than an open door. The
 * confirmation is the whole of what that form does.
 *
 * ## Why the rule is asserted over every form rather than fixed twice
 *
 * Because nothing about the failure is visible at the call site. A spec reading
 * `['token' => $this->expectedName, 'strict' => true]` looks like the careful version —
 * `strict` is even there, guarding a smaller hole in the same comparison — and the
 * dangerous half is the option that is *absent*. The next person writing an `Identical`
 * will write exactly that unless something says otherwise.
 *
 * A token that really is meant to name another field (a "repeat your password" check)
 * would have to say `literal => false` explicitly, which is the point: it becomes a
 * decision in the source instead of a default nobody chose.
 */
final class IdenticalTokensAreLiteralTest extends TestCase
{
    public function testNoFormComparesAgainstAValueTheSubmissionCanChoose(): void
    {
        $offenders = [];

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof Form || ! method_exists($form, 'getInputFilterSpecification')) {
                continue;
            }

            /** @var mixed $spec */
            $spec = $form->getInputFilterSpecification();
            if (! is_array($spec)) {
                continue;
            }

            foreach ($spec as $field => $rules) {
                if (! is_array($rules)) {
                    continue;
                }
                foreach ((array) ($rules['validators'] ?? []) as $validator) {
                    if (! is_array($validator) || ! self::isIdentical($validator['name'] ?? null)) {
                        continue;
                    }
                    /** @var array<string, mixed> $options */
                    $options = is_array($validator['options'] ?? null) ? $validator['options'] : [];
                    if (true === ($options['literal'] ?? false)) {
                        continue;
                    }

                    $offenders[] = sprintf('%s::%s', $class, (string) $field);
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "An Identical validator without 'literal' => true reads its token as a key into the "
            . "submitted data, so the submitter chooses what the value is compared against. Add "
            . "'literal' => true, or state 'literal' => false if naming another field is really "
            . "the intent:\n  " . implode("\n  ", $offenders)
        );
    }

    /**
     * The behaviour itself, on the form where it mattered most.
     *
     * Asserted separately from the rule above because a spec-shape check proves the option
     * is written down, not that writing it down does anything. This one deletes nothing: it
     * asks the form the same question the controller asks.
     */
    public function testTheLibraryConfirmationCannotBeSatisfiedByAnExtraField(): void
    {
        $form = new LibraryDeleteForm('Bellavista');
        $form->setData([
            LibraryDeleteForm::NAME_FIELD => 'not the library name',
            //The field whose name is the token. Before `literal => true` this made the
            //validator compare 'not the library name' against itself.
            'Bellavista'                  => 'not the library name',
            //A token the form will accept, the way a browser posts one:
            //`Csrf::getValue()` is the hash the element renders into the page. The
            //confirmation is what is under test, so the token has to be answered rather
            //than removed — and answering it exercises the check instead of skipping it.
            'security'                    => $form->get('security')->getValue(),
        ]);

        self::assertFalse($form->isValid(), 'a wrong name was accepted because the request said so');

        $correct = new LibraryDeleteForm('Bellavista');
        $correct->setData([
            LibraryDeleteForm::NAME_FIELD => 'Bellavista',
            'security'                    => $correct->get('security')->getValue(),
        ]);

        self::assertTrue($correct->isValid(), 'the right name stopped being accepted');
    }

    private static function isIdentical(mixed $name): bool
    {
        return is_string($name) && (Identical::class === $name || 'Identical' === $name);
    }
}
