<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class PeriodConstraintValidator extends ConstraintValidator
{
    private const PATTERN = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PeriodConstraint) {
            throw new UnexpectedTypeException($constraint, PeriodConstraint::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!preg_match(self::PATTERN, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
