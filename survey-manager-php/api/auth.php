<?php
// api/auth.php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  if (isset($_GET['action']) && $_GET['action'] === 'me') {
    echo json_encode($_SESSION['user'] ?? null);
    exit;
  }
  http_response_code(400);
  echo json_encode(['error' => 'Bad request']);
  exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $body['action'] ?? '';

if ($method === 'POST' && $action === 'register') {
  $name = trim((string)($body['name'] ?? ''));
  $email = trim((string)($body['email'] ?? ''));
  $pass = (string)($body['password'] ?? '');
  $role = in_array(($body['role'] ?? 'respondent'), ['admin','respondent']) ? $body['role'] : 'respondent';

  if (!$name || !$email || !$pass) {
    http_response_code(422);
    echo json_encode(['error' => 'Nombre, correo y contraseña son obligatorios']);
    exit;
  }

  try {
    $pdo->beginTransaction();
    // Crear Respondent si no existe
    $stmt = $pdo->prepare('SELECT ID FROM Respondents WHERE Email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) {
      $stmt = $pdo->prepare('INSERT INTO Respondents (External_ID, Name, Email) VALUES (?, ?, ?)');
      $stmt->execute([bin2hex(random_bytes(8)), $name, $email]);
      $respondent_id = (int)$pdo->lastInsertId();
    } else {
      $respondent_id = (int)$row['ID'];
    }

    // Crear credencial
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('REPLACE INTO Auth (Respondent_ID, Password_Hash, Role) VALUES (?, ?, ?)');
    $stmt->execute([$respondent_id, $hash, $role]);
    $pdo->commit();

    echo json_encode(['ok' => true, 'respondent_id' => $respondent_id, 'role' => $role]);
  } catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo registrar', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($method === 'POST' && $action === 'login') {
  $email = trim((string)($body['email'] ?? ''));
  $pass = (string)($body['password'] ?? '');

  $stmt = $pdo->prepare('SELECT r.ID, r.Name, r.Email, a.Password_Hash, a.Role
                         FROM Respondents r
                         JOIN Auth a ON a.Respondent_ID = r.ID
                         WHERE r.Email = ?');
  $stmt->execute([$email]);
  $row = $stmt->fetch();
  if (!$row || !password_verify($pass, $row['Password_Hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales inválidas']);
    exit;
  }
  $_SESSION['user'] = [
    'ID' => (int)$row['ID'],
    'Name' => $row['Name'],
    'Email' => $row['Email'],
    'Role' => $row['Role']
  ];
  echo json_encode(['ok' => true, 'user' => $_SESSION['user']]);
  exit;
}

if ($method === 'POST' && $action === 'logout') {
  session_destroy();
  echo json_encode(['ok' => true]);
  exit;
}

http_response_code(400);
echo json_encode(['error' => 'Bad request']);
