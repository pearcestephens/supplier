<?php
/**
 * MagicLink - One-time access token manager
 *
 * Implements a simple one-time, 24h access mechanism using the supplier UUID
 * as the link parameter and a small file-backed token stored per supplier.
 */
declare(strict_types=1);

class MagicLink
{
    private const DIR = __DIR__ . '/../cache/magic';

    public static function issue(string $supplierId, int $ttlSeconds = 86400): bool
    {
        if ($supplierId === '') return false;
        if (!is_dir(self::DIR)) {
            @mkdir(self::DIR, 0755, true);
        }
        $path = self::pathFor($supplierId);
        $now = time();
        $data = [
            'supplier_id' => $supplierId,
            'issued_at' => $now,
            'expires_at' => $now + $ttlSeconds,
            'consumed' => false,
            'version' => 1
        ];
        return (bool)@file_put_contents($path, json_encode($data), LOCK_EX);
    }

    public static function validateAndConsume(string $supplierId): bool
    {
        $path = self::pathFor($supplierId);
        if (!is_file($path)) {
            return false;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) return false;
        $data = json_decode($raw, true);
        if (!is_array($data)) return false;

        $now = time();
        if (!empty($data['consumed'])) {
            return false;
        }
        if (!isset($data['expires_at']) || $now > (int)$data['expires_at']) {
            // expired - clean up
            @unlink($path);
            return false;
        }
        // Consume
        $data['consumed'] = true;
        @file_put_contents($path, json_encode($data), LOCK_EX);

        return true;
    }

    private static function pathFor(string $supplierId): string
    {
        $safe = preg_replace('/[^a-f0-9\-]/i', '_', $supplierId);
        return self::DIR . '/' . $safe . '.json';
    }
}
