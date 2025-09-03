<?php
// api/answers_matrix.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_login('admin'); // solo admin

$survey_id = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
if (!$survey_id) {
  http_response_code(422);
  echo json_encode(['error' => 'survey_id requerido']);
  exit;
}

try {
  // 1) Submissions + Respondents
  $sstmt = $pdo->prepare("
    SELECT s.ID AS Submission_ID,
           r.Name  AS Respondent_Name,
           r.Email AS Respondent_Email,
           r.External_ID AS Respondent_External_ID,
           s.Started_at, s.Submitted_at
    FROM Submissions s
    JOIN Respondents r ON r.ID = s.Respondent_ID
    WHERE s.Survey_ID = ?
    ORDER BY s.ID ASC
  ");
  $sstmt->execute([$survey_id]);
  $subs = $sstmt->fetchAll(PDO::FETCH_ASSOC);

  if (!$subs) {
    echo json_encode(['survey_id'=>$survey_id, 'submissions'=>[],'answers'=>[],'answers_multi'=>[]]);
    exit;
  }

  $subIds = array_column($subs, 'Submission_ID');
  $in = implode(',', array_fill(0, count($subIds), '?'));

  // 2) Answers base (text/number/date/single)
  $astmt = $pdo->prepare("
    SELECT a.ID AS Answer_ID, a.Submission_ID, a.Question_ID,
           a.Answer_text, a.Answer_number, a.Answer_date,
           a.Selected_Choice_ID
    FROM Answers a
    WHERE a.Submission_ID IN ($in)
    ORDER BY a.Submission_ID, a.Question_ID, a.ID
  ");
  $astmt->execute($subIds);
  $answers = $astmt->fetchAll(PDO::FETCH_ASSOC);

  // 3) Answers_Choices (multi)
  $mcstmt = $pdo->prepare("
    SELECT ac.Answer_ID, ac.Choice_ID
    FROM Answers_Choices ac
    WHERE ac.Answer_ID IN (
      SELECT a.ID FROM Answers a
      WHERE a.Submission_ID IN ($in)
    )
    ORDER BY ac.Answer_ID, ac.Choice_ID
  ");
  $mcstmt->execute($subIds);
  $answers_multi_rows = $mcstmt->fetchAll(PDO::FETCH_ASSOC);

  // Agrupar multi por answer_id
  $multi_by_answer = [];
  foreach ($answers_multi_rows as $row) {
    $aid = (int)$row['Answer_ID'];
    if (!isset($multi_by_answer[$aid])) $multi_by_answer[$aid] = [];
    $multi_by_answer[$aid][] = (int)$row['Choice_ID'];
  }

  echo json_encode([
    'survey_id'      => $survey_id,
    'submissions'    => $subs,
    'answers'        => $answers,
    'answers_multi'  => $multi_by_answer
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'answers_matrix fallo', 'detail' => $e->getMessage()]);
}
