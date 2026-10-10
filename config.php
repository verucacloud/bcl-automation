<?php
declare(strict_types=1);

/*
 * Jangan simpan kredensial produksi di repository.
 * Salin config.local.example.php menjadi config.local.php lalu isi nilainya.
 */
$localConfig = __DIR__ . '/config.local.php';
if (!is_file($localConfig)) {
    http_response_code(500);
    exit('Konfigurasi belum tersedia. Salin config.local.example.php menjadi config.local.php.');
}

$config = require $localConfig;

$host = (string)($config['db_host'] ?? 'localhost');
$user = (string)($config['db_user'] ?? '');
$password = (string)($config['db_password'] ?? '');
$database = (string)($config['db_name'] ?? '');
$port = (int)($config['db_port'] ?? 3306);
$useSsl = !empty($config['db_ssl']) || $port === 4000;

$conn = mysqli_init();
if ($useSsl) {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
}

$connected = @$conn->real_connect($host, $user, $password, $database, $port, null, $useSsl ? MYSQLI_CLIENT_SSL : 0);
if (!$connected || $conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Database connection failed: ' . htmlspecialchars($conn->connect_error));
}
$conn->set_charset('utf8mb4');

define('APP_BASE_URL', rtrim((string)($config['app_base_url'] ?? ''), '/'));
define('CONTROLLER_API_KEY', (string)($config['controller_api_key'] ?? ''));
define('GOOGLE_CLIENT_ID', (string)($config['google_client_id'] ?? ''));
define('GOOGLE_CLIENT_SECRET', (string)($config['google_client_secret'] ?? ''));
