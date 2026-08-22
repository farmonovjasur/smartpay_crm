<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260820200613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ledger_payments (id INT UNSIGNED AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, created_at DATETIME NOT NULL, ledger_entry_id INT UNSIGNED NOT NULL, created_by INT UNSIGNED DEFAULT NULL, INDEX IDX_34C29EC8DE12AB56 (created_by), INDEX idx_ledger_payment_entry (ledger_entry_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ledger_payments ADD CONSTRAINT FK_34C29EC8EB264CB8 FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ledger_payments ADD CONSTRAINT FK_34C29EC8DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('DROP TABLE app_config');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE debts CHANGE is_active is_active TINYINT GENERATED ALWAYS AS (CASE WHEN status IN (\'active\', \'partial\') THEN 1 ELSE NULL END) STORED');
        $this->addSql('ALTER TABLE invoices CHANGE total_amount total_amount NUMERIC(15, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE ledger_entries CHANGE paid_at paid_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE ledger_entries RENAME INDEX fk_ledger_paid_by TO IDX_E3FD73F48B380FF2');
        $this->addSql('ALTER TABLE ledger_entries RENAME INDEX fk_ledger_created TO IDX_E3FD73F4DE12AB56');
        $this->addSql('ALTER TABLE ledger_items CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE prepayments CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE prepayments RENAME INDEX fk_prepay_user TO IDX_711D360ADE12AB56');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_config (`key` VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, value TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY (`key`)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, headers LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, queue_name VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E016BA31DB (delivered_at), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E0FB7336F0 (queue_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE ledger_payments DROP FOREIGN KEY FK_34C29EC8EB264CB8');
        $this->addSql('ALTER TABLE ledger_payments DROP FOREIGN KEY FK_34C29EC8DE12AB56');
        $this->addSql('DROP TABLE ledger_payments');
        $this->addSql('ALTER TABLE debts CHANGE is_active is_active TINYINT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices CHANGE total_amount total_amount NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE ledger_entries CHANGE paid_at paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ledger_entries RENAME INDEX idx_e3fd73f4de12ab56 TO fk_ledger_created');
        $this->addSql('ALTER TABLE ledger_entries RENAME INDEX idx_e3fd73f48b380ff2 TO fk_ledger_paid_by');
        $this->addSql('ALTER TABLE ledger_items CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE prepayments CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE prepayments RENAME INDEX idx_711d360ade12ab56 TO fk_prepay_user');
    }
}
