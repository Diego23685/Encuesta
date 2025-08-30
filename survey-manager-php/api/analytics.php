<?php
// api/analytics.php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

$survey_id = (int)($_GET['survey_id'] ?? 0);
if (!$survey_id) { http_response_code(422); echo json_encode(['error'=>'survey_id requerido']); exit; }

// Conteos por opción (single/multi)
$sql_choice_counts = "
SELECT q.ID as Question_ID, q.Text as Question_Text, c.ID as Choice_ID, c.Label,
       SUM(CASE WHEN a.Selected_Choice_ID = c.ID THEN 1 ELSE 0 END) +
       SUM(CASE WHEN ac.Choice_ID = c.ID THEN 1 ELSE 0 END) as Count_Selected
FROM Questions q
JOIN Choices c ON c.Question_ID = q.ID
LEFT JOIN Answers a ON a.Question_ID = q.ID
LEFT JOIN Answers_Choices ac ON ac.Answer_ID = a.ID
WHERE q.Survey_ID = ?
GROUP BY q.ID, c.ID
ORDER BY q.ID, c.Position, c.ID
";

// Promedios para numeric
$sql_numeric = "
SELECT q.ID as Question_ID, q.Text as Question_Text,
       COUNT(a.Answer_number) as n, AVG(a.Answer_number) as avg, MIN(a.Answer_number) as min, MAX(a.Answer_number) as max
FROM Questions q
JOIN Answers a ON a.Question_ID = q.ID
WHERE q.Survey_ID = ? AND q.Type = 'number'
GROUP BY q.ID
";

$stmt = $pdo->prepare($sql_choice_counts);
$stmt->execute([$survey_id]);
$choice_counts = $stmt->fetchAll();

$stmt = $pdo->prepare($sql_numeric);
$stmt->execute([$survey_id]);
$numeric = $stmt->fetchAll();

echo json_encode(['choice_counts'=>$choice_counts, 'numeric'=>$numeric]);
