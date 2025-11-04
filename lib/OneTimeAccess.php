<?php
/**
 * One-Time Access Token Manager (file-based)
 * Uses supplier UUID as the token. Records issuance and consumes on first use.
 */
declare(strict_types=1);

class OneTimeAccess
{
    private string $dir;
    private int $ttl;

    public function __construct(?string $dir = null, ?int $ttlSeconds = null)
    {
        $this->dir = $dir ?: (__DIR__ . '/../cache/one_time_links');
        $this->ttl = $ttlSeconds ?? (defined('MAGIC_LINK_TTL_SECONDS') ? (int)MAGIC_LINK_TTL_SECONDS : 86400);
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    /**
     * Issue a new one-time token for a supplier ID (overwrites any previous).
     */
    public function issue(string $supplierId): bool
    {
        $path = $this->fileFor($supplierId);
        $data = [
            'supplier_id' => $supplierId,
            'issued_at' => time(),
            'used' => false
        ];
        return (bool)@file_put_contents($path, json_encode($data), LOCK_EX);
    }

    /**
     * Validate and consume the token. Returns true if valid and marks as used.
     */
    public function validateAndConsume(string $supplierId): bool
    {
        $path = $this->fileFor($supplierId);
        if (!is_file($path)) {
            return false;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) return false;
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['issued_at'], $data['used'])) return false;

        // Check expiry
        if ((time() - (int)$data['issued_at']) > $this->ttl) {
            // Expired: remove file
            @unlink($path);
            return false;
        }

        if (!empty($data['used'])) {
            return false; // already used
        }

        // Mark as used
        $data['used'] = true;
        @file_put_contents($path, json_encode($data), LOCK_EX);
        return true;
    }

    private function fileFor(string $supplierId): string
    {
        $safe = preg_replace('/[^a-f0-9\-]/i', '_', $supplierId);
        return $this->dir . '/' . $safe . '.json';
    }
}
