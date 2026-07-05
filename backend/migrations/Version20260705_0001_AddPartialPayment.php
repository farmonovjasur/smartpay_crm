<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Qisman to'lov (partial payment) tizimi uchun migration:
 * - debts jadvaliga paid_amount column qo'shish
 * - status column'iga 'partial' qiymatini qo'llab-quvvatlash
 * - is_active generated column'ini partial statusni ham hisobga olish
 */
final class Version20260705_0001_AddPartialPayment extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add paid_amount column to debts table and update is_active for partial status support';
    }

    public function up(Schema $schema): void
    {
        // 1. paid_amount column qo'shish
        $this->addSql('ALTER TABLE debts ADD COLUMN paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER amount');

        // 2. Avval unique constraint'ni olib tashlash
        $this->addSql('ALTER TABLE debts DROP INDEX uniq_active_debt_per_client');

        // 3. Eski is_active generated column'ni olib tashlash
        $this->addSql('ALTER TABLE debts DROP COLUMN is_active');

        // 4. Yangi is_active generated column qo'shish — active VA partial uchun 1 qaytaradi
        $this->addSql("ALTER TABLE debts ADD COLUMN is_active TINYINT GENERATED ALWAYS AS (CASE WHEN status IN ('active', 'partial') THEN 1 ELSE NULL END) STORED");

        // 5. Unique constraint'ni qayta yaratish
        $this->addSql('ALTER TABLE debts ADD UNIQUE INDEX uniq_active_debt_per_client (client_id, is_active)');
    }

    public function down(Schema $schema): void
    {
        // Unique constraint'ni olib tashlash
        $this->addSql('ALTER TABLE debts DROP INDEX uniq_active_debt_per_client');

        // is_active ni eski holatga qaytarish
        $this->addSql('ALTER TABLE debts DROP COLUMN is_active');
        $this->addSql("ALTER TABLE debts ADD COLUMN is_active TINYINT GENERATED ALWAYS AS (CASE WHEN status='active' THEN 1 ELSE NULL END) STORED");

        // Unique constraint'ni eski holatga qaytarish
        $this->addSql('ALTER TABLE debts ADD UNIQUE INDEX uniq_active_debt_per_client (client_id, is_active)');

        // paid_amount column'ni olib tashlash
        $this->addSql('ALTER TABLE debts DROP COLUMN paid_amount');
    }
}
