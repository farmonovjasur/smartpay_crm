<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530231206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE debts CHANGE is_active is_active TINYINT GENERATED ALWAYS AS (CASE WHEN status=\'active\' THEN 1 ELSE NULL END) STORED');
        $this->addSql('ALTER TABLE invoices CHANGE total_amount total_amount NUMERIC(15, 2) DEFAULT \'0\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE debts CHANGE is_active is_active TINYINT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices CHANGE total_amount total_amount NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
    }
}
