<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');

$appHome = xapi_config()['APP_HOME'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$studentCode = trim($input['student_code'] ?? '');
$verb = trim($input['verb'] ?? '');
$activityId = trim($input['activity_id'] ?? '');
$activityName = trim($input['activity_name'] ?? $activityId);
$activityType = trim($input['activity_type'] ?? 'lesson');

if ($studentCode === '' || $verb === '' || $activityId === '') {
    http_response_code(400);
    echo json_encode([
        'error' => 'student_code, verb and activity_id are required'
    ]);
    exit;
}

$allowedVerbs = [
    'initialized',
    'attempted',
    'answered',
    'typed',
    'used-hint',
    'completed',
    'passed',
    'failed'
];

if (!in_array($verb, $allowedVerbs, true)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Unsupported verb',
        'verb' => $verb,
        'allowed_verbs' => $allowedVerbs
    ]);
    exit;
}

$allowedActivityTypes = ['lesson', 'question', 'interaction', 'module'];

if (!in_array($activityType, $allowedActivityTypes, true)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'activity_type must be one of: ' . implode(', ', $allowedActivityTypes)
    ]);
    exit;
}

if (array_key_exists('success', $input) && $input['success'] !== null && !is_bool($input['success'])) {
    http_response_code(400);
    echo json_encode(['error' => 'success must be a boolean (true or false)']);
    exit;
}

if (array_key_exists('score_raw', $input) && $input['score_raw'] !== null) {
    if (!is_int($input['score_raw']) && !is_float($input['score_raw'])) {
        http_response_code(400);
        echo json_encode(['error' => 'score_raw must be a number between 0 and 1']);
        exit;
    }

    if ($input['score_raw'] < 0 || $input['score_raw'] > 1) {
        http_response_code(400);
        echo json_encode(['error' => 'score_raw must be between 0 and 1']);
        exit;
    }
}

if (array_key_exists('attempt_number', $input) && $input['attempt_number'] !== null) {
    if (!is_int($input['attempt_number']) || $input['attempt_number'] < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'attempt_number must be a whole number of 1 or higher']);
        exit;
    }
}

$studentRes = sb_request(
    'GET',
    'students?student_code=eq.' . urlencode($studentCode) . '&active=eq.true&select=*'
);

if (($studentRes['status'] ?? 500) >= 400) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Could not read students table',
        'supabase' => $studentRes
    ]);
    exit;
}

$student = $studentRes['data'][0] ?? null;

if (!$student) {
    http_response_code(404);
    echo json_encode([
        'error' => 'Unknown or inactive student',
        'student_code' => $studentCode
    ]);
    exit;
}

$verbMap = [
    'initialized' => 'http://adlnet.gov/expapi/verbs/initialized',
    'attempted'   => 'http://adlnet.gov/expapi/verbs/attempted',
    'answered'    => 'http://adlnet.gov/expapi/verbs/answered',
    'typed'       => $appHome . '/xapi/verbs/typed',
    'used-hint'   => $appHome . '/xapi/verbs/used-hint',
    'completed'   => 'http://adlnet.gov/expapi/verbs/completed',
    'passed'      => 'http://adlnet.gov/expapi/verbs/passed',
    'failed'      => 'http://adlnet.gov/expapi/verbs/failed'
];

$verbId = $verbMap[$verb] ?? $appHome . '/xapi/verbs/' . rawurlencode($verb);

$activityTypeMap = [
    'lesson' => 'http://adlnet.gov/expapi/activities/lesson',
    'question' => 'http://adlnet.gov/expapi/activities/question',
    'interaction' => 'http://adlnet.gov/expapi/activities/interaction',
    'module' => 'http://adlnet.gov/expapi/activities/module'
];

$xapiActivityType = $activityTypeMap[$activityType]
    ?? $appHome . '/xapi/activity-types/' . rawurlencode($activityType);

$scoreRaw = $input['score_raw'] ?? null;
$success = $input['success'] ?? null;
$durationSeconds = $input['duration_seconds'] ?? null;

$statement = [
    'actor' => [
        'name' => $student['display_name'],
        'account' => [
            'homePage' => $appHome,
            'name' => $studentCode
        ]
    ],
    'verb' => [
        'id' => $verbId,
        'display' => [
            'en-US' => $verb
        ]
    ],
    'object' => [
        'id' => $appHome . '/activities/' . rawurlencode($activityId),
        'definition' => [
            'name' => [
                'nl-NL' => $activityName
            ],
            'type' => $xapiActivityType
        ],
        'objectType' => 'Activity'
    ],
    'result' => [
        'success' => $success,
        'score' => [
            'raw' => $scoreRaw,
            'min' => 0,
            'max' => 1
        ],
        'response' => $input['response'] ?? null,
        'duration' => $durationSeconds !== null
            ? 'PT' . intval($durationSeconds) . 'S'
            : null
    ],
    'context' => [
        'contextActivities' => [
            'parent' => [[
                'id' => $appHome . '/lessons/' . rawurlencode($input['lesson_id'] ?? 'unknown'),
                'definition' => [
                    'type' => 'http://adlnet.gov/expapi/activities/lesson'
                ]
            ]],
            'grouping' => [[
                'id' => $appHome . '/methods/' . rawurlencode($input['method_id'] ?? 'braillestudio'),
                'definition' => [
                    'name' => [
                        'nl-NL' => $input['method_id'] ?? 'BrailleStudio'
                    ]
                ]
            ]]
        ],
        'extensions' => [
            $appHome . '/xapi/extensions/word' => $input['word'] ?? null,
            $appHome . '/xapi/extensions/braille-cell' => $input['braille_cell'] ?? null,
            $appHome . '/xapi/extensions/letter' => $input['letter'] ?? null,
            $appHome . '/xapi/extensions/correct-response' => $input['correct_response'] ?? null,
            $appHome . '/xapi/extensions/attempt-number' => $input['attempt_number'] ?? null
        ]
    ],
    'timestamp' => gmdate('c')
];

$row = [
    'student_id' => $student['id'],
    'student_code' => $studentCode,

    'verb_id' => $verbId,
    'verb_display' => $verb,

    'activity_id' => $activityId,
    'activity_name' => $activityName,
    'activity_type' => $activityType,

    'lesson_id' => $input['lesson_id'] ?? null,
    'method_id' => $input['method_id'] ?? null,

    'success' => $success,
    'score_raw' => $scoreRaw,

    'response' => $input['response'] ?? null,
    'correct_response' => $input['correct_response'] ?? null,

    'duration_seconds' => $durationSeconds,

    'statement' => $statement
];

$res = sb_request('POST', 'xapi_statements', $row);

http_response_code($res['status']);

echo json_encode(
    $res['data'],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
