<?php
// api/choices.php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $question_id = (int)($_GET['question_id'] ?? 0);
  if (!$question_id) { http_response_code(422); echo json_encode(['error'=>'question_id requerido']); exit; }
  $stmt = $pdo->prepare('SELECT * FROM Choices WHERE Question_ID=? ORDER BY Position ASC, ID ASC');
  $stmt->execute([$question_id]);
  echo json_encode($stmt->fetchAll());
  exit;
}

require_login('admin');
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
  $qid = (int)($body['Question_ID'] ?? 0);
  $pos = (int)($body['Position'] ?? 0);
  $label = trim((string)($body['Label'] ?? ''));
  $value = trim((string)($body['Value'] ?? ''));
  if (!$qid || !$label) { http_response_code(422); echo json_encode(['error'=>'Question_ID y Label son requeridos']); exit; }
  $stmt = $pdo->prepare('INSERT INTO Choices (Question_ID, Position, Label, Value) VALUES (?, ?, ?, ?)');
  $stmt->execute([$qid, $pos, $label, $value ?: $label]);
  echo json_encode(['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
  exit;
}

if ($method === 'DELETE') {
  parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
  $id = (int)($qs['id'] ?? 0);
  if (!$id) { http_response_code(422); echo json_encode(['error'=>'id requerido']); exit; }
  $pdo->prepare('DELETE FROM Choices WHERE ID=?')->execute([$id]);
  echo json_encode(['ok'=>true]);
  exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
