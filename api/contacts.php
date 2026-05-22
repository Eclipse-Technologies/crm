<?php
// api/contacts.php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$sanitize_for_json = function($data) {
    if (is_array($data)) {
        $clean = [];
        foreach ($data as $k => $v) {
            $clean[$k] = $sanitize_for_json($v);
        }
        return $clean;
    } elseif (is_string($data)) {
        // Remove invalid UTF-8, normalize line endings, remove stray backslashes
        $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        $data = str_replace(["\r\n", "\r"], "\n", $data);
        $data = preg_replace('/\\(?!["\\\/bfnrtu])/', '', $data); // Remove stray backslashes not part of valid escapes
        return $data;
    } else {
        return $data;
    }
};
require_once __DIR__ . '/../db_mysql.php';
require_once __DIR__ . '/../env_loader.php';
load_env();

// Header-based API key auth (align with api.php policy)
$api_keys_raw = getenv('API_KEYS') ?: '';
$valid_keys = $api_keys_raw !== '' ? array_filter(array_map('trim', explode(',', $api_keys_raw))) : [];
if (empty($valid_keys)) {
    http_response_code(503);
    echo json_encode(['error' => 'API not configured. Set API_KEYS in .env.']);
    exit;
}

$provided_key = '';
$x_api_key = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
$auth_header = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
if ($x_api_key !== '') {
    $provided_key = $x_api_key;
} elseif (str_starts_with($auth_header, 'Bearer ')) {
    $provided_key = trim(substr($auth_header, 7));
}

$key_valid = false;
foreach ($valid_keys as $k) {
    if (hash_equals($k, $provided_key)) {
        $key_valid = true;
        break;
    }
}

if (!$key_valid) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'detail' => 'Provide API key via X-API-Key header or Authorization: Bearer <key>.'
    ]);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $conn = get_mysql_connection();

        $limit = max(1, min(500, (int) ($_GET['limit'] ?? 200)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = 'SELECT contact_id, first_name, last_name, company, email, phone, city, province, postal_code, country, notes, tags, is_customer, created_at, last_modified FROM contacts';
        $types = '';
        $params = [];

        if ($q !== '') {
            $sql .= ' WHERE first_name LIKE ? OR last_name LIKE ? OR company LIKE ? OR email LIKE ?';
            $like = '%' . $q . '%';
            $types = 'ssss';
            $params = [$like, $like, $like, $like];
        }

        $sql .= ' ORDER BY last_name, first_name LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to prepare contacts query.']);
            $conn->close();
            break;
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $contacts = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $contacts[] = $row;
        }

        $stmt->close();
        $conn->close();

        $contacts = $sanitize_for_json($contacts);
        echo json_encode($contacts);
        break;
    case 'POST':
        http_response_code(405);
        echo json_encode([
            'error' => 'Method not allowed',
            'detail' => 'Direct writes are disabled on this endpoint. Use authenticated CRM import/workflow endpoints instead.'
        ]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error'=>'Method not allowed']);
}
