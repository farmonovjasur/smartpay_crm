<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class PhoneConstraint extends Constraint
{
    public string $message = 'Telefon raqam "{{ value }}" noto\'g\'ri formatda. +998XXXXXXXXX formatida bo\'lishi kerak.';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct([], $groups, $payload);

        if ($message !== null) {
            $this->message = $message;
        }
    }
}
