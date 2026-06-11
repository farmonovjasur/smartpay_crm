<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed initial configuration values into the config table.
 */
final class Version20260601_0002_SeedConfig extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed config table with initial application configuration values';
    }

    public function up(Schema $schema): void
    {
        $now = date('Y-m-d H:i:s');

        $configs = [
            ['unit_price', '100000', 'Oylik xizmat narxi (so\'m)'],
            ['responsible_name', 'Halimov Bekzod', 'Mas\'ul shaxs ismi'],
            ['seller_inn', '306733959', 'Sotuvchi INN'],
            ['ikpu_code', '', 'IKPU kodi'],
            ['unit_code', '', 'O\'lchov birligi kodi'],
            ['tax_benefit_code', '', 'Soliq imtiyozi kodi'],
            ['origin_code', '', 'Kelib chiqish kodi'],
            ['product_name_ru_template', 'Программное обеспечение за {MONTH} - {YEAR}', 'Mahsulot nomi shabloni (ruscha)'],
            ['debt_check_hour', '3', 'Qarz tekshiruvi soati (cron)'],
        ];

        foreach ($configs as [$key, $value, $description]) {
            $this->addSql(
                "INSERT INTO config (config_key, config_value, description, updated_at) VALUES (?, ?, ?, ?)",
                [$key, $value, $description, $now]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $keys = [
            'unit_price',
            'responsible_name',
            'seller_inn',
            'ikpu_code',
            'unit_code',
            'tax_benefit_code',
            'origin_code',
            'product_name_ru_template',
            'debt_check_hour',
        ];

        foreach ($keys as $key) {
            $this->addSql("DELETE FROM config WHERE config_key = ?", [$key]);
        }
    }
}
