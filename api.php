<?php
/**
 * api.php — Read-only REST API for CRM data
 *
 * Authentication: Header-based only.
 * - X-API-Key: <key>
 * - Authorization: Bearer <key>
 * API keys are stored in .env as API_KEYS (comma-separated).
 *
 * Endpoints:
 *   GET /api.php/contacts[?q=&limit=&offset=]
 *   GET /api.php/contacts/{id}
 *   GET /api.php/opportunities[?stage=&limit=&offset=]
 *   GET /api.php/opportunities/{id}
 *   GET /api.php/tasks[?status=&limit=&offset=]
 *   GET /api.php/tasks/{id}
 *   GET /api.php/contracts[?status=&limit=&offset=]
 *   GET /api.php/contracts/{id}
 */

require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/env_loader.php';
load_env();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// No session — stateless API

// ---- Authentication ----
$api_keys_raw = getenv('API_KEYS') ?? '';
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
    if (hash_equals($k, $provided_key)) { $key_valid = true; break; }
}
if (!$key_valid) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'detail' => 'Provide API key via X-API-Key header or Authorization: Bearer <key>. Query-string api_key is disabled.'
    ]);
    exit;
}

// ---- Route parsing ----
// Supports both PATH_INFO (/contacts/123) and ?resource=contacts&id=123
$path_info = trim($_SERVER['PATH_INFO'] ?? '', '/');
if ($path_info === '') {
    $resource = trim($_GET['resource'] ?? '', '/');
    $resource_id = $_GET['id'] ?? null;
} else {
    $parts = explode('/', $path_info, 2);
    $resource = $parts[0];
    $resource_id = $parts[1] ?? null;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. This is a read-only API.']);
    exit;
}

$limit  = max(1, min(500, (int)($_GET['limit']  ?? 100)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$conn = get_mysql_connection();

function api_rows(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    if ($params) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $r = $stmt->get_result();
    } else {
        $r = $conn->query($sql);
    }
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) { $rows[] = $row; } $r->free(); }
    return $rows;
}

function api_count(mysqli $conn, string $table, string $where = '', array $params = [], string $types = ''): int {
    $sql = "SELECT COUNT(*) FROM `$table`" . ($where ? " WHERE $where" : '');
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $r = $stmt->get_result();
    } else {
        $r = $conn->query($sql);
    }
    return $r ? (int)$r->fetch_row()[0] : 0;
}

switch ($resource) {

    // ---- /contacts ----
    case 'contacts':
        if ($resource_id !== null) {
            $id = (int)$resource_id;
            $rows = api_rows($conn, 'SELECT contact_id,first_name,last_name,company,email,phone,city,province,postal_code,country,status,tags,created_at,last_modified FROM contacts WHERE contact_id = ?', [$id], 'i');
            if (!$rows) { http_response_code(404); echo json_encode(['error' => 'Not found']); break; }
            echo json_encode($rows[0]);
        } else {
            $q = trim($_GET['q'] ?? '');
            if ($q !== '') {
                $like = '%' . $q . '%';
                $total = api_count($conn, 'contacts', 'first_name LIKE ? OR last_name LIKE ? OR company LIKE ? OR email LIKE ?', [$like,$like,$like,$like], 'ssss');
                $rows  = api_rows($conn, 'SELECT contact_id,first_name,last_name,company,email,phone,city,province,status FROM contacts WHERE first_name LIKE ? OR last_name LIKE ? OR company LIKE ? OR email LIKE ? ORDER BY last_name,first_name LIMIT ? OFFSET ?', [$like,$like,$like,$like,$limit,$offset], 'ssssii');
            } else {
                $total = api_count($conn, 'contacts');
                $rows  = api_rows($conn, "SELECT contact_id,first_name,last_name,company,email,phone,city,province,status FROM contacts ORDER BY last_name,first_name LIMIT $limit OFFSET $offset");
            }
            echo json_encode(['total' => $total, 'limit' => $limit, 'offset' => $offset, 'data' => $rows]);
        }
        break;

    // ---- /opportunities ----
    case 'opportunities':
        if ($resource_id !== null) {
            $id = (int)$resource_id;
            $rows = api_rows($conn, 'SELECT * FROM opportunities WHERE opportunity_id = ?', [$id], 'i');
            if (!$rows) { http_response_code(404); echo json_encode(['error' => 'Not found']); break; }
            echo json_encode($rows[0]);
        } else {
            $stage = trim($_GET['stage'] ?? '');
            if ($stage !== '') {
                $total = api_count($conn, 'opportunities', 'stage = ?', [$stage], 's');
                $rows  = api_rows($conn, "SELECT * FROM opportunities WHERE stage = ? ORDER BY expected_close LIMIT $limit OFFSET $offset", [$stage], 's');
            } else {
                $total = api_count($conn, 'opportunities');
                $rows  = api_rows($conn, "SELECT * FROM opportunities ORDER BY expected_close LIMIT $limit OFFSET $offset");
            }
            echo json_encode(['total' => $total, 'limit' => $limit, 'offset' => $offset, 'data' => $rows]);
        }
        break;

    // ---- /tasks ----
    case 'tasks':
        if ($resource_id !== null) {
            $id = trim($resource_id);
            $rows = api_rows($conn, 'SELECT * FROM tasks WHERE id = ?', [$id], 's');
            if (!$rows) { http_response_code(404); echo json_encode(['error' => 'Not found']); break; }
            echo json_encode($rows[0]);
        } else {
            $status = trim($_GET['status'] ?? '');
            if ($status !== '') {
                $total = api_count($conn, 'tasks', 'status = ?', [$status], 's');
                $rows  = api_rows($conn, "SELECT * FROM tasks WHERE status = ? ORDER BY due_date LIMIT $limit OFFSET $offset", [$status], 's');
            } else {
                $total = api_count($conn, 'tasks');
                $rows  = api_rows($conn, "SELECT * FROM tasks ORDER BY due_date IS NULL, due_date LIMIT $limit OFFSET $offset");
            }
            echo json_encode(['total' => $total, 'limit' => $limit, 'offset' => $offset, 'data' => $rows]);
        }
        break;

    // ---- /contracts ----
    case 'contracts':
        if ($resource_id !== null) {
            $id = trim($resource_id);
            $rows = api_rows($conn, 'SELECT * FROM contracts WHERE contract_id = ?', [$id], 's');
            if (!$rows) { http_response_code(404); echo json_encode(['error' => 'Not found']); break; }
            echo json_encode($rows[0]);
        } else {
            $status = trim($_GET['status'] ?? '');
            if ($status !== '') {
                $total = api_count($conn, 'contracts', 'contract_status = ?', [$status], 's');
                $rows  = api_rows($conn, "SELECT contract_id,contact_id,customer_id,contract_type,contract_status,equipment_type,monthly_fee,annual_value,start_date,end_date,renewal_date,tank_quantity,tank_size FROM contracts WHERE contract_status = ? ORDER BY end_date LIMIT $limit OFFSET $offset", [$status], 's');
            } else {
                $total = api_count($conn, 'contracts');
                $rows  = api_rows($conn, "SELECT contract_id,contact_id,customer_id,contract_type,contract_status,equipment_type,monthly_fee,annual_value,start_date,end_date,renewal_date,tank_quantity,tank_size FROM contracts ORDER BY end_date LIMIT $limit OFFSET $offset");
            }
            echo json_encode(['total' => $total, 'limit' => $limit, 'offset' => $offset, 'data' => $rows]);
        }
        break;

    // ---- root: list available endpoints ----
    case '':
        echo json_encode([
            'api' => 'Eclipse CRM REST API',
            'version' => '1.0',
            'endpoints' => [
                'GET /api.php/contacts'        => 'List contacts (?q=search&limit=&offset=)',
                'GET /api.php/contacts/{id}'   => 'Single contact',
                'GET /api.php/opportunities'   => 'List opportunities (?stage=&limit=&offset=)',
                'GET /api.php/opportunities/{id}' => 'Single opportunity',
                'GET /api.php/tasks'           => 'List tasks (?status=&limit=&offset=)',
                'GET /api.php/tasks/{id}'      => 'Single task',
                'GET /api.php/contracts'       => 'List contracts (?status=&limit=&offset=)',
                'GET /api.php/contracts/{id}'  => 'Single contract',
            ],
            'auth' => 'Set X-API-Key header or Authorization: Bearer <key>',
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => "Unknown resource: $resource"]);
        break;
}

$conn->close();
