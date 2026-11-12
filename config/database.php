<?php

// Uses Render environment variables when available.
// Falls back to local XAMPP settings when they are not set.
$host     = getenv('DB_HOST') ?: "127.0.0.1";
$port     = getenv('DB_PORT') ?: "3308";
$dbname   = getenv('DB_NAME') ?: "findit_db";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";

$options = [];

// Many cloud MySQL providers require SSL
if (getenv('DB_SSL') === 'true') {
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        $options
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Unable to connect to the database. Please try again later.");
}

?>
