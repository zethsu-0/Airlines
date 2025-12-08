<?php
// save_quiz.php - robust create / update for quizzes (accepts numeric id OR public_id, prefers 'section' field)
session_start();
header('Content-Type: application/json');

// ------------------
// DB CONFIG
// ------------------
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines';

// ------------------
// CONNECT DB
// ------------------
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// ------------------ Helpers
// ------------------
function generate_unique_public_id($mysqli, $bytes = 6, $max_tries = 8) {
    $tries = 0;
    do {
        $id = strtoupper(bin2hex(random_bytes($bytes)));
        $stmt = $mysqli->prepare("SELECT 1 FROM quizzes WHERE public_id = ? LIMIT 1");
        if (!$stmt) return $id;
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res && $res->fetch_assoc()) ? true : false;
        $stmt->close();
        $tries++;
        if ($tries >= $max_tries) break;
    } while ($exists);
    return $id;
}

function safe_prepare($mysqli, $sql, $label = '') {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $lbl = $label ? " ($label)" : '';
        throw new Exception('Prepare failed' . $lbl . ': ' . $mysqli->error);
    }
    return $stmt;
}

// ------------------ Creator (session)
// ------------------
$createdBy = $_SESSION['acc_id'] ?? $_SESSION['user_id'] ?? null;
if (!$createdBy) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in (acc_id missing)']);
    exit;
}
$createdByStr = (string)$createdBy;

// ------------------ Read input JSON
// ------------------
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON received']);
    exit;
}

// ------------------ Extract fields (accept both 'section' and legacy 'from')
// ------------------
$incomingId       = $data['id'] ?? null; // may be numeric id or public_id string
$title            = trim($data['title'] ?? 'Untitled Quiz');
$section_in       = isset($data['section']) ? trim($data['section']) : trim($data['from'] ?? '');
$to               = trim($data['to'] ?? '');
$code             = trim($data['code'] ?? '');
$duration         = (int)($data['duration'] ?? 0);
$num_questions    = (int)($data['num_questions'] ?? 0);
$items            = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
$input_type       = trim($data['input_type'] ?? 'code-airport');

// ensure code exists
if ($code === '') {
    $code = 'QZ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

// numeric conversion helpers
$duration_i = (int)$duration;
$num_questions_i = (int)$num_questions;

// ------------------ Transaction
// ------------------
$mysqli->begin_transaction();

try {
    $isUpdate = false;
    $quiz_id = 0;
    $public_id = null;

    // Detect whether incoming id is numeric (db id) or public_id string
    $incoming_is_numeric = false;
    if ($incomingId !== null && $incomingId !== '') {
        // if it's purely digits treat as numeric id
        if (is_int($incomingId) || ctype_digit((string)$incomingId)) {
            $incoming_is_numeric = true;
            $incoming_numeric_id = (int)$incomingId;
        } else {
            $incoming_is_numeric = false;
            $incoming_pubid = (string)$incomingId;
        }
    }

    if ($incomingId !== null && $incomingId !== '') {
        // Try find quiz either by id or by public_id
        if ($incoming_is_numeric) {
            $stmt = safe_prepare($mysqli, "SELECT id, public_id FROM quizzes WHERE id = ? LIMIT 1", "find quiz by id");
            $stmt->bind_param('i', $incoming_numeric_id);
        } else {
            $stmt = safe_prepare($mysqli, "SELECT id, public_id FROM quizzes WHERE public_id = ? LIMIT 1", "find quiz by public_id");
            $stmt->bind_param('s', $incoming_pubid);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            // update path
            $isUpdate = true;
            $quiz_id = (int)$row['id'];
            $public_id = trim((string)($row['public_id'] ?? ''));
        } else {
            // if update requested but not found, treat as error
            throw new Exception('Quiz not found for update');
        }
    }

    // Ensure public_id exists for this quiz (generate if missing)
    if ($isUpdate && ($public_id === '' || $public_id === null)) {
        $public_id = generate_unique_public_id($mysqli, 6);
        $stmtPub = safe_prepare($mysqli, "UPDATE quizzes SET public_id = ? WHERE id = ?", "set public_id");
        $stmtPub->bind_param('si', $public_id, $quiz_id);
        if (!$stmtPub->execute()) throw new Exception('Failed to set public_id: ' . $stmtPub->error);
        $stmtPub->close();
    }

    // Detect whether 'section' column exists in quizzes table. If not, we'll write to `from` (legacy).
    $colCheck = $mysqli->query("SHOW COLUMNS FROM `quizzes` LIKE 'section'");
    $hasSectionCol = ($colCheck && $colCheck->num_rows > 0);
    if ($colCheck) $colCheck->free();

    if ($isUpdate) {
        // ---------- UPDATE quiz ----------
        // prefer updating 'section' if column exists; else update legacy `from` column
        if ($hasSectionCol) {
            $sqlUpdate = "UPDATE quizzes SET title = ?, section = ?, `to` = ?, quiz_code = ?, duration = ?, num_questions = ?, input_type = ? WHERE id = ?";
            $stmtUpdate = safe_prepare($mysqli, $sqlUpdate, "update quiz (section)");
            $stmtUpdate->bind_param('sssiiisi', $title, $section_in, $to, $code, $duration_i, $num_questions_i, $input_type, $quiz_id);
        } else {
            $sqlUpdate = "UPDATE quizzes SET title = ?, `from` = ?, `to` = ?, quiz_code = ?, duration = ?, num_questions = ?, input_type = ? WHERE id = ?";
            $stmtUpdate = safe_prepare($mysqli, $sqlUpdate, "update quiz (from)");
            $stmtUpdate->bind_param('sssiiisi', $title, $section_in, $to, $code, $duration_i, $num_questions_i, $input_type, $quiz_id);
        }

        if (!$stmtUpdate->execute()) {
            throw new Exception('Update quiz failed: ' . $stmtUpdate->error);
        }
        $stmtUpdate->close();

        // remove existing items for this quiz (we will re-insert the submitted items)
        $stmtDel = safe_prepare($mysqli, "DELETE FROM quiz_items WHERE quiz_id = ?", "delete items");
        $stmtDel->bind_param('i', $quiz_id);
        if (!$stmtDel->execute()) throw new Exception('Delete quiz_items failed: ' . $stmtDel->error);
        $stmtDel->close();

    } else {
        // ---------- INSERT new quiz ----------
        $public_id = generate_unique_public_id($mysqli, 6);

        if ($hasSectionCol) {
            $sqlInsert = "INSERT INTO quizzes (public_id, title, section, `to`, quiz_code, duration, num_questions, input_type, created_by, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmtInsert = safe_prepare($mysqli, $sqlInsert, "insert quiz (section)");
            $stmtInsert->bind_param('sssssiiss', $public_id, $title, $section_in, $to, $code, $duration_i, $num_questions_i, $input_type, $createdByStr);
        } else {
            // legacy fallback writes 'from'
            $sqlInsert = "INSERT INTO quizzes (public_id, title, `from`, `to`, quiz_code, duration, num_questions, input_type, created_by, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmtInsert = safe_prepare($mysqli, $sqlInsert, "insert quiz (from)");
            $stmtInsert->bind_param('sssssiiss', $public_id, $title, $section_in, $to, $code, $duration_i, $num_questions_i, $input_type, $createdByStr);
        }

        if (!$stmtInsert->execute()) {
            throw new Exception('Insert quiz failed: ' . $stmtInsert->error);
        }
        $quiz_id = $stmtInsert->insert_id;
        $stmtInsert->close();
    }

    // ------------------ ensure quiz_items can hold legs_json and assistance_json (optional)
    $useLegsColumn = false;
    $useAssistColumn = false;

    $resCol = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'legs_json'");
    if ($resCol) {
        $useLegsColumn = ($resCol->num_rows > 0);
        $resCol->free();
    }
    if (!$useLegsColumn) {
        // try to add it, non-fatal
        @$mysqli->query("ALTER TABLE quiz_items ADD COLUMN legs_json TEXT NULL");
        $resCol2 = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'legs_json'");
        if ($resCol2) { $useLegsColumn = ($resCol2->num_rows > 0); $resCol2->free(); }
    }

    $resColA = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'assistance_json'");
    if ($resColA) {
        $useAssistColumn = ($resColA->num_rows > 0);
        $resColA->free();
    }
    if (!$useAssistColumn) {
        // try to add it, non-fatal
        @$mysqli->query("ALTER TABLE quiz_items ADD COLUMN assistance_json TEXT NULL");
        $resColA2 = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'assistance_json'");
        if ($resColA2) { $useAssistColumn = ($resColA2->num_rows > 0); $resColA2->free(); }
    }

    // ------------------ Insert items
    if (!empty($items)) {

        // build dynamic insert with optional legs_json / assistance_json
        $cols = "public_id, quiz_id, item_index, origin_iata, destination_iata,
                 adults, children, infants, flight_type,
                 departure_date, return_date,
                 flight_number, seats, travel_class";
        $placeholders = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";

        if ($useLegsColumn) {
            $cols .= ", legs_json";
            $placeholders .= ", ?";
        }
        if ($useAssistColumn) {
            $cols .= ", assistance_json";
            $placeholders .= ", ?";
        }

        $insertSql = "INSERT INTO quiz_items ($cols) VALUES ($placeholders)";

        $stmtItem = safe_prepare($mysqli, $insertSql, "insert quiz_items");

        $idx = 0;
        foreach ($items as $item) {
            $idx++;
            $item_public_id = generate_unique_public_id($mysqli, 5);

            $booking = $item['booking'] ?? [];

            // normalize legs
            $legs = [];
            if (!empty($booking['legs']) && is_array($booking['legs'])) {
                foreach ($booking['legs'] as $lg) {
                    $legs[] = [
                        'origin'      => isset($lg['origin']) ? strtoupper(trim($lg['origin'])) : '',
                        'destination' => isset($lg['destination']) ? strtoupper(trim($lg['destination'])) : '',
                        'date'        => $lg['date'] ?? null
                    ];
                }
            } else {
                $lgOrigin = strtoupper(trim($booking['origin'] ?? $item['iata'] ?? ''));
                $lgDest   = strtoupper(trim($booking['destination'] ?? $item['city'] ?? ''));
                $lgDate   = $booking['departure'] ?? null;
                $legs[] = ['origin'=>$lgOrigin, 'destination'=>$lgDest, 'date'=>$lgDate];
            }

            $origin = $legs[0]['origin'] ?? '';
            $dest   = $legs[count($legs)-1]['destination'] ?? '';
            if ($origin === '') $origin = strtoupper(trim($item['iata'] ?? ''));
            if ($dest === '')   $dest   = strtoupper(trim($item['city'] ?? ''));

            $adults   = (int)($booking['adults'] ?? 0);
            $children = (int)($booking['children'] ?? 0);
            $infants  = (int)($booking['infants'] ?? 0);
            $type     = strtoupper(trim($booking['flight_type'] ?? 'ONE-WAY'));
            $depart   = $legs[0]['date'] ?? ($booking['departure'] ?? '');
            $return   = $booking['return'] ?? '';

            $flightNo = trim($booking['flight_number'] ?? '');
            $seatsVal = strtoupper(trim($booking['seats'] ?? ''));
            $classVal = strtoupper(trim($booking['travel_class'] ?? ''));

            $legs_json = $useLegsColumn ? json_encode($legs, JSON_UNESCAPED_UNICODE) : null;

            // normalize assistance (per-passenger disabilities / special handling)
            $assistArr = [];
            if (!empty($booking['assistance']) && is_array($booking['assistance'])) {
                foreach ($booking['assistance'] as $a) {
                    if (!is_array($a)) continue;
                    $pNum = isset($a['passenger']) ? (int)$a['passenger'] : 0;
                    $tVal = isset($a['type']) ? strtoupper(trim($a['type'])) : '';
                    if ($pNum > 0 && $tVal !== '') {
                        $assistArr[] = [
                            'passenger' => $pNum,
                            'type'      => $tVal
                        ];
                    }
                }
            }
            $assist_json = $useAssistColumn ? json_encode($assistArr, JSON_UNESCAPED_UNICODE) : null;

            // build params array in the same order as $cols
            $params = [
                $item_public_id,
                $quiz_id,
                $idx,
                $origin,
                $dest,
                $adults,
                $children,
                $infants,
                $type,
                (string)$depart,
                (string)$return,
                $flightNo,
                $seatsVal,
                $classVal
            ];

            if ($useLegsColumn) {
                $params[] = (string)($legs_json ?? '');
            }
            if ($useAssistColumn) {
                $params[] = (string)($assist_json ?? '');
            }

            // bind by reference
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : 's';
            }
            $refs = [];
            foreach ($params as $k => $v) $refs[$k] = &$params[$k];
            array_unshift($refs, $types);

            if (!call_user_func_array([$stmtItem, 'bind_param'], $refs)) {
                throw new Exception('Failed to bind quiz_items params: ' . $stmtItem->error);
            }
            if (!$stmtItem->execute()) {
                throw new Exception('Insert quiz_items failed: ' . $stmtItem->error);
            }
        }
        $stmtItem->close();
    }

    // ------------------ Commit
    $mysqli->commit();

    echo json_encode([
        'success'   => true,
        'public_id' => $public_id,
        'id'        => $quiz_id,
        'mode'      => $isUpdate ? 'update' : 'create'
    ]);
    exit;

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
