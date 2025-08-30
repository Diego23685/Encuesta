<?php
// api/surveys.php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM Surveys WHERE ID = ?');
    $stmt->execute([$_GET['id']]);
    echo json_encode($stmt->fetch());
    exit;
  }
  $stmt = $pdo->query('SELECT * FROM Surveys ORDER BY Created_at DESC, ID DESC');
  echo json_encode($stmt->fetchAll());
  exit;
}

require_login('admin');
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
  $title  = trim((string)($body['Title'] ?? ''));
  $desc   = trim((string)($body['Description'] ?? ''));
  $isAnon = (bool)($body['Is_anonymous'] ?? false);
  $status = trim((string)($body['Status'] ?? 'draft'));
  $opens  = $body['Opens_at'] ?? null;
  $closes = $body['Closes_at'] ?? null;

  if (!$title) {
    http_response_code(422);
    echo json_encode(['error' => 'Title es requerido']);
    exit;
  }

  $stmt = $pdo->prepare('INSERT INTO Surveys (Title, Description, Is_anonymous, Status, Opens_at, Closes_at) VALUES (?, ?, ?, ?, ?, ?)');
  $stmt->execute([$title, $desc, $isAnon ? 1 : 0, $status, $opens, $closes]);
  echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
  exit;
}

if ($method === 'PUT') {
  parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
  $id = (int)($qs['id'] ?? 0);
  if (!$id) {
    http_response_code(422);
    echo json_encode(['error' => 'id requerido']);
    exit;
  }

  $title  = trim((string)($body['Title'] ?? ''));
  $desc   = trim((string)($body['Description'] ?? ''));
  $isAnon = array_key_exists('Is_anonymous', $body) ? (int)((bool)$body['Is_anonymous']) : null;
  $status = $body['Status'] ?? null;
  $opens  = $body['Opens_at'] ?? null;
  $closes = $body['Closes_at'] ?? null;

  // OJO: comillas dobles para evitar el parse error por '' dentro del string.
  $sql = "UPDATE Surveys
            SET
              Title = COALESCE(NULLIF(?, ''), Title),
              Description = COALESCE(?, Description),
              Is_anonymous = COALESCE(?, Is_anonymous),
              Status = COALESCE(?, Status),
              Opens_at = COALESCE(?, Opens_at),
              Closes_at = COALESCE(?, Closes_at)
          WHERE ID = ?";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    $title,
    $desc !== '' ? $desc : null,
    $isAnon,
    $status,
    $opens,
    $closes,
    $id
  ]);

  echo json_encode(['ok' => true]);
  exit;
}

if ($method === 'DELETE') {
  parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
  $id = (int)($qs['id'] ?? 0);
  if (!$id) {
    http_response_code(422);
    echo json_encode(['error' => 'id requerido']);
    exit;
  }
  $pdo->prepare('DELETE FROM Surveys WHERE ID = ?')->execute([$id]);
  echo json_encode(['ok' => true]);
  exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
