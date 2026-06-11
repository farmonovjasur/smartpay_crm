<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\ClientMonthlyStatus;
use App\Entity\Config;
use App\Enum\PaymentStatus;
use App\Enum\PayMethod;

/**
 * Behavioural tests for the optional `last_paid_period` field on clients.
 *
 * The field lets the operator capture, at creation/edit time, the most
 * recent month for which a legacy client has already paid. The service
 * then seeds ClientMonthlyStatus rows from service_date through
 * last_paid_period so that the daily debt check does not retroactively
 * flag those months as unpaid.
 */
final class ClientLastPaidPeriodTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInvoiceConfig();
    }

    private function validClientData(array $overrides = []): array
    {
        return array_merge([
            'inn' => '300000001',
            'name' => 'Legacy Client',
            'phone' => '+998901112233',
            'service_date' => '2024-01-15',
            'payment_type' => 'fakt',
            'product_count' => 1,
            'notes' => null,
            'last_paid_period' => '2024-01',
        ], $overrides);
    }

    public function testCreateWithoutLastPaidPeriodReturns422(): void
    {
        $this->loginAs('admin');

        $payload = $this->validClientData();
        unset($payload['last_paid_period']);

        $this->jsonRequest('POST', '/api/clients', $payload);
        self::assertResponseStatusCodeSame(422);

        $json = $this->getJsonResponse();
        self::assertArrayHasKey('errors', $json);
        self::assertArrayHasKey('lastPaidPeriod', $json['errors']);
    }

    public function testCreateWithEmptyLastPaidPeriodReturns422(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'last_paid_period' => '',
        ]));
        self::assertResponseStatusCodeSame(422);

        $cmsCount = (int) $this->em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM client_monthly_status');
        self::assertSame(0, $cmsCount);
    }

    public function testCreateWithLastPaidPeriodSeedsHistoricalCmsRows(): void
    {
        $this->loginAs('admin');

        // Service date 2024-01-15, last paid 2024-04 → 4 paid months: 2024-01..2024-04
        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => '2024-01-15',
            'last_paid_period' => '2024-04',
        ]));
        self::assertResponseStatusCodeSame(201);

        $json = $this->getJsonResponse();
        $clientId = $json['data']['id'];
        self::assertSame('2024-04', $json['data']['lastPaidPeriod']);

        $paidRows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT period, payment_status, payment_method, notes FROM client_monthly_status
             WHERE client_id = ? AND payment_status = ? ORDER BY period',
            [$clientId, 'paid']
        );

        self::assertCount(4, $paidRows);
        self::assertSame(['2024-01', '2024-02', '2024-03', '2024-04'], array_column($paidRows, 'period'));
        foreach ($paidRows as $row) {
            self::assertSame(PaymentStatus::Paid->value, $row['payment_status']);
            self::assertSame(PayMethod::Naqt->value, $row['payment_method']);
            self::assertSame('client_creation_history', $row['notes']);
        }
    }

    public function testCreateWithLastPaidPeriodEqualToServicePeriodSeedsSingleRow(): void
    {
        $this->loginAs('admin');

        // Use a current-month service date so reconcile doesn't generate any unpaid months.
        $today = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
        $serviceDate = $today->format('Y-m-10');
        $currentPeriod = $today->format('Y-m');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => $serviceDate,
            'last_paid_period' => $currentPeriod,
        ]));
        self::assertResponseStatusCodeSame(201);

        $clientId = $this->getJsonResponse()['data']['id'];

        $paidCount = (int) $this->em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM client_monthly_status WHERE client_id = ? AND payment_status = ?', [$clientId, 'paid']);
        self::assertSame(1, $paidCount);
    }

    public function testCreateWithLastPaidBeforeServiceDateReturns422(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => '2024-06-15',
            'last_paid_period' => '2024-03',
        ]));
        self::assertResponseStatusCodeSame(422);

        $cmsCount = (int) $this->em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM client_monthly_status');
        self::assertSame(0, $cmsCount, 'No CMS rows should be persisted when validation fails');
    }

    public function testCreateWithLastPaidInTheFutureReturns422(): void
    {
        $this->loginAs('admin');

        $futurePeriod = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))
            ->modify('+2 months')
            ->format('Y-m');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => '2024-01-15',
            'last_paid_period' => $futurePeriod,
        ]));
        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateWithMalformedPeriodReturns422(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'last_paid_period' => '2024/04',
        ]));
        self::assertResponseStatusCodeSame(422);

        $json = $this->getJsonResponse();
        self::assertArrayHasKey('errors', $json);
        self::assertArrayHasKey('lastPaidPeriod', $json['errors']);
    }

    public function testCreateWithEmptyStringIsTreatedAsNull(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'last_paid_period' => '',
        ]));
        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateForwardSeedsAdditionalMonths(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => '2024-01-15',
            'last_paid_period' => '2024-03',
        ]));
        $id = $this->getJsonResponse()['data']['id'];

        $this->jsonRequest('PUT', '/api/clients/' . $id, $this->validClientData([
            'service_date' => '2024-01-15',
            'last_paid_period' => '2024-06',
            'status' => 'faol',
        ]));
        self::assertResponseStatusCodeSame(200);

        $paidRows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT period FROM client_monthly_status WHERE client_id = ? AND payment_status = ? ORDER BY period',
            [$id, 'paid']
        );
        self::assertSame(
            ['2024-01', '2024-02', '2024-03', '2024-04', '2024-05', '2024-06'],
            array_column($paidRows, 'period'),
        );
    }

    public function testUpdateBackwardIsAllowedAndRebuildsDebt(): void
    {
        $this->loginAs('admin');

        $tz = new \DateTimeZone('Asia/Tashkent');
        $today = new \DateTimeImmutable('now', $tz);
        $currentPeriod = $today->format('Y-m');
        $serviceDate = $today->modify('-6 months')->format('Y-m-15');

        // Create a paid-up client (last_paid = current month) → no debt
        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => $serviceDate,
            'last_paid_period' => $currentPeriod,
        ]));
        self::assertResponseStatusCodeSame(201);
        $id = $this->getJsonResponse()['data']['id'];

        $debtCount = (int) $this->em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM debts WHERE client_id = ?', [$id]);
        self::assertSame(0, $debtCount);

        // Operator realises they typed the wrong period → moves it backward
        $threeMonthsAgo = $today->modify('-3 months')->format('Y-m');
        $this->jsonRequest('PUT', '/api/clients/' . $id, $this->validClientData([
            'service_date' => $serviceDate,
            'last_paid_period' => $threeMonthsAgo,
            'status' => 'faol',
        ]));
        self::assertResponseStatusCodeSame(200);

        // last_paid_period is updated and a debt is created for the gap
        $current = $this->em->getConnection()
            ->fetchOne('SELECT last_paid_period FROM clients WHERE id = ?', [$id]);
        self::assertSame($threeMonthsAgo, $current);

        $debt = $this->em->getConnection()->fetchAssociative(
            'SELECT status, months_overdue FROM debts WHERE client_id = ? AND status = ?',
            [$id, 'active']
        );
        self::assertNotFalse($debt);
        self::assertGreaterThanOrEqual(2, (int) $debt['months_overdue']);
    }

    public function testUpdateWithoutLastPaidPeriodReturns422(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => '2024-01-15',
            'last_paid_period' => '2024-03',
        ]));
        $id = $this->getJsonResponse()['data']['id'];

        // Update without the field — now mandatory, must return 422
        $this->jsonRequest('PUT', '/api/clients/' . $id, [
            'inn' => '300000001',
            'name' => 'Renamed',
            'phone' => '+998901112233',
            'service_date' => '2024-01-15',
            'payment_type' => 'fakt',
            'product_count' => 1,
            'status' => 'faol',
            'notes' => null,
        ]);
        self::assertResponseStatusCodeSame(422);

        // Verify nothing changed
        $current = $this->em->getConnection()
            ->fetchOne('SELECT name FROM clients WHERE id = ?', [$id]);
        self::assertSame('Legacy Client', $current);
    }

    public function testSeedingIsIdempotentWhenSamePeriodReplayed(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => '2024-01-15',
            'last_paid_period' => '2024-04',
        ]));
        $id = $this->getJsonResponse()['data']['id'];

        $paidBefore = (int) $this->em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM client_monthly_status WHERE client_id = ? AND payment_status = ?', [$id, 'paid']);

        // Set the same value again via update — paid count must not duplicate
        $this->jsonRequest('PUT', '/api/clients/' . $id, $this->validClientData([
            'service_date' => '2024-01-15',
            'last_paid_period' => '2024-04',
            'status' => 'faol',
        ]));
        self::assertResponseStatusCodeSame(200);

        $paidAfter = (int) $this->em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM client_monthly_status WHERE client_id = ? AND payment_status = ?', [$id, 'paid']);
        self::assertSame($paidBefore, $paidAfter, 'Paid CMS rows must not duplicate on replayed seeding');
        self::assertSame(4, $paidAfter, 'Expect exactly 2024-01..2024-04 as paid');
    }

    public function testCreateWithLastPaidEqualToCurrentMonthCreatesNoDebt(): void
    {
        $this->loginAs('admin');

        $tz = new \DateTimeZone('Asia/Tashkent');
        $today = new \DateTimeImmutable('now', $tz);
        $currentPeriod = $today->format('Y-m');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => $today->modify('-2 months')->format('Y-m-d'),
            'last_paid_period' => $currentPeriod,
        ]));
        self::assertResponseStatusCodeSame(201);

        $debtCount = (int) $this->em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM debts');
        self::assertSame(0, $debtCount, 'No debt expected when last_paid_period covers current month');
    }

    public function testCreateWithLastPaidBeforeCurrentMonthCreatesDebt(): void
    {
        $this->loginAs('admin');

        $tz = new \DateTimeZone('Asia/Tashkent');
        $today = new \DateTimeImmutable('now', $tz);
        $currentPeriod = $today->format('Y-m');

        // Service date 5 months ago, last paid 3 months ago.
        // With the simplified rule, overdue = (last_paid+1 .. current_month).
        $serviceDate = $today->modify('-5 months')->format('Y-m-01');
        $lastPaid = $today->modify('-3 months')->format('Y-m');
        $expectedMonthsOverdue = $this->countMonthsBetween(
            $today->modify('-2 months')->format('Y-m'),
            $currentPeriod,
        );

        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => $serviceDate,
            'last_paid_period' => $lastPaid,
            'product_count' => 2,
        ]));
        self::assertResponseStatusCodeSame(201);

        $clientId = $this->getJsonResponse()['data']['id'];

        $debt = $this->em->getConnection()->fetchAssociative(
            'SELECT amount, monthly_amount, months_overdue, first_overdue_period, last_overdue_period, status, payment_type_snapshot FROM debts WHERE client_id = ?',
            [$clientId]
        );

        self::assertNotFalse($debt, 'A debt row must be created when months are overdue');
        self::assertSame('active', $debt['status']);
        self::assertSame('fakt', $debt['payment_type_snapshot']);
        self::assertSame('200000.00', $debt['monthly_amount']);
        self::assertSame($expectedMonthsOverdue, (int) $debt['months_overdue']);
        self::assertSame($currentPeriod, $debt['last_overdue_period']);
        self::assertSame(
            $today->modify('-2 months')->format('Y-m'),
            $debt['first_overdue_period'],
        );
        $expectedAmount = bcmul('200000.00', (string) $expectedMonthsOverdue, 2);
        self::assertSame($expectedAmount, $debt['amount']);

        $unpaid = $this->em->getConnection()->fetchAllAssociative(
            'SELECT period FROM client_monthly_status WHERE client_id = ? AND payment_status = ? ORDER BY period',
            [$clientId, 'unpaid']
        );
        self::assertCount($expectedMonthsOverdue, $unpaid);
    }

    private function countMonthsBetween(string $from, string $to): int
    {
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $from . '-01');
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', $to . '-01');
        $months = 0;
        $cursor = $start;
        while ($cursor <= $end) {
            $months++;
            $cursor = $cursor->modify('+1 month');
        }
        return $months;
    }

    public function testUpdateLastPaidToCurrentMonthClosesActiveDebt(): void
    {
        $this->loginAs('admin');

        $tz = new \DateTimeZone('Asia/Tashkent');
        $today = new \DateTimeImmutable('now', $tz);
        $currentPeriod = $today->format('Y-m');
        $threeMonthsAgo = $today->modify('-3 months')->format('Y-m');
        $serviceDate = $today->modify('-3 months')->format('Y-m-15');

        // Create a client that is currently behind on payments → active debt
        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => $serviceDate,
            'last_paid_period' => $threeMonthsAgo,
        ]));
        self::assertResponseStatusCodeSame(201);
        $id = $this->getJsonResponse()['data']['id'];

        $debtBefore = $this->em->getConnection()->fetchAssociative(
            'SELECT status, amount FROM debts WHERE client_id = ?',
            [$id]
        );
        self::assertNotFalse($debtBefore);
        self::assertSame('active', $debtBefore['status']);

        // Now move last_paid_period forward to the current month
        $this->jsonRequest('PUT', '/api/clients/' . $id, $this->validClientData([
            'service_date' => $serviceDate,
            'last_paid_period' => $currentPeriod,
            'status' => 'faol',
        ]));
        self::assertResponseStatusCodeSame(200);

        $debtAfter = $this->em->getConnection()->fetchAssociative(
            'SELECT status, paid_method FROM debts WHERE client_id = ?',
            [$id]
        );
        self::assertSame('paid', $debtAfter['status'], 'Active debt must be closed when client becomes paid up');
        self::assertSame('naqt', $debtAfter['paid_method']);

        // A Payment row must have been recorded for audit
        $paymentCount = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM payments WHERE client_id = ? AND notes = ?',
            [$id, 'client_last_paid_period_update']
        );
        self::assertSame(1, $paymentCount);
    }

    public function testGeneratingInvoiceForSeededClientSkipsHistoricalPaidMonths(): void
    {
        $this->loginAs('admin');

        // Eski mijoz: 2024-01-15 dan beri xizmatda, 2024-04 gacha to'lagan.
        // Mijoz yaratilganda 2024-05..joriy oygacha qarz avtomatik shakllanadi,
        // shu sababli 2024-05 davri uchun fakturada unga "qarz qoldigi" emas,
        // balki yangi item sifatida tushishi kutiladi.
        $this->jsonRequest('POST', '/api/clients', $this->validClientData([
            'service_date' => '2024-01-15',
            'last_paid_period' => '2024-04',
        ]));
        self::assertResponseStatusCodeSame(201);

        // 2024-04 davri uchun faktura tuzilsa, mijoz unga tushmasligi kerak
        // (chunki shu period CMS'da paid sifatida belgilangan).
        $this->jsonRequest('POST', '/api/invoices/generate', ['period' => '2024-04']);
        self::assertResponseStatusCodeSame(422);

        // 2024-03 davri ham paid — 422
        $this->jsonRequest('POST', '/api/invoices/generate', ['period' => '2024-03']);
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Seed the minimum config values that InvoiceGenerator reads.
     * The test database is reset for every test so we re-seed when needed.
     */
    private function seedInvoiceConfig(): void
    {
        $configs = [
            ['unit_price', '100000'],
            ['responsible_name', 'Halimov Bekzod'],
            ['product_name_ru_template', 'Программное обеспечение за {month} - {year}'],
        ];
        foreach ($configs as [$key, $value]) {
            $config = new Config();
            $config->setConfigKey($key);
            $config->setConfigValue($value);
            $this->em->persist($config);
        }
        $this->em->flush();
    }
}
