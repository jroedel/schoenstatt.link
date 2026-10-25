<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Books\CheckoutForms;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Books/CheckoutForms.php';

/**
 * Who a `library-borrowers` library may lend to (docs/libraries.md, "The person list").
 *
 * Membership is read off loan history, so the rule has three ways to be wrong that all
 * still render a plausible dropdown: offering a borrower who belongs to another library,
 * hiding a new borrower who has not borrowed anywhere yet, and offering a person whose
 * "borrower" tick was taken away. Each is a case here. The history itself is the database's
 * (LibraryBorrowingHistoryTest); this is the rule applied to it.
 *
 * Requires the class file directly: the method under test touches nothing outside it.
 */
class LibraryBorrowersRuleTest extends TestCase
{
    private const PERSONS = [
        10 => ['fullName' => 'Borrowed here', 'isBorrower' => true],
        11 => ['fullName' => 'Borrowed here and elsewhere', 'isBorrower' => true],
        12 => ['fullName' => 'Borrowed only elsewhere', 'isBorrower' => true],
        13 => ['fullName' => 'Never borrowed', 'isBorrower' => true],
        14 => ['fullName' => 'Borrowed here, unticked', 'isBorrower' => false],
        15 => ['fullName' => 'Never borrowed, unticked', 'isBorrower' => false],
        16 => ['fullName' => 'Default checkout person', 'isBorrower' => false],
    ];

    private const HISTORY = [10 => true, 11 => true, 12 => false, 14 => true, 16 => true];

    public function testTheLibraryOffersItsOwnBorrowersNewBorrowersAndItsDefaultPerson(): void
    {
        self::assertSame(
            [
                10 => 'Borrowed here',
                11 => 'Borrowed here and elsewhere',
                13 => 'Never borrowed',
                16 => 'Default checkout person',
            ],
            CheckoutForms::eligibleBorrowers(self::PERSONS, self::HISTORY, 16)
        );
    }

    /** The default person is offered for being the default, not for anything in its row. */
    public function testWithoutADefaultPersonOnlyTickedBorrowersAreOffered(): void
    {
        self::assertSame(
            [10 => 'Borrowed here', 11 => 'Borrowed here and elsewhere', 13 => 'Never borrowed'],
            CheckoutForms::eligibleBorrowers(self::PERSONS, self::HISTORY, null)
        );
    }

    /**
     * The order the persons arrive in is the order offered: getUnlinkedPersons() sorts by
     * surname, and a dropdown of seventy names is only usable sorted.
     */
    public function testTheOrderOfThePersonsIsKept(): void
    {
        $persons = [
            13 => ['fullName' => 'Ana Zubizarreta', 'isBorrower' => true],
            10 => ['fullName' => 'Pablo Arce', 'isBorrower' => true],
        ];

        self::assertSame([13, 10], array_keys(CheckoutForms::eligibleBorrowers($persons, [], null)));
    }
}
