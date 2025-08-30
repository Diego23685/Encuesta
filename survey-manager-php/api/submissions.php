<?php
// api/submissions.php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // Carga de un cuestionario completo (encuesta + preguntas + opciones)
  $survey_id = (int)($_GET['survey_id'] ?? 0);
  if (!$survey_id) { http_response_code(422); echo json_encode(['error'=>'survey_id requerido']); exit; }

  $st = $pdo->prepare('SELECT * FROM Surveys WHERE ID=?');
  $st->execute([$survey_id]);
  $survey = $st->fetch();
  if (!$survey) { http_response_code(404); echo json_encode(['error'=>'Survey no encontrada']); exit; }

  $st = $pdo->prepare('SELECT * FROM Questions WHERE Survey_ID=? ORDER BY Position ASC, ID ASC');
  $st->execute([$survey_id]);
  $questions = $st->fetchAll();

  $qids = array_map(fn($q)=>$q['ID'], $questions);
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
  echo json_encode(['survey'=>$survey, 'questions'=>$questions]);
  exit;
}

if ($method === 'POST') {
  // Guardar un envío
  $body = json_decode(file_get_contents('php://input'), true) ?? [];
  $survey_id = (int)($body['survey_id'] ?? 0);
  $answers = $body['answers'] ?? [];
  $name = trim((string)($body['name'] ?? ''));
  $email = trim((string)($body['email'] ?? ''));
  $respondent_id = null;

  if (!$survey_id || !is_array($answers) || empty($answers)) {
    http_response_code(422);
    echo json_encode(['error'=>'survey_id y answers son requeridos']);
    exit;
  }

  try {
    $pdo->beginTransaction();
    // Resolver Respondent (si no es anónimo y hay email)
    if ($email) {
      $st = $pdo->prepare('SELECT ID FROM Respondents WHERE Email=?');
      $st->execute([$email]);
      $row = $st->fetch();
      if ($row) {
        $respondent_id = (int)$row['ID'];
      } else {
        $st = $pdo->prepare('INSERT INTO Respondents (External_ID, Name, Email) VALUES (?, ?, ?)');
        $st->execute([bin2hex(random_bytes(8)), $name ?: $email, $email]);
        $respondent_id = (int)$pdo->lastInsertId();
      }
    }

    $st = $pdo->prepare('INSERT INTO Submissions (Survey_ID, Respondent_ID, Submitted_at) VALUES (?, ?, NOW())');
    $st->execute([$survey_id, $respondent_id]);
    $submission_id = (int)$pdo->lastInsertId();

    // answers: array de objetos {question_id, type, value, choices: []}
    foreach ($answers as $a) {
      $qid = (int)($a['question_id'] ?? 0);
      $type = $a['type'] ?? 'text';
      $val = $a['value'] ?? null;
      $choices = $a['choices'] ?? [];

      $ans_text = null; $ans_number = null; $ans_date = null; $selected_choice_id = null;

      if ($type === 'text') $ans_text = is_scalar($val) ? (string)$val : null;
      elseif ($type === 'number') $ans_number = is_scalar($val) ? (float)$val : null;
      elseif ($type === 'date') $ans_date = is_scalar($val) ? (string)$val : null;
      elseif ($type === 'single') $selected_choice_id = (int)$val;
      elseif ($type === 'multi') {} // se llena en tabla N:N

      $stA = $pdo->prepare('INSERT INTO Answers (Submission_ID, Question_ID, Answer_text, Answer_number, Answer_date, Selected_Choice_ID) VALUES (?, ?, ?, ?, ?, ?)');
      $stA->execute([$submission_id, $qid, $ans_text, $ans_number, $ans_date, $selected_choice_id]);
      $answer_id = (int)$pdo->lastInsertId();

      if ($type === 'multi' && is_array($choices)) {
        $stAC = $pdo->prepare('INSERT INTO Answers_Choices (Answer_ID, Choice_ID) VALUES (?, ?)');
        foreach ($choices as $cid) {
          $stAC->execute([$answer_id, (int)$cid]);
        }
      }
    }

    $pdo->commit();
    echo json_encode(['ok'=>true, 'submission_id'=>$submission_id]);
  } catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>'No se pudo guardar', 'detail'=>$e->getMessage()]);
  }
  exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
