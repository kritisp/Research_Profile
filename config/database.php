<?php
/**
 * Database Connection Helper (PDO)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log detailed error internally and display safe generic error
                error_log('Database Connection Error: ' . $e->getMessage());
                http_response_code(500);
                if (PHP_SAPI !== 'cli') {
                    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Service Temporarily Unavailable</title>';
                    echo '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;color:#1e293b;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}';
                    echo '.card{background:white;padding:2rem;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 4px 6px -1px rgb(0 0 0/0.05);max-width:28rem;text-align:center;}';
                    echo 'h1{font-size:1.25rem;font-weight:700;color:#0f172a;margin-bottom:0.5rem;}p{font-size:0.875rem;color:#64748b;line-height:1.5;}</style></head><body>';
                    echo '<div class="card"><h1>Service Temporarily Unavailable</h1><p>The academic profile database is currently unreachable. The technical team has been notified. Please try again shortly.</p></div></body></html>';
                } else {
                    fwrite(STDERR, "Database Connection Error: " . $e->getMessage() . PHP_EOL);
                }
                exit(1);
            }
        }

        return self::$instance;
    }
}
