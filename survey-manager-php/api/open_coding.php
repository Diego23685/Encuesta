<?php
// api/open_coding.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_login('admin'); // Solo admin ve tabulaciones

$survey_id   = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
$question_id = isset($_GET['question_id']) ? (int)$_GET['question_id'] : null;

if (!$survey_id) {
  http_response_code(422);
  echo json_encode(['error' => 'survey_id requerido']);
  exit;
}

try {
  // Trae preguntas abiertas (text/date) de la encuesta (o solo una)
  $sql = "SELECT ID, Position, Text, Type
          FROM Questions
          WHERE Survey_ID = ? AND Type IN ('text','date')";
  $params = [$survey_id];
  if ($question_id) {
    $sql .= " AND ID = ?";
    $params[] = $question_id;
  }
  $sql .= " ORDER BY Position ASC, ID ASC";
  $qstmt = $pdo->prepare($sql);
  $qstmt->execute($params);
  $questions = $qstmt->fetchAll(PDO::FETCH_ASSOC);

  // Helpers
  $normalize = function($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    // Contra múltiples espacios
    $s = preg_replace('/\s+/u', ' ', $s);
    // Minúsculas para agrupar sin diferenciar mayúsculas
    $s = mb_strtolower($s, 'UTF-8');
    return $s;
  };

  $result = [
    'survey_id' => $survey_id,
    'questions' => []
  ];

  foreach ($questions as $q) {
    $qid  = (int)$q['ID'];
    $type = $q['Type'];

    if ($type === 'text') {
      $astmt = $pdo->prepare("
        SELECT a.Answer_text AS v
        FROM Answers a
        JOIN Submissions s ON s.ID = a.Submission_ID
        WHERE s.Survey_ID = ? AND a.Question_ID = ?
          AND a.Answer_text IS NOT NULL AND a.Answer_text <> ''
      ");
      $astmt->execute([$survey_id, $qid]);
    } else { // date
      $astmt = $pdo->prepare("
        SELECT DATE(a.Answer_date) AS v
        FROM Answers a
        JOIN Submissions s ON s.ID = a.Submission_ID
        WHERE s.Survey_ID = ? AND a.Question_ID = ?
          AND a.Answer_date IS NOT NULL
      ");
      $astmt->execute([$survey_id, $qid]);
    }

    $freq = [];      // clave normalizada => conteo
    $labelRep = [];  // clave normalizada => primer label visto (para mostrar)
    $n_total = 0;

    while ($row = $astmt->fetch(PDO::FETCH_ASSOC)) {
      $val = $row['v'];
      // limpiar por si viene null
      if ($val === null) continue;
      $n_total++;
      $key = $normalize($val);
      if ($key === '') continue;
      if (!isset($freq[$key])) {
        $freq[$key] = 0;
        // guardamos el primer label original (recortado) como representación
        $labelRep[$key] = trim((string)$val);
      }
      $freq[$key]++;
    }

    // Ordenar por frecuencia desc y luego alfabético
    $items = [];
    foreach ($freq as $k => $count) {
      $items[] = [
        'label' => $labelRep[$k],
        'count' => $count,
        'key'   => $k
      ];
    }
    usort($items, function($a, $b){
      if ($a['count'] === $b['count']) return strcmp($a['label'], $b['label']);
      return $b['count'] - $a['count'];
    });

    // Asignar códigos 1..N
    $codes = [];
    $code = 1;
    foreach ($items as $it) {
      $codes[] = [
        'code'  => $code++,
        'label' => $it['label'],
        'count' => $it['count']
      ];
    }

    $result['questions'][] = [
      'Question_ID' => $qid,
      'Position'    => (int)$q['Position'],
      'Text'        => $q['Text'],
      'Type'        => $type,
      'n_total'     => $n_total,
      'n_distinct'  => count($freq),
      'codes'       => $codes
    ];
  }

  echo json_encode($result);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'open_coding fallo', 'detail' => $e->getMessage()]);
}
