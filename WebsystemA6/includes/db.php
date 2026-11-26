<?php

const DB_HOST     = 'localhost';
const DB_USER     = 'root';
const DB_PASSWORD = '';
const DB_NAME     = 'booking_dtb';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli
{
    static $connection = null;

    if ($connection === null) {
        try {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            $connection->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $error) {
            error_log('Database connection error: ' . $error->getMessage());
            http_response_code(500);
            exit("Can't reach the database. Start MySQL in XAMPP and check the settings in includes/db.php.");
        }
    }

    return $connection;
}