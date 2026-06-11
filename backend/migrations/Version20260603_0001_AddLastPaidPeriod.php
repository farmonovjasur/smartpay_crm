<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add last_paid_period column to clients table.
 *
 * This column lets the operator capture, at creation/edit time, the most recent
 * month for which the client has already paid (especially for legacy clients
 * being migrated to the CRM). The application uses it to seed
 * client_monthly_status rows from service_date through last_paid_period so
 * that the daily debt check does not falsely treat the historical months as
 * unpaid.
 */
final class Version20260603_0001_AddLastPaidPeriod extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_paid_period column to clients (YYYY-MM, nullable) and supporting index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE clients
                ADD COLUMN last_paid_period CHAR(7) DEFAULT NULL
                COMMENT 'Mijozning oxirgi to''langan oyi (YYYY-MM). Yangi mijoz uchun NULL.'"
        );
        $this->addSql('CREATE INDEX idx_clients_last_paid_period ON clients (last_paid_period)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_clients_last_paid_period ON clients');
        $this->addSql('ALTER TABLE clients DROP COLUMN last_paid_period');
    }
}
