<?php
/**
 * Database Configuration & Connection Provider
 * Uses PDO with strict error handling and prepared statements
 */

class Database {
    private static ?PDO $pdo = null;

    private static array $config = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'lasu_fcit_exam_db',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ];

    /**
     * Set custom connection parameters if needed
     */
    public static function setConfig(array $config): void {
        self::$config = array_merge(self::$config, $config);
        self::$pdo = null; // reset connection
    }

    /**
     * Get active database configuration
     */
    public static function getConfig(): array {
        return self::$config;
    }

    /**
     * Get singleton PDO database connection
     */
    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                self::$config['host'],
                self::$config['port'],
                self::$config['dbname'],
                self::$config['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci', time_zone = '+01:00'"
            ];

            try {
                self::$pdo = new PDO($dsn, self::$config['username'], self::$config['password'], $options);
            } catch (PDOException $e) {
                // In production, do not leak connection parameters
                error_log('Database Connection Error: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed. Please ensure MySQL service is running and configured.');
            }
        }

        return self::$pdo;
    }

    /**
     * Helper to get connection without specifying dbname (for migrations/db creation)
     */
    public static function getRawConnection(): PDO {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            self::$config['host'],
            self::$config['port'],
            self::$config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        return new PDO($dsn, self::$config['username'], self::$config['password'], $options);
    }
}

/**
 * Global helper function to get PDO instance
 */
function getDb(): PDO {
    return Database::getConnection();
}
