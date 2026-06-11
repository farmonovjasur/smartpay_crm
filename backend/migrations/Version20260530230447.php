<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530230447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, action VARCHAR(100) NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id VARCHAR(50) DEFAULT NULL, details JSON DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT UNSIGNED DEFAULT NULL, INDEX idx_audit_user (user_id), INDEX idx_audit_entity (entity_type, entity_id), INDEX idx_audit_action (action), INDEX idx_audit_created (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client_monthly_status (id INT UNSIGNED AUTO_INCREMENT NOT NULL, period VARCHAR(7) NOT NULL, payment_status VARCHAR(10) NOT NULL, payment_method VARCHAR(10) DEFAULT NULL, payment_type_snapshot VARCHAR(10) NOT NULL, paid_at DATETIME DEFAULT NULL, notes VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, client_id INT UNSIGNED NOT NULL, invoice_id INT UNSIGNED DEFAULT NULL, debt_id INT UNSIGNED DEFAULT NULL, INDEX IDX_B963B45819EB6921 (client_id), INDEX IDX_B963B4582989F1FD (invoice_id), INDEX IDX_B963B458240326A5 (debt_id), INDEX idx_period (period), INDEX idx_status (payment_status), UNIQUE INDEX uniq_client_period (client_id, period), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE clients (id INT UNSIGNED AUTO_INCREMENT NOT NULL, inn VARCHAR(14) NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(20) NOT NULL, service_date DATE NOT NULL, payment_type VARCHAR(10) NOT NULL, product_count INT UNSIGNED NOT NULL, status VARCHAR(10) DEFAULT \'faol\' NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, INDEX idx_clients_payment_type (payment_type), INDEX idx_clients_status (status), INDEX idx_clients_deleted (deleted_at), INDEX idx_clients_name (name), UNIQUE INDEX uniq_clients_inn_alive (inn, deleted_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE config (config_key VARCHAR(100) NOT NULL, config_value LONGTEXT NOT NULL, description VARCHAR(255) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by INT UNSIGNED DEFAULT NULL, INDEX IDX_D48A2F7C16FE72E1 (updated_by), PRIMARY KEY (config_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE debts (id INT UNSIGNED AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, monthly_amount NUMERIC(15, 2) NOT NULL, months_overdue INT UNSIGNED DEFAULT 1 NOT NULL, first_overdue_period VARCHAR(7) NOT NULL, last_overdue_period VARCHAR(7) NOT NULL, payment_type_snapshot VARCHAR(10) NOT NULL, due_date DATE NOT NULL, paid_at DATETIME DEFAULT NULL, paid_method VARCHAR(10) DEFAULT NULL, status VARCHAR(10) DEFAULT \'active\' NOT NULL, is_active TINYINT GENERATED ALWAYS AS (CASE WHEN status=\'active\' THEN 1 ELSE NULL END) STORED, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INT UNSIGNED NOT NULL, paid_by INT UNSIGNED DEFAULT NULL, INDEX IDX_6F64A29B19EB6921 (client_id), INDEX IDX_6F64A29B8B380FF2 (paid_by), INDEX idx_debts_status (status), INDEX idx_debts_due_date (due_date), UNIQUE INDEX uniq_active_debt_per_client (client_id, is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice_items (id INT UNSIGNED AUTO_INCREMENT NOT NULL, client_name_snapshot VARCHAR(255) NOT NULL, client_inn_snapshot VARCHAR(14) NOT NULL, client_phone_snapshot VARCHAR(20) NOT NULL, payment_type_snapshot VARCHAR(10) NOT NULL, quantity INT UNSIGNED NOT NULL, unit_price NUMERIC(15, 2) NOT NULL, total_price NUMERIC(15, 2) NOT NULL, is_carried_debt TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, invoice_id INT UNSIGNED NOT NULL, client_id INT UNSIGNED NOT NULL, debt_id INT UNSIGNED DEFAULT NULL, INDEX IDX_DCC4B9F8240326A5 (debt_id), INDEX idx_inv_items_invoice (invoice_id), INDEX idx_inv_items_client (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoices (id INT UNSIGNED AUTO_INCREMENT NOT NULL, invoice_number VARCHAR(50) NOT NULL, period VARCHAR(7) NOT NULL, serial_no INT UNSIGNED NOT NULL, issue_date DATE NOT NULL, total_amount NUMERIC(15, 2) DEFAULT \'0\' NOT NULL, items_count INT UNSIGNED DEFAULT 0 NOT NULL, responsible_name VARCHAR(255) DEFAULT \'Halimov Bekzod\' NOT NULL, unit_price_snapshot NUMERIC(15, 2) NOT NULL, product_name_snapshot VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_by INT UNSIGNED DEFAULT NULL, INDEX IDX_6A2F2F95DE12AB56 (created_by), INDEX idx_invoices_issue_date (issue_date), UNIQUE INDEX uniq_invoice_number (invoice_number), UNIQUE INDEX uniq_invoice_period (period, deleted_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notifications (id INT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, title VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, link_url VARCHAR(500) DEFAULT NULL, is_read TINYINT DEFAULT 0 NOT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT UNSIGNED NOT NULL, INDEX IDX_6000B0D3A76ED395 (user_id), INDEX idx_notif_user_unread (user_id, is_read, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payments (id INT UNSIGNED AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, payment_method VARCHAR(10) NOT NULL, period VARCHAR(7) NOT NULL, paid_at DATETIME NOT NULL, notes VARCHAR(500) DEFAULT NULL, client_id INT UNSIGNED NOT NULL, debt_id INT UNSIGNED DEFAULT NULL, invoice_id INT UNSIGNED DEFAULT NULL, created_by INT UNSIGNED DEFAULT NULL, INDEX IDX_65D29B32240326A5 (debt_id), INDEX IDX_65D29B322989F1FD (invoice_id), INDEX IDX_65D29B32DE12AB56 (created_by), INDEX idx_payments_client (client_id), INDEX idx_payments_period (period), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE refresh_tokens (id INT UNSIGNED AUTO_INCREMENT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT UNSIGNED NOT NULL, INDEX IDX_9BACE7E1A76ED395 (user_id), INDEX idx_user_active (user_id, revoked_at), UNIQUE INDEX uniq_token_hash (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(10) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, last_login_at DATETIME DEFAULT NULL, last_login_ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, INDEX idx_users_role (role), INDEX idx_users_deleted (deleted_at), UNIQUE INDEX uniq_users_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE client_monthly_status ADD CONSTRAINT FK_B963B45819EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE client_monthly_status ADD CONSTRAINT FK_B963B4582989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE client_monthly_status ADD CONSTRAINT FK_B963B458240326A5 FOREIGN KEY (debt_id) REFERENCES debts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE config ADD CONSTRAINT FK_D48A2F7C16FE72E1 FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE debts ADD CONSTRAINT FK_6F64A29B19EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE debts ADD CONSTRAINT FK_6F64A29B8B380FF2 FOREIGN KEY (paid_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice_items ADD CONSTRAINT FK_DCC4B9F82989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoice_items ADD CONSTRAINT FK_DCC4B9F819EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE invoice_items ADD CONSTRAINT FK_DCC4B9F8240326A5 FOREIGN KEY (debt_id) REFERENCES debts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F95DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B3219EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B32240326A5 FOREIGN KEY (debt_id) REFERENCES debts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B322989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B32DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F2858A76ED395');
        $this->addSql('ALTER TABLE client_monthly_status DROP FOREIGN KEY FK_B963B45819EB6921');
        $this->addSql('ALTER TABLE client_monthly_status DROP FOREIGN KEY FK_B963B4582989F1FD');
        $this->addSql('ALTER TABLE client_monthly_status DROP FOREIGN KEY FK_B963B458240326A5');
        $this->addSql('ALTER TABLE config DROP FOREIGN KEY FK_D48A2F7C16FE72E1');
        $this->addSql('ALTER TABLE debts DROP FOREIGN KEY FK_6F64A29B19EB6921');
        $this->addSql('ALTER TABLE debts DROP FOREIGN KEY FK_6F64A29B8B380FF2');
        $this->addSql('ALTER TABLE invoice_items DROP FOREIGN KEY FK_DCC4B9F82989F1FD');
        $this->addSql('ALTER TABLE invoice_items DROP FOREIGN KEY FK_DCC4B9F819EB6921');
        $this->addSql('ALTER TABLE invoice_items DROP FOREIGN KEY FK_DCC4B9F8240326A5');
        $this->addSql('ALTER TABLE invoices DROP FOREIGN KEY FK_6A2F2F95DE12AB56');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B3219EB6921');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B32240326A5');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B322989F1FD');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B32DE12AB56');
        $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_9BACE7E1A76ED395');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE client_monthly_status');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP TABLE config');
        $this->addSql('DROP TABLE debts');
        $this->addSql('DROP TABLE invoice_items');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE users');
    }
}
