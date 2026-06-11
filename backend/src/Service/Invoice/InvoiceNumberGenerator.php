<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use Doctrine\ORM\EntityManagerInterface;

final class InvoiceNumberGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{serial: int, number: string}
     */
    public function nextFor(string $period): array
    {
        $conn = $this->em->getConnection();

        $maxSerial = (int) $conn->fetchOne(
            'SELECT COALESCE(MAX(serial_no), 0) FROM invoices WHERE period = ? FOR UPDATE',
            [$period]
        );

        $serial = $maxSerial + 1;

        // Format: FAKTURA-O=DD.MM.YYYY-NNN
        $today = new \DateTimeImmutable();
        $number = sprintf('FAKTURA-O=%s-%03d', $today->format('d.m.Y'), $serial);

        return ['serial' => $serial, 'number' => $number];
    }
}
