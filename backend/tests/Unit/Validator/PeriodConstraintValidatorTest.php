<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\PeriodConstraint;
use App\Validator\PeriodConstraintValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class PeriodConstraintValidatorTest extends TestCase
{
    private PeriodConstraintValidator $validator;
    private ExecutionContextInterface $context;
    private PeriodConstraint $constraint;

    protected function setUp(): void
    {
        $this->validator = new PeriodConstraintValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->constraint = new PeriodConstraint();
        $this->validator->initialize($this->context);
    }

    public function testValidPeriodJanuary(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('2026-01', $this->constraint);
    }

    public function testValidPeriodDecember(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('2026-12', $this->constraint);
    }

    public function testValidPeriodSeptember(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('2024-09', $this->constraint);
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

    public function testMonth00IsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('2026-00', $this->constraint);
    }

    public function testMonth13IsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('2026-13', $this->constraint);
    }

    public function testMissingLeadingZeroIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('2026-1', $this->constraint);
    }

    public function testWrongSeparatorIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('2026/05', $this->constraint);
    }

    public function testShortYearIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('26-05', $this->constraint);
    }

    public function testFullDateIsInvalid(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $this->validator->validate('2026-05-01', $this->constraint);
    }
}
