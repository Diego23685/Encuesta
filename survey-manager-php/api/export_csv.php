<?php
// api/export_csv.php
require_once __DIR__ . '/config.php';

$survey_id = (int)($_GET['survey_id'] ?? 0);
if (!$survey_id) { http_response_code(422); echo 'survey_id requerido'; exit; }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=survey_'.$survey_id.'.csv');

$fp = fopen('php://output', 'w');
fputcsv($fp, ['Submission_ID','Respondent','Email','Question','Answer']);

$sql = "
SELECT s.ID as Submission_ID,
       COALESCE(r.Name, 'Anónimo') as Respondent,
       r.Email,
       q.Text as Question,
       COALESCE(a.Answer_text, CAST(a.Answer_number as char), DATE_FORMAT(a.Answer_date, '%Y-%m-%d'), c.Label) as Answer
FROM Submissions s
JOIN Surveys sv ON sv.ID = s.Survey_ID
LEFT JOIN Respondents r ON r.ID = s.Respondent_ID
JOIN Answers a ON a.Submission_ID = s.ID
JOIN Questions q ON q.ID = a.Question_ID
LEFT JOIN Choices c ON c.ID = a.Selected_Choice_ID
WHERE s.Survey_ID = ?
ORDER BY s.ID, q.Position, q.ID
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$survey_id]);
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
  fputcsv($fp, $row);
}
fclose($fp);
