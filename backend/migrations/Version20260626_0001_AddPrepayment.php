<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add balance column to clients and create prepayments table.
 */
final class Version20260626_0001_AddPrepayment extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add balance column to clients table and create prepayments table for advance payments';
    }

    public function up(Schema $schema): void
    {
        // Add balance column to clients
        $this->addSql('ALTER TABLE clients ADD COLUMN balance DECIMAL(15,2) NOT NULL DEFAULT 0.00');

        // Create prepayments table
        $this->addSql('
            CREATE TABLE prepayments (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id   INT UNSIGNED NOT NULL,
                amount      DECIMAL(15,2) NOT NULL,
                method      VARCHAR(10) NOT NULL,
                paid_at     DATETIME NOT NULL,
                notes       VARCHAR(500) NULL,
                created_by  INT UNSIGNED NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_prepay_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
                CONSTRAINT fk_prepay_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_prepay_client (client_id),
                INDEX idx_prepay_paid_at (paid_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS prepayments');
        $this->addSql('ALTER TABLE clients DROP COLUMN balance');
    }
}
