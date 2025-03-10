<?php
class Database {
    private static $instances = []; // Cache instances to avoid reconnecting for the same database

    public static function connect($dbAlias) {
        $validAliases = ['trade', 'market','volatility']; // Define valid aliases for databases
        $dbConfig = [
            'trade' => 'my369sa',
            'market' => 'my369data',
            'volatility' => 'my369volatility'
        ];

        // Validate the alias
        if (!in_array($dbAlias, $validAliases)) {
            throw new InvalidArgumentException("Invalid database alias: $dbAlias");
        }

        if (!isset(self::$instances[$dbAlias])) {
            try {
                // Use environment variables for sensitive data
                $host = getenv('DB_HOST') ?: '172.19.0.11';
                $port = getenv('DB_PORT') ?: '3306';
                $user = getenv('DB_USER') ?: 'root';
                $password = getenv('DB_PASSWORD') ?: 'CF26D23C453D3EB6';
                $dbName = $dbConfig[$dbAlias];

                // DSN string
                $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";

                // Establish the connection
                $pdo = new PDO($dsn, $user, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Cache the connection
                self::$instances[$dbAlias] = $pdo;

            } catch (PDOException $e) {
                // Log the error for debugging
                error_log("Database connection failed for $dbAlias: " . $e->getMessage());
                die("Unable to connect to the database. Please try again later.");
            }
        }

        return self::$instances[$dbAlias];
    }
}
