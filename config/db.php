<?php
/**
 * Database Connection Handler using PDO
 */
require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // If database does not exist yet, we allow catching in setup
                throw new PDOException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }
}

function get_db(): PDO {
    return Database::getConnection();
}
