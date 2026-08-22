<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Qarzdaftari (Credit Ledger) — yangi jadvallar.
 *
 * ledger_entries: Asosiy qarz yozuvi (mijozga qarzga xizmat berilganda yaratiladi)
 * ledger_items:   Har bir yozuv ichidagi xizmat qatorlari (1:N)
 */
final class Version20260820_0001_AddLedgerTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Qarzdaftari: ledger_entries + ledger_items jadvallarini yaratish';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE ledger_entries (
                id            INT UNSIGNED AUTO_INCREMENT NOT NULL,
                client_id     INT UNSIGNED NOT NULL,
                total_amount  DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
                paid_amount   DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
                status        VARCHAR(10)    NOT NULL DEFAULT 'active',
                notes         VARCHAR(500)   DEFAULT NULL,
                paid_at       DATETIME       DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                paid_by       INT UNSIGNED   DEFAULT NULL,
                created_by    INT UNSIGNED   DEFAULT NULL,
                created_at    DATETIME       NOT NULL     COMMENT '(DC2Type:datetime_immutable)',
                updated_at    DATETIME       DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',

                INDEX idx_ledger_client  (client_id),
                INDEX idx_ledger_status  (status),
                INDEX idx_ledger_created (created_at),

                CONSTRAINT fk_ledger_client   FOREIGN KEY (client_id)  REFERENCES clients (id) ON DELETE RESTRICT,
                CONSTRAINT fk_ledger_paid_by  FOREIGN KEY (paid_by)    REFERENCES users (id)   ON DELETE SET NULL,
                CONSTRAINT fk_ledger_created  FOREIGN KEY (created_by) REFERENCES users (id)   ON DELETE SET NULL,

                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE ledger_items (
                id               INT UNSIGNED AUTO_INCREMENT NOT NULL,
                ledger_entry_id  INT UNSIGNED   NOT NULL,
                description      VARCHAR(500)   NOT NULL,
                amount           DECIMAL(15, 2) NOT NULL,
                created_at       DATETIME       NOT NULL COMMENT '(DC2Type:datetime_immutable)',

                INDEX idx_litem_entry (ledger_entry_id),

                CONSTRAINT fk_litem_entry FOREIGN KEY (ledger_entry_id)
                    REFERENCES ledger_entries (id) ON DELETE CASCADE,

                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ledger_items');
        $this->addSql('DROP TABLE ledger_entries');
    }
}
