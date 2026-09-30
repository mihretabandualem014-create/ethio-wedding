<?php
// ============================================================
// CONFIGURATION — Update these values before deploying
// ============================================================

// Database credentials (from cPanel → MySQL Databases)
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');       // e.g. cpanelusername_wedding
define('DB_USER', 'your_db_user');       // e.g. cpanelusername_dbuser
define('DB_PASS', 'your_db_password');

// Couple & Event Details
define('BRIDE_NAME',    'Deva');
define('GROOM_NAME',    'Ayinu');
define('WEDDING_DATE',  'Wednesday, January 24, 2018');
define('WEDDING_TIME',  '');
define('VENUE_NAME',    'Venue Name');
define('VENUE_ADDRESS', 'Venue Address, City');
define('SITE_TITLE',    BRIDE_NAME . ' & ' . GROOM_NAME);

// Wedding date for countdown (ISO 8601, local time)
define('WEDDING_DATE_ISO', '2018-01-24T10:00:00');

// Upload settings
define('UPLOAD_DIR',      __DIR__ . '/uploads/');
define('MAX_FILE_SIZE',   10 * 1024 * 1024); // 10 MB
define('MAX_IMAGE_WIDTH', 1000);             // px
define('JPEG_QUALITY',    85);

// Admin password — change this before deploying!
define('ADMIN_PASSWORD', 'adminwedding123!');

// ============================================================
// DATABASE CONNECTION
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
