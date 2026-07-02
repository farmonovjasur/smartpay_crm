<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the `app_config` key-value table used for operational metadata:
 * - worker_heartbeat: timestamp of last messenger worker activity
 * - last_debt_check_date: date string (Y-m-d) of last successful debt reconciliation
 *
 * Also creates the `messenger_messages` table required by the Doctrine
 * transport (MESSENGER_TRANSPORT_DSN=doctrine://default).
 */
final class Version20260702_0001_AddAppConfigTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add app_config table for health monitoring and messenger_messages for async transport';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS app_config (
                `key` VARCHAR(64) NOT NULL,
                `value` TEXT NOT NULL,
                PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Messenger Doctrine transport table
        $this->addSql("
            CREATE TABLE IF NOT EXISTS messenger_messages (
                id BIGINT AUTO_INCREMENT NOT NULL,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                INDEX IDX_75EA56E0FB7336F0 (queue_name),
                INDEX IDX_75EA56E0E3BD61CE (available_at),
                INDEX IDX_75EA56E016BA31DB (delivered_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_config');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
