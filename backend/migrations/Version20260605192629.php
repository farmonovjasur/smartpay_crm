<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260605192629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_clients_last_paid_period ON clients');
        $this->addSql('ALTER TABLE clients ADD phone2 VARCHAR(20) DEFAULT NULL, CHANGE last_paid_period last_paid_period VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE debts CHANGE is_active is_active TINYINT GENERATED ALWAYS AS (CASE WHEN status=\'active\' THEN 1 ELSE NULL END) STORED');
        $this->addSql('ALTER TABLE invoices CHANGE total_amount total_amount NUMERIC(15, 2) DEFAULT \'0\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE clients DROP phone2, CHANGE last_paid_period last_paid_period CHAR(7) DEFAULT NULL COMMENT \'Mijozning oxirgi to\'\'langan oyi (YYYY-MM). Yangi mijoz uchun NULL.\'');
        $this->addSql('CREATE INDEX idx_clients_last_paid_period ON clients (last_paid_period)');
        $this->addSql('ALTER TABLE debts CHANGE is_active is_active TINYINT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices CHANGE total_amount total_amount NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
    }
}
