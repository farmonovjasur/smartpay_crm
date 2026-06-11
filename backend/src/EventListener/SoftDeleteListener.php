<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL Filter that automatically adds `deleted_at IS NULL` condition
 * to all queries on entities that have a `deleted_at` column.
 *
 * Applies to: clients, users, invoices tables.
 */
class SoftDeleteListener extends SQLFilter
{
    private const SOFT_DELETABLE_TABLES = [
        'clients',
        'users',
        'invoices',
    ];

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        $tableName = $targetEntity->getTableName();

        if (!in_array($tableName, self::SOFT_DELETABLE_TABLES, true)) {
            return '';
        }

        return sprintf('%s.deleted_at IS NULL', $targetTableAlias);
    }
}
