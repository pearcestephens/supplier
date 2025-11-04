<?php
/**
 * Simple file-based rate limiter (per-IP, per-key)
 *
 * Sliding window approximation using minute buckets stored as small JSON files
 */
declare(strict_types=1);

class RateLimiter
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?: (__DIR__ . '/../cache/ratelimit');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    /**
     * Check and increment a rate limit.
     * @param string $key Unique key, e.g. "api:{ip}" or "login:{ip}"
     * @param int $limit Requests allowed per minute
     * @return array [allowed(bool), remaining(int), reset(int epoch seconds)]
     */
    public function check(string $key, int $limit): array
    {
        $now = time();
        $minute = (int)floor($now / 60);
        $file = $this->fileFor($key);
        $bucket = ['minute' => $minute, 'count' => 0];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $data = json_decode($raw, true);
                if (is_array($data) && isset($data['minute'], $data['count'])) {
                    $bucket = $data;
                }
            }
        }

        if ($bucket['minute'] !== $minute) {
            $bucket = ['minute' => $minute, 'count' => 0];
        }

        $bucket['count']++;
        @file_put_contents($file, json_encode($bucket), LOCK_EX);

        $allowed = $bucket['count'] <= $limit;
        $remaining = max(0, $limit - $bucket['count']);
        $reset = ($minute + 1) * 60; // next minute boundary

        return [$allowed, $remaining, $reset];
    }

    private function fileFor(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_:\-]/', '_', $key);
        return $this->dir . '/' . $safe . '.json';
    }
}
