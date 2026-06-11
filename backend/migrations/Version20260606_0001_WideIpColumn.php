<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606_0001_WideIpColumn extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen audit_logs.ip column from VARCHAR(45) to VARCHAR(255) to store full IP chain';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_logs MODIFY ip VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_logs MODIFY ip VARCHAR(45) DEFAULT NULL');
    }
}
