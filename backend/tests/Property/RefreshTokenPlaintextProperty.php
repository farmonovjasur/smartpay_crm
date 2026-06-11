<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\Auth\RefreshTokenStore;
use Doctrine\DBAL\Connection;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Property 16: Random refresh tokenlarni issue qilib DB ustunlarini skanerlash,
 * plaintext qiymat hech qaerda yo'qligini tekshirish.
 *
 * For any issued refresh token, the plaintext value MUST NOT appear anywhere
 * in the database (only the SHA-256 hash is stored).
 *
 * **Validates: Requirements 1.8**
 */
class RefreshTokenPlaintextProperty extends PropertyTestCase
{
    public function testPlaintextRefreshTokenNeverStoredInDatabase(): void
    {
        $refreshStore = self::getContainer()->get(RefreshTokenStore::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        /** @var Connection $conn */
        $conn = self::getContainer()->get(Connection::class);

        // Collect issued plaintext tokens
        $plaintextTokens = [];

        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $user = new User();
            $user->setName(Generators::randomName());
            $user->setEmail('rt_plain_' . $i . '_' . uniqid() . '@test.uz');
            $user->setRole($this->randomInt(0, 1) === 0 ? UserRole::Admin : UserRole::User);
            $user->setPasswordHash($hasher->hashPassword($user, 'dummy'));

            $this->em->persist($user);
            $this->em->flush();

            $plainToken = $refreshStore->issue(
                $user,
                'Agent-' . $this->randomInt(1, 1000),
                '192.168.' . $this->randomInt(0, 255) . '.' . $this->randomInt(1, 254)
            );

            $plaintextTokens[] = $plainToken;
        }

        // Scan all columns in the refresh_tokens table for any plaintext match
        $allRows = $conn->fetchAllAssociative('SELECT * FROM refresh_tokens');

        foreach ($plaintextTokens as $idx => $plaintext) {
            foreach ($allRows as $row) {
                foreach ($row as $column => $value) {
                    if ($value === null) {
                        continue;
                    }

                    $strValue = (string) $value;

                    // Check exact match
                    self::assertNotSame(
                        $plaintext,
                        $strValue,
                        "Plaintext refresh token found in column '$column' (token index=$idx)"
                    );

                    // Check substring presence (e.g., could be stored in a combined field)
                    if (strlen($plaintext) > 10 && strlen($strValue) > 10) {
                        self::assertStringNotContainsString(
                            $plaintext,
                            $strValue,
                            "Plaintext refresh token found as substring in column '$column' (token index=$idx)"
                        );
                    }
                }
            }

            // Additionally verify that the SHA-256 hash IS stored
            $expectedHash = hash('sha256', $plaintext);
            $found = false;
            foreach ($allRows as $row) {
                if (($row['token_hash'] ?? '') === $expectedHash) {
                    $found = true;
                    break;
                }
            }
            self::assertTrue($found, "SHA-256 hash for token index=$idx must be present in DB");
        }
    }
}
