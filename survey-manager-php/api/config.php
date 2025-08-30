<?php
// api/config.php
// ⚙️ Configuración base de MySQL y sesión
declare(strict_types=1);
session_start();

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'Proyecto_Encuesta';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: 'admin';

try {
  $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'DB connection failed', 'detail' => $e->getMessage()]);
  exit;
}

function require_login($role = null) {
  if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
  }
  if ($role && ($_SESSION['user']['Role'] ?? null) !== $role) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit;
  }
}

header('Content-Type: application/json');
?>
