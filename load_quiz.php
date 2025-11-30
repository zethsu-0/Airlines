<?php
// load_quiz.php
header('Content-Type: application/json; charset=utf-8');

// Don't output raw HTML errors – they break JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'airlines';

try {
    if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
        throw new Exception('Invalid id');
    }
    $quiz_id = (int) $_GET['id'];

    $mysqli = new mysqli($host, $user, $pass, $db);
    if ($mysqli->connect_error) {
        throw new Exception('DB connection failed: ' . $mysqli->connect_error);
    }

    // --------- 1) LOAD QUIZ HEADER FROM `quizzes` ---------
    /*
        Your schema:

        SELECT `id`, `title`, `from`, `to`, `quiz_code`, `duration`,
               `num_questions`, `created_by`, `created_at`
        FROM `quizzes`
    */
    $stmt = $mysqli->prepare("
        SELECT 
            `id`,
            `title`,
            `from`,
            `to`,
            `quiz_code`,
            `duration`,
            `num_questions`,
            `created_by`,
            `created_at`
        FROM `quizzes`
        WHERE `id` = ?
    ");
    if (!$stmt) {
        throw new Exception('Prepare (quiz) failed: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quizRes = $stmt->get_result();
    $quizRow = $quizRes->fetch_assoc();
    $stmt->close();

    if (!$quizRow) {
        throw new Exception('Quiz not found');
    }

    // --------- 2) LOAD ITEMS FROM `quiz_items` ---------
    /*
        SELECT `id`, `quiz_id`, `item_index`, `origin_iata`, `destination_iata`,
               `adults`, `children`, `infants`, `flight_type`, `departure_date`,
               `return_date`, `flight_number`, `seats`, `travel_class`
        FROM `quiz_items`
        WHERE quiz_id = ?
    */
    $stmt = $mysqli->prepare("
        SELECT 
            `id`,
            `quiz_id`,
            `item_index`,
            `origin_iata`,
            `destination_iata`,
            `adults`,
            `children`,
            `infants`,
            `flight_type`,
            `departure_date`,
            `return_date`,
            `flight_number`,
            `seats`,
            `travel_class`
        FROM `quiz_items`
        WHERE `quiz_id` = ?
        ORDER BY `item_index` ASC, `id` ASC
    ");
    if (!$stmt) {
        throw new Exception('Prepare (items) failed: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $itemsRes = $stmt->get_result();

    $items = [];
    while ($row = $itemsRes->fetch_assoc()) {
        $origin      = strtoupper(trim($row['origin_iata'] ?? ''));
        $destination = strtoupper(trim($row['destination_iata'] ?? ''));
        $adults      = (int)($row['adults'] ?? 0);
        $children    = (int)($row['children'] ?? 0);
        $infants     = (int)($row['infants'] ?? 0);
        $flightType  = strtoupper(trim($row['flight_type'] ?? 'ONE-WAY'));
        if ($flightType !== 'ROUND-TRIP') {
            $flightType = 'ONE-WAY';
        }
        $departure   = $row['departure_date'] ?? null;
        $returnDate  = $row['return_date']   ?? null;
        $flightNum   = $row['flight_number'] ?? '';
        $seats       = $row['seats']         ?? '';
        $travelClass = strtolower(trim($row['travel_class'] ?? 'economy'));

        // Shape exactly how quizmaker.js expects:
        $items[] = [
            'id'         => (int)$row['id'],
            'item_index' => (int)$row['item_index'],
            'iata'       => $origin,      // origin used as iata
            'city'       => $destination, // destination in "city" field
            'booking'    => [
                'adults'        => $adults,
                'children'      => $children,
                'infants'       => $infants,
                'flight_type'   => $flightType,
                'origin'        => $origin,
                'destination'   => $destination,
                'departure'     => $departure,
                'return'        => $returnDate,
                'flight_number' => $flightNum,
                'seats'         => $seats,
                'travel_class'  => $travelClass
            ]
        ];
    }
    $stmt->close();
    $mysqli->close();

    // --------- 3) Build JSON for quizmaker.php ---------
    $quiz = [
        'id'           => (int)$quizRow['id'],
        'title'        => $quizRow['title'],
        // this feeds the Section / Course input (sectionField)
        'from'         => $quizRow['from'],        // uses your `from` column
        'to'           => $quizRow['to'],          // not used yet in UI, but handy
        'quiz_code'    => $quizRow['quiz_code'],
        'duration'     => $quizRow['duration'],
        'num_questions'=> (int)$quizRow['num_questions'],
        'created_by'   => $quizRow['created_by'],
        'created_at'   => $quizRow['created_at'],
        'items'        => $items
    ];

    echo json_encode([
        'success' => true,
        'quiz'    => $quiz
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
