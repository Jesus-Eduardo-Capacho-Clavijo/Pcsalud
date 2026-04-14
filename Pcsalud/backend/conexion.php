<?php
declare(strict_types=1);

/**
 * Carga variable de entorno con fallback local.
 */
function envOrDefault(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function envFromMany(array $keys, string $default): string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }
    return $default;
}

$host = envFromMany(['DB_HOST', 'MYSQLHOST'], 'localhost');
$port = envFromMany(['DB_PORT', 'MYSQLPORT'], '3306');
$db = envFromMany(['DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE'], 'pcsalud');
$user = envFromMany(['DB_USER', 'MYSQLUSER'], 'root');
$pass = envFromMany(['DB_PASS', 'MYSQLPASSWORD', 'MYSQL_ROOT_PASSWORD'], '');
$charset = envOrDefault('DB_CHARSET', 'utf8mb4');
$appEnv = envOrDefault('APP_ENV', 'local');

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    // En produccion evitamos exponer detalles internos.
    if ($appEnv === 'production') {
        exit('Error de conexion a la base de datos.');
    }
    exit('Error de conexion a la base de datos: ' . $e->getMessage());
}
