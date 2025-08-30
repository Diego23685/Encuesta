<?php
// api/questions.php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $survey_id = (int)($_GET['survey_id'] ?? 0);
  if (!$survey_id) { http_response_code(422); echo json_encode(['error'=>'survey_id requerido']); exit; }
  $stmt = $pdo->prepare('SELECT * FROM Questions WHERE Survey_ID=? ORDER BY Position ASC, ID ASC');
  $stmt->execute([$survey_id]);
  $questions = $stmt->fetchAll();

  // Adjuntar choices
  $qids = array_map(fn($q)=>$q['ID'],$questions);
  $choices = [];
  if ($qids) {
    $in = implode(',', array_fill(0, count($qids), '?'));
    $st2 = $pdo->prepare("SELECT * FROM Choices WHERE Question_ID IN ($in) ORDER BY Position ASC, ID ASC");
    $st2->execute($qids);
    foreach ($st2->fetchAll() as $c) {
      $choices[$c['Question_ID']][] = $c;
    }
  }
  foreach ($questions as &$q) { $q['Choices'] = $choices[$q['ID']] ?? []; }
  echo json_encode($questions);
  exit;
}

require_login('admin');
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
  $survey_id = (int)($body['Survey_ID'] ?? 0);
  $pos = (int)($body['Position'] ?? 0);
  $text = trim((string)($body['Text'] ?? ''));
  $type = trim((string)($body['Type'] ?? 'text')); // text|number|date|single|multi
  $req = (bool)($body['Is_required'] ?? false);
  $min = $body['Min_value'] ?? null;
  $max = $body['Max_value'] ?? null;

  if (!$survey_id || !$text) { http_response_code(422); echo json_encode(['error'=>'Survey_ID y Text son requeridos']); exit; }
  $stmt = $pdo->prepare('INSERT INTO Questions (Survey_ID, Position, Text, Type, Is_required, Min_value, Max_value) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([$survey_id, $pos, $text, $type, $req ? 1:0, $min, $max]);
  echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
  exit;
}

if ($method === 'DELETE') {
  parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
  $id = (int)($qs['id'] ?? 0);
  if (!$id) { http_response_code(422); echo json_encode(['error'=>'id requerido']); exit; }
  $pdo->prepare('DELETE FROM Questions WHERE ID=?')->execute([$id]);
  echo json_encode(['ok'=>true]);
  exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
