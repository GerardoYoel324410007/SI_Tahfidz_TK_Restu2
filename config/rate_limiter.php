<?php
/**
 * File-based Rate Limiter
 * Menggantikan session-based rate limiting yang lemah
 */

class RateLimiter {
    private static $dir = __DIR__ . '/../data/ratelimit/';

    /**
     * Inisialisasi direktori
     */
    private static function ensureDir() {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0755, true);
        }
    }

    /**
     * Cek dan increment rate limit
     * @param string $key Identifier unik (misal: IP address)
     * @param int $maxAttempts Maks percobaan
     * @param int $windowSeconds Jendela waktu dalam detik
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null]
     */
    public static function check(string $key, int $maxAttempts = 5, int $windowSeconds = 900): array {
        self::ensureDir();

        $file = self::$dir . md5($key) . '.json';
        $now = time();

        // Baca data yang ada
        $data = ['attempts' => [], 'blocked_until' => null];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true) ?: $data;
        }

        // Cek apakah masih di-block
        if ($data['blocked_until'] && $now < $data['blocked_until']) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $data['blocked_until'] - $now
            ];
        }

        // Hapus attempts di luar window
        $data['attempts'] = array_filter(
            $data['attempts'],
            fn($t) => ($now - $t) < $windowSeconds
        );

        // Cek apakah sudah melebihi limit
        if (count($data['attempts']) >= $maxAttempts) {
            $data['blocked_until'] = $now + $windowSeconds;
            file_put_contents($file, json_encode($data), LOCK_EX);

            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $windowSeconds
            ];
        }

        // Tambah attempt baru
        $data['attempts'][] = $now;
        $data['blocked_until'] = null;
        file_put_contents($file, json_encode($data), LOCK_EX);

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - count($data['attempts']),
            'retry_after' => null
        ];
    }

    /**
     * Reset rate limit untuk key tertentu
     */
    public static function reset(string $key): void {
        self::ensureDir();
        $file = self::$dir . md5($key) . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Cleanup file yang sudah expired
     */
    public static function cleanup(int $maxAge = 3600): void {
        self::ensureDir();
        $files = glob(self::$dir . '*.json');
        $now = time();

        foreach ($files as $file) {
            if ($now - filemtime($file) > $maxAge) {
                unlink($file);
            }
        }
    }
}
