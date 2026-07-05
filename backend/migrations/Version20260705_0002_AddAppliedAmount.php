<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705_0002_AddAppliedAmount extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add applied_amount column to payments table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payments ADD applied_amount DECIMAL(15, 2) DEFAULT NULL');
        $this->addSql('UPDATE payments SET applied_amount = amount');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payments DROP COLUMN applied_amount');
    }
}
