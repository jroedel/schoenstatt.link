<?php

namespace SchoenstattTest\Integration;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\ToNull;
use Laminas\Form\Element;
use Laminas\InputFilter\InputProviderInterface;
use PHPUnit\Framework\TestCase;
use SionModel\Form\Element\Phone;
use SionModel\Validator\Phone as PhoneValidator;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Characterization test for SionModel\Form\Element\Phone, which used to extend
 * Laminas\Form\Element\Tel (now `@final`). Tel contributed nothing but the
 * type="tel" attribute — this element already overrode getInputSpecification()
 * wholesale and supplied its own validator — so it was moved onto the still-open
 * Laminas\Form\Element.
 *
 * Written against the pre-change code and passing there.
 *
 * Needs vendor/ (laminas-form), so it runs in the capsule:
 * php composer.phar integration
 */
class PhoneElementContractTest extends TestCase
{
    /**
     * The one thing Tel actually provided.
     */
    public function testRendersAsATelephoneInput(): void
    {
        $element = new Phone('homePhone');

        self::assertSame('tel', $element->getAttribute('type'));
        self::assertSame('homePhone', $element->getAttribute('name'));
    }

    public function testIsStillAFormElement(): void
    {
        $element = new Phone('homePhone');

        self::assertInstanceOf(Element::class, $element);
        self::assertInstanceOf(InputProviderInterface::class, $element);
    }

    /**
     * The input specification is what the InputFilter consumes; every part of
     * it matters for server-side validation.
     */
    public function testInputSpecificationIsUnchanged(): void
    {
        $spec = (new Phone('homePhone'))->getInputSpecification();

        self::assertSame('homePhone', $spec['name']);
        self::assertFalse($spec['required']);
        self::assertSame(
            [StringTrim::class, StripNewlines::class, ToNull::class],
            array_column($spec['filters'], 'name')
        );
    }

    public function testInputSpecificationCarriesThePhoneValidator(): void
    {
        $spec = (new Phone('homePhone'))->getInputSpecification();

        self::assertCount(1, $spec['validators']);
        self::assertInstanceOf(PhoneValidator::class, $spec['validators'][0]);
    }

    /**
     * The validator is memoized — getValidator() must not build a new one per
     * call, since the input spec is rebuilt on every form bind.
     */
    public function testValidatorInstanceIsReused(): void
    {
        $element = new Phone('homePhone');

        $first  = $element->getInputSpecification()['validators'][0];
        $second = $element->getInputSpecification()['validators'][0];

        self::assertSame($first, $second);
    }

    /**
     * End-to-end through the element's own validator, so the spec is not just
     * shaped right but actually rejects bad input.
     */
    public function testSuppliedValidatorAcceptsAndRejects(): void
    {
        $validator = (new Phone('homePhone'))->getInputSpecification()['validators'][0];

        self::assertTrue($validator->isValid('+1 (555) 123-4567'));
        self::assertFalse($validator->isValid('555-1234'));
    }
}
