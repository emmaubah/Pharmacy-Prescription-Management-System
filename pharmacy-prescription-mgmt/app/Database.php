<?php

class Database
{
    private PDO $connection;

    public function __construct()
    {
        // Load the local database settings.
        $config = require __DIR__ . '/../config/config.local.php';

        // Describe which MySQL database to connect to.
        $dsn = "mysql:host={$config['host']};"
             . "port={$config['port']};"
             . "dbname={$config['database']};"
             . "charset=utf8mb4";

        // Establish the connection.
        $this->connection = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }

    // Allow other classes to use the connection.
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}

