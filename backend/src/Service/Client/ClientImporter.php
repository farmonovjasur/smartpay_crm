<?php

declare(strict_types=1);

namespace App\Service\Client;

use App\Dto\Client\ImportResult;
use App\Dto\Client\ImportRowError;
use App\Entity\Client;
use App\Entity\User;
use App\Enum\ClientStatus;
use App\Enum\NotificationType;
use App\Enum\PaymentType;
use App\Exception\ExcelInvalidFormatException;
use App\Exception\ExcelTooLargeException;
use App\Repository\ClientRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ClientImporter
{
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
        private readonly ImportRowParser $rowParser,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notificationService,
        private readonly ClientService $clientService,
    ) {
    }

    public function importExcel(UploadedFile $file, User $actor, bool $dryRun): ImportResult
    {
        // Validate size
        if ($file->getSize() > self::MAX_SIZE) {
            throw new ExcelTooLargeException();
        }

        // Validate extension and MIME
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'xlsx') {
            throw new ExcelInvalidFormatException();
        }

        $mime = $file->getMimeType();
        $allowed = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', // xlsx is zip-based
        ];
        if (!in_array($mime, $allowed, true)) {
            throw new ExcelInvalidFormatException();
        }

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $result = new ImportResult();
        $seenInns = [];

        // Header is on row index 2 (Excel row 3). Data starts at index 3.
        for ($i = 3, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i];

            // Skip empty rows
            if (empty(array_filter($row, fn ($cell) => $cell !== null && trim((string) $cell) !== ''))) {
                continue;
            }

            $result->totalRows++;
            $parsed = $this->rowParser->parse($row);

            if (!$parsed['valid']) {
                $result->errorRows[] = new ImportRowError($i + 1, $parsed['errors']);
                continue;
            }

            $inn = $parsed['data']['inn'];

            // Check duplicates within file
            if (isset($seenInns[$inn])) {
                $result->duplicateRows[] = ['row' => $i + 1, 'inn' => $inn];
                continue;
            }

            // Check DB duplicates
            if ($this->clientRepository->findOneAliveByInn($inn) !== null) {
                $result->duplicateRows[] = ['row' => $i + 1, 'inn' => $inn];
                $seenInns[$inn] = true;
                continue;
            }

            $seenInns[$inn] = true;

            if (!$dryRun) {
                $client = new Client();
                $client->setInn($inn);
                $client->setName($parsed['data']['name']);
                $client->setPhone($parsed['data']['phone']);
                $client->setPhone2($parsed['data']['phone2']);
                $client->setServiceDate(new \DateTimeImmutable($parsed['data']['service_date']));
                $client->setPaymentType(PaymentType::from($parsed['data']['payment_type']));
                $client->setProductCount($parsed['data']['product_count']);
                $client->setStatus(ClientStatus::Faol);

                $lastPaidPeriod = $parsed['data']['last_paid_period'] ?? '';
                $client->setLastPaidPeriod($lastPaidPeriod);

                $this->em->persist($client);
                $this->em->flush();

                // Qo'lda qo'shish kabi: to'lov tarixini seed qilish va
                // qarzdorlikni hisoblash (seedPaidHistory + reconcileOverdueAfterSeeding)
                $this->clientService->seedAndReconcileForImport($client, $lastPaidPeriod, $actor);
            }

            $result->importedCount++;
        }

        if (!$dryRun && $result->importedCount > 0) {
            $this->em->flush();

            $this->auditLogger->log($actor, 'client.import', 'client', null, [
                'imported' => $result->importedCount,
                'errors' => count($result->errorRows),
                'duplicates' => count($result->duplicateRows),
            ]);

            // Barcha xodimlarga bildirishnoma yuborish
            $this->notificationService->notifyAllStaff(
                NotificationType::ClientImported,
                'Mijozlar import qilindi',
                sprintf(
                    '%d ta mijoz muvaffaqiyatli import qilindi (xatolar: %d, dublikatlar: %d)',
                    $result->importedCount,
                    count($result->errorRows),
                    count($result->duplicateRows),
                ),
                '/clients',
            );
            $this->notificationService->flush();
        }

        return $result;
    }
}
