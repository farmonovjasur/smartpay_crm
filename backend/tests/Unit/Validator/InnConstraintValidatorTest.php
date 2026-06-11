<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\InnConstraint;
use App\Validator\InnConstraintValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class InnConstraintValidatorTest extends TestCase
{
    private InnConstraintValidator $validator;
    private ExecutionContextInterface $context;
    private InnConstraint $constraint;

    protected function setUp(): void
    {
        $this->validator = new InnConstraintValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->constraint = new InnConstraint();
        $this->validator->initialize($this->context);
    }

    public function testValidNineDigitInn(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('123456789', $this->constraint);
    }

    public function testValidFourteenDigitInn(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('12345678901234', $this->constraint);
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

    public function testTooShortInnIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('12345678', $this->constraint);
    }

    public function testTooLongInnIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('123456789012345', $this->constraint);
    }

    public function testTenDigitInnIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('1234567890', $this->constraint);
    }

    public function testInnWithLettersIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('12345678a', $this->constraint);
    }

    public function testInnWithSpacesIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('123 456 78', $this->constraint);
    }
}
