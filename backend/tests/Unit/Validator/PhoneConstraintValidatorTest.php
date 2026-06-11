<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\PhoneConstraint;
use App\Validator\PhoneConstraintValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class PhoneConstraintValidatorTest extends TestCase
{
    private PhoneConstraintValidator $validator;
    private ExecutionContextInterface $context;
    private PhoneConstraint $constraint;

    protected function setUp(): void
    {
        $this->validator = new PhoneConstraintValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->constraint = new PhoneConstraint();
        $this->validator->initialize($this->context);
    }

    public function testValidPhone(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('+998901234567', $this->constraint);
    }

    public function testNullValueIsValid(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate(null, $this->constraint);
    }

    public function testEmptyStringIsValid(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('', $this->constraint);
    }

    public function testPhoneWithoutPlusIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('998901234567', $this->constraint);
    }

    public function testPhoneWithWrongPrefixIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('+997901234567', $this->constraint);
    }

    public function testPhoneTooShortIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('+99890123456', $this->constraint);
    }

    public function testPhoneTooLongIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('+9989012345678', $this->constraint);
    }

    public function testPhoneWithLettersIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('+998abcdefghi', $this->constraint);
    }
}
