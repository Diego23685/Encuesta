<?php
// api/export_rows.php
require_once __DIR__ . '/config.php';
require_login('admin');

$survey_id = (int)($_GET['survey_id'] ?? 0);
if (!$survey_id) { http_response_code(422); echo json_encode(['error'=>'survey_id requerido']); exit; }

// Preguntas de la encuesta (para mapear id -> posición / tipo)
$qstmt = $pdo->prepare("SELECT ID, Position, Text, Type FROM Questions WHERE Survey_ID=? ORDER BY Position ASC");
$qstmt->execute([$survey_id]);
$questions = $qstmt->fetchAll();
$by_qid = [];
foreach ($questions as $q) $by_qid[$q['ID']] = $q;

// Submissions + Respondents
$sstmt = $pdo->prepare("
  SELECT s.ID AS Submission_ID, r.Name, r.Email, s.Submitted_at
  FROM Submissions s
  LEFT JOIN Respondents r ON r.ID = s.Respondent_ID
  WHERE s.Survey_ID = ?
  ORDER BY s.ID ASC
");
$sstmt->execute([$survey_id]);
$subs = $sstmt->fetchAll();

$rows = [];
$astmt = $pdo->prepare("
  SELECT a.ID, a.Question_ID, a.Answer_text, a.Answer_number,
         DATE_FORMAT(a.Answer_date,'%Y-%m-%d') AS Answer_date,
         a.Selected_Choice_ID,
         c.Label AS Selected_Label
  FROM Answers a
  LEFT JOIN Choices c ON c.ID = a.Selected_Choice_ID
  WHERE a.Submission_ID = ?
");

$multi_stmt = $pdo->prepare("
  SELECT c.Label
  FROM Answers_Choices ac
  INNER JOIN Choices c ON c.ID = ac.Choice_ID
  WHERE ac.Answer_ID = ?
  ORDER BY c.Position ASC, c.ID ASC
");

foreach ($subs as $s) {
  $astmt->execute([$s['Submission_ID']]);
  $answers = $astmt->fetchAll();

  // por posición (I, II, III...); valor ya "presentable"
  $by_pos = [];

  foreach ($answers as $a) {
    $qid = (int)$a['Question_ID'];
    if (!isset($by_qid[$qid])) continue;
    $pos = (int)$by_qid[$qid]['Position'];
    $type = $by_qid[$qid]['Type'];
    $val  = '';

    if ($type === 'text') {
      $val = (string)($a['Answer_text'] ?? '');
    } elseif ($type === 'number') {
      $val = ($a['Answer_number'] === null ? '' : (0+$a['Answer_number']));
    } elseif ($type === 'date') {
      $val = (string)($a['Answer_date'] ?? '');
    } elseif ($type === 'single') {
      $val = (string)($a['Selected_Label'] ?? '');
    } elseif ($type === 'multi') {
      // juntar labels de Answers_Choices
      $multi_stmt->execute([$a['ID']]);
      $labels = array_column($multi_stmt->fetchAll(), 'Label');
      $val = implode(', ', $labels);
    }
    $by_pos[$pos] = $val;
  }

  $rows[] = [
    'submission_id' => (int)$s['Submission_ID'],
    'name'  => (string)($s['Name'] ?? ''),
    'email' => (string)($s['Email'] ?? ''),
    'by_pos'=> $by_pos,
  ];
}

echo json_encode([
  'survey_id' => $survey_id,
  'questions' => $questions,
  'rows'      => $rows,
], JSON_UNESCAPED_UNICODE);
