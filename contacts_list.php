<?php

// --- EXPORT BLOCK: must be first, before any output or includes ---
if (isset($_GET['export']) && $_GET['export'] === '1') {
  require_once __DIR__ . '/db_mysql.php';
  $schema = require __DIR__ . '/contact_schema.php';
  // Robust flatten helper (declare if not already defined)
  if (!function_exists('flattenArray')) {
    function flattenArray($arr) {
      $flat = [];
      foreach ($arr as $v) {
        if (is_array($v)) {
          foreach (flattenArray($v) as $vv) $flat[] = $vv;
        } else {
          $flat[] = $v;
        }
      }
      return $flat;
    }
  }
  $displayFields = ['contact_id', 'first_name', 'last_name', 'company', 'email'];
  $displayFieldsFromGet = isset($_GET['display']) && is_array($_GET['display']) ? $_GET['display'] : (isset($_GET['display']) ? [$_GET['display']] : null);
  if ($displayFieldsFromGet) {
    $displayFields = flattenArray($displayFieldsFromGet);
  }
  $query = strtolower(trim($_GET['query'] ?? ''));
  $field = $_GET['field'] ?? '';
  $exportAllFields = isset($_GET['export_all_fields']) && $_GET['export_all_fields'] == '1';
  $fieldsToExport = $exportAllFields ? $schema : $displayFields;
  $fieldsToExport = flattenArray($fieldsToExport); // Always flatten before use
  $conn = get_mysql_connection();
  // Build WHERE clause for export using prepared statements
  $whereConditions = [];
  $bindTypes = '';
  $bindValues = [];
  if ($query !== '') {
    $words = preg_split('/\s+/', $query);
    if ($field && in_array($field, $schema)) {
      foreach ($words as $word) {
        $whereConditions[] = "LOWER(`$field`) LIKE ?";
        $bindTypes .= 's';
        $bindValues[] = '%' . $word . '%';
      }
    } else {
      foreach ($words as $word) {
        $fieldConds = [];
        foreach ($schema as $f) {
          $fieldConds[] = "LOWER(`$f`) LIKE ?";
          $bindTypes .= 's';
          $bindValues[] = '%' . $word . '%';
        }
        $whereConditions[] = '(' . implode(' OR ', $fieldConds) . ')';
      }
    }
  }
  $where = $whereConditions ? ' WHERE ' . implode(' AND ', $whereConditions) : '';
  $sortFields = explode(',', $_GET['sort'] ?? '');
  $sortDirection = strtolower($_GET['direction'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
  $validSortFields = array_filter($sortFields, function($f) use ($schema) { return in_array($f, $schema); });
  $orderBy = '';
  if (!empty($validSortFields)) {
    $orderBy = ' ORDER BY ' . implode(', ', array_map(function($f) use ($sortDirection) {
      return "`$f` $sortDirection";
    }, $validSortFields));
  }
  $fields_sql = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $fieldsToExport));
  $sql = "SELECT $fields_sql FROM contacts$where$orderBy";
  if ($bindValues) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($bindTypes, ...$bindValues);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
  } else {
    $result = $conn->query($sql);
  }
  if (!$result) {
    header('Content-Type: text/html');
    echo "<div class='alert alert-danger' style='margin:40px auto;max-width:600px;'>Error: Unable to export contacts. Please try again later.</div>";
    $conn->close();
    exit;
  }
  $filename = 'contacts_export_' . date('Ymd_His') . '.csv';
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $output = fopen('php://output', 'w');
  if (!$output) {
    header('Content-Type: text/html');
    echo "<div class='alert alert-danger' style='margin:40px auto;max-width:600px;'>Error: Unable to open export stream.</div>";
    $result->free();
    $conn->close();
    exit;
  }
  fputcsv($output, $fieldsToExport);
  while ($contact = $result->fetch_assoc()) {
    $row = [];
    foreach ($fieldsToExport as $f) {
      $row[] = $contact[$f] ?? '';
    }
    fputcsv($output, $row);
  }
  fclose($output);
  $result->free();
  $conn->close();
  exit;
}

// --- Helper functions for main page logic ---

function fetch_contacts_mysql($schema) {
  // ...function body here...
}

// --- Main includes and initializations ---
require_once __DIR__ . '/simple_auth/middleware.php';
// Session initialization is now handled by Auth via middleware.php
require_once __DIR__ . '/sanitize_helper.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/env_loader.php';
define('DEFAULT_CONTACTS_PER_PAGE', 25); // Default number of contacts per page
define('ALLOWED_PER_PAGE_OPTIONS', [10, 25, 50, 100]);
$currentPage = basename(__FILE__);
require_once 'db_mysql.php';
require_once __DIR__ . '/daily_call_list_helper.php';
$schema = require __DIR__ . '/contact_schema.php';

load_env();
$dailyCallDefaultEmail = trim((string) (getenv('DAILY_CALL_EMAIL_TO') ?: ($_SESSION['email'] ?? 'rlee@eclipsewatertechnologies.com')));

if (isset($_POST['send_daily_call_list'])) {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('CSRF validation failed');
  }

  $sendTo = trim((string) ($_POST['daily_call_email'] ?? $dailyCallDefaultEmail));
  if ($sendTo === '' || !filter_var($sendTo, FILTER_VALIDATE_EMAIL)) {
    header('Location: contacts_list.php?daily_call_status=error&daily_call_error=' . urlencode('Recipient email is invalid.'));
    exit;
  }

  $dailyConn = get_mysql_connection();
  ensure_daily_call_tracking_table($dailyConn);
  $dailyCandidates = fetch_daily_ontario_call_candidates($dailyConn, 10);

  if (empty($dailyCandidates)) {
    $dailyConn->close();
    header('Location: contacts_list.php?daily_call_status=empty');
    exit;
  }

  $sendResult = send_daily_call_email($sendTo, $dailyCandidates);
  if (!empty($sendResult['ok'])) {
    $candidateIds = array_map(static function ($row) {
      return (string) ($row['contact_id'] ?? '');
    }, $dailyCandidates);
    mark_daily_call_contacts_sent($dailyConn, $candidateIds);
    $dailyConn->close();
    header('Location: contacts_list.php?daily_call_status=sent&daily_call_count=' . count($dailyCandidates));
    exit;
  }

  $dailyConn->close();
  header('Location: contacts_list.php?daily_call_status=error&daily_call_error=' . urlencode((string) ($sendResult['error'] ?? 'Email send failed.')));
  exit;
}

if (isset($_POST['mark_called_contact'])) {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('CSRF validation failed');
  }

  $contactIdToMark = trim((string) ($_POST['contact_id'] ?? ''));
  if ($contactIdToMark !== '') {
    $dailyConn = get_mysql_connection();
    ensure_daily_call_tracking_table($dailyConn);
    mark_daily_call_contact_called($dailyConn, $contactIdToMark, (string) ($_SESSION['username'] ?? 'system'));
    $dailyConn->close();
  }

  // Preserve current table state and return near the contact that was just updated.
  $redirectParams = [];
  $paramsToPropagate = ['page', 'query', 'sort', 'direction', 'per_page', 'field', 'call_ready'];
  foreach ($paramsToPropagate as $p) {
    if (isset($_GET[$p])) {
      $redirectParams[$p] = $_GET[$p];
    }
  }
  if (isset($_GET['display'])) {
    $redirectParams['display'] = $_GET['display'];
  }
  $redirectParams['daily_call_status'] = 'marked';

  $fragment = $contactIdToMark !== '' ? '#contact-' . rawurlencode($contactIdToMark) : '';
  header('Location: contacts_list.php?' . http_build_query($redirectParams) . $fragment);
  exit;
}

// Always initialize these variables before any use
$total_contacts = 0;
$offset = 0;
$per_page = DEFAULT_CONTACTS_PER_PAGE;
$total_pages = 1;
$displayFields = ['first_name', 'last_name', 'company', 'email', 'phone', 'address', 'city', 'province', 'postal_code', 'country', 'notes', 'category', 'created_at', 'last_modified', 'is_customer', 'tank_number', 'delivery_date', 'tags']; // contact_id intentionally omitted from default

// Helper to flatten display[] if nested (prevents 'Array' column bug)
function flattenDisplayFields($arr) {
  $flat = [];
  foreach ($arr as $v) {
    if (is_array($v)) {
      foreach ($v as $vv) $flat[] = $vv;
    } else {
      $flat[] = $v;
    }
  }
  return $flat;
}

$displayFieldsFromGet = isset($_GET['display']) && is_array($_GET['display']) ? $_GET['display'] : (isset($_GET['display']) ? [$_GET['display']] : null);
if (isset($_POST['apply'])) {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('CSRF validation failed');
  }
  // Validate against schema before saving
  $postedRaw = (isset($_POST['display']) && is_array($_POST['display'])) ? $_POST['display'] : [];
  $posted = array_values(array_intersect(flattenDisplayFields($postedRaw), $schema));
  if (empty($posted)) { $posted = [$schema[0]]; } // always keep at least one column
  $_SESSION['displayFields'] = $posted;
  // Persist in a long-lived cookie so it survives session resets
  setcookie('crm_display_fields', json_encode($posted), [
    'expires' => time() + 60 * 60 * 24 * 365,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  // PRG: redirect back so Back button and navigation work correctly
  $qs = http_build_query(['applied' => '1']);
  header('Location: contacts_list.php?' . $qs);
  exit;
} elseif ($displayFieldsFromGet) {
  $displayFields = array_values(array_intersect(flattenDisplayFields($displayFieldsFromGet), $schema));
  if (!empty($displayFields)) { $_SESSION['displayFields'] = $displayFields; }
} elseif (isset($_SESSION['displayFields']) && is_array($_SESSION['displayFields'])) {
  $displayFields = flattenDisplayFields($_SESSION['displayFields']);
} elseif (isset($_COOKIE['crm_display_fields'])) {
  // Cookie fallback: survives session resets (e.g. after login/CSRF refresh)
  $fromCookie = json_decode($_COOKIE['crm_display_fields'], true);
  if (is_array($fromCookie)) {
    $displayFields = array_values(array_intersect(flattenDisplayFields($fromCookie), $schema));
    if (!empty($displayFields)) { $_SESSION['displayFields'] = $displayFields; }
  }
}
// Ensure displayFields is never empty
if (empty($displayFields)) {
  $displayFields = ['first_name', 'last_name', 'company', 'email', 'phone'];
}

// Include layout only after preference handling so header/setcookie work.
require_once __DIR__ . '/layout_start.php';

// ✅ PAGINATION: Get current page and per-page setting
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], ALLOWED_PER_PAGE_OPTIONS) 
    ? (int)$_GET['per_page'] 
    : DEFAULT_CONTACTS_PER_PAGE;

$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Handle query and sort
$query = strtolower(trim($_GET['query'] ?? ''));
$field = $_GET['field'] ?? '';
$sortFields = explode(',', $_GET['sort'] ?? '');
$sortDirection = $_GET['direction'] ?? 'asc';
$callReadyOnly = isset($_GET['call_ready']) && $_GET['call_ready'] === '1';
$activeSort = array_flip($sortFields);




// Build WHERE clause for both count and data queries using prepared statements
$conn = get_mysql_connection();
if ($callReadyOnly) {
  ensure_daily_call_tracking_table($conn);
}
$whereConditions = [];
$bindTypes = '';
$bindValues = [];

if ($callReadyOnly) {
  $whereConditions[] = "TRIM(COALESCE(`phone`, '')) <> ''";
  $whereConditions[] = "LOWER(TRIM(COALESCE(`province`, ''))) IN ('on', 'ontario')";
  $whereConditions[] = "NOT EXISTS (SELECT 1 FROM daily_call_tracking dct WHERE dct.contact_id = contacts.contact_id AND dct.called_at IS NOT NULL)";
}

if ($query !== '') {
  $words = preg_split('/\s+/', $query);
  if ($field && in_array($field, $schema)) {
    foreach ($words as $word) {
      $whereConditions[] = "LOWER(`$field`) LIKE ?";
      $bindTypes .= 's';
      $bindValues[] = '%' . $word . '%';
    }
  } else {
    foreach ($words as $word) {
      $fieldConds = [];
      foreach ($schema as $f) {
        $fieldConds[] = "LOWER(`$f`) LIKE ?";
        $bindTypes .= 's';
        $bindValues[] = '%' . $word . '%';
      }
      $whereConditions[] = '(' . implode(' OR ', $fieldConds) . ')';
    }
  }
}
$where = $whereConditions ? ' WHERE ' . implode(' AND ', $whereConditions) : '';
// Count query
if ($bindValues) {
  $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM contacts$where");
  $countStmt->bind_param($bindTypes, ...$bindValues);
  $countStmt->execute();
  $count_result = $countStmt->get_result();
  $countStmt->close();
} else {
  $count_result = $conn->query("SELECT COUNT(*) as cnt FROM contacts$where");
}
$total_contacts = 0;
if ($count_result) {
  $row = $count_result->fetch_assoc();
  $total_contacts = (int)($row['cnt'] ?? 0);
  $count_result->free();
}
$total_pages = max(1, ceil($total_contacts / $per_page));
$current_page = min($current_page, $total_pages); // Ensure current page doesn't exceed total pages
$offset = ($current_page - 1) * $per_page;
// Data query (paginated)
// Always flatten $displayFields before using for SQL
// Always flatten $displayFields before using for SQL (prevents 'Array' bug)
if (function_exists('flattenArray')) {
  $fieldsForSelect = flattenArray($displayFields);
} else if (function_exists('flattenDisplayFields')) {
  $fieldsForSelect = flattenDisplayFields($displayFields);
} else {
  $fieldsForSelect = $displayFields;
}
if (!in_array('contact_id', $fieldsForSelect, true)) {
  array_unshift($fieldsForSelect, 'contact_id'); // Always include contact_id as first field
}
$fields_sql = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $fieldsForSelect));
$sortFields = array_filter($sortFields, function($f) use ($schema) { return in_array($f, $schema); });
$orderBy = '';
if (!empty($sortFields)) {
  $orderBy = ' ORDER BY ' . implode(', ', array_map(function($f) use ($sortDirection) {
    return "`$f` $sortDirection";
  }, $sortFields));
}
$sql = "SELECT $fields_sql FROM contacts$where$orderBy LIMIT $per_page OFFSET $offset";
$contacts = [];
if ($bindValues) {
  $dataStmt = $conn->prepare($sql);
  $dataStmt->bind_param($bindTypes, ...$bindValues);
  $dataStmt->execute();
  $result = $dataStmt->get_result();
  $dataStmt->close();
} else {
  $result = $conn->query($sql);
}
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
  }
  $result->free();
}
$conn->close();
// Only keep the debug output logic in pure PHP, not as a mixed PHP/HTML block at this location
$showDebug = $debugMode && !empty($debugOutput);
// Detect duplicates
$emailCount = [];
foreach ($contacts as $c) {
    $email = strtolower(trim($c['email'] ?? ''));
    if (!empty($email)) {
        $emailCount[$email] = ($emailCount[$email] ?? 0) + 1;
    }
}
$page_contacts = $contacts;

$calledContactMap = [];
$visibleContactIds = array_values(array_filter(array_map(static function ($row) {
  return (string) ($row['contact_id'] ?? '');
}, $page_contacts), static function ($id) {
  return trim($id) !== '';
}));

if (!empty($visibleContactIds)) {
  $dailyConn = get_mysql_connection();
  ensure_daily_call_tracking_table($dailyConn);
  $calledContactMap = fetch_called_contact_id_map($dailyConn, $visibleContactIds);
  $dailyConn->close();
}


?>

<div class="container-fluid px-0">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white rounded shadow-sm p-3">
    <div class="d-flex flex-column">
      <button type="button" class="btn btn-outline-secondary mb-2" onclick="document.getElementById('fieldPanel').style.display = (document.getElementById('fieldPanel').style.display === 'none' ? 'block' : 'none');">
        <i class="bi bi-sliders"></i> Customize Visible Columns
      </button>

      <span class="text-muted mb-0" style="font-size:16px;">Total: <strong><?= $total_contacts ?></strong></span>
      <!-- Removed the fetch_contacts_mysql function call and related code -->
      <a href="import_contacts.php" class="btn btn-outline-secondary">
        <i class="bi bi-upload"></i> <span style="font-weight:500;">Import</span>
      </a>
      <a href="contact_form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> <span style="font-weight:500;">Add Contact</span>
      </a>
      <form method="POST" class="mt-2 d-flex flex-wrap gap-2 align-items-center">
        <?php renderCSRFInput(); ?>
        <input type="hidden" name="send_daily_call_list" value="1">
        <input type="email" name="daily_call_email" class="form-control form-control-sm" value="<?= htmlspecialchars($dailyCallDefaultEmail) ?>" placeholder="your@email.com" style="min-width:220px;">
        <button type="submit" class="btn btn-warning btn-sm">
          <i class="bi bi-telephone"></i> Email 10 Ontario Call Contacts
        </button>
      </form>
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-center mb-3 gap-2 bg-light rounded p-3 border">
    <form method="GET" action="contacts_list.php" class="d-flex flex-wrap gap-2 align-items-center mb-0" id="contacts-search-form">
      <input type="text" name="query" class="form-control" placeholder="Search" value="<?= htmlspecialchars($_GET['query'] ?? '') ?>" style="min-width:220px;">
      <?php if ($field !== ''): ?>
        <input type="hidden" name="field" value="<?= e($field) ?>">
      <?php endif; ?>
      <input type="hidden" name="sort" value="<?= e($_GET['sort'] ?? '') ?>">
      <input type="hidden" name="direction" value="<?= e($sortDirection) ?>">
      <input type="hidden" name="per_page" value="<?= (int) $per_page ?>">
      <?php if ($callReadyOnly): ?>
        <input type="hidden" name="call_ready" value="1">
      <?php endif; ?>
      <?php foreach ($displayFields as $df): ?>
        <input type="hidden" name="display[]" value="<?= e($df) ?>">
      <?php endforeach; ?>
      <div class="form-check ms-2" style="margin-bottom:0;">
        <input type="checkbox" class="form-check-input" id="exportAllFields" name="export_all_fields" value="1">
        <label class="form-check-label" for="exportAllFields" style="font-weight:400;font-size:15px;">Export all fields</label>
      </div>
      <button type="submit" class="btn btn-primary">Search</button>
      <a href="contacts_list.php?call_ready=1" class="btn <?= $callReadyOnly ? 'btn-success' : 'btn-outline-success' ?>">Call List Ready</a>
      <button type="button" class="btn btn-success" onclick="exportContacts()">Export</button>
      <?php if ($query !== ''): ?>
        <a href="contacts_list.php<?= $callReadyOnly ? '?call_ready=1' : '' ?>" class="btn btn-outline-secondary">Clear</a>
      <?php endif; ?>
    </form>
    <script>
    function exportContacts() {
      const form = document.getElementById('contacts-search-form');
      const url = new URL(form.action, window.location.origin);
      const formData = new FormData(form);
      // Add all form fields except export button
      for (const [key, value] of formData.entries()) {
        if (key !== 'export') {
          url.searchParams.set(key, value);
        }
      }
      // Always set export=1
      url.searchParams.set('export', '1');
      // Handle export all fields checkbox
      if (document.getElementById('exportAllFields').checked) {
        url.searchParams.set('export_all_fields', '1');
      } else {
        url.searchParams.delete('export_all_fields');
      }
      window.location.href = url.toString();
    }
    </script>
    <span class="ms-3 text-muted" style="font-size:15px;">Showing <strong><?= $total_contacts > 0 ? ($offset + 1) : 0 ?></strong>–<strong><?= min($offset + $per_page, $total_contacts) ?></strong> of <strong><?= $total_contacts ?></strong> contacts</span>
    <?php if ($total_pages > 1): ?>
      <span class="ms-3 text-muted" style="font-size:15px;">Page <strong><?= $current_page ?></strong> of <strong><?= $total_pages ?></strong></span>
      <nav aria-label="Contacts pagination">
        <ul class="pagination" style="margin: 10px 0;">
          <?php
          $range = 2; // pages to show before/after current
          $showPages = [];
          $showPages[] = 1;
          for ($i = $current_page - $range; $i <= $current_page + $range; $i++) {
            if ($i > 1 && $i < $total_pages) $showPages[] = $i;
          }
          if ($total_pages > 1) $showPages[] = $total_pages;
          $last = 0;
          foreach ($showPages as $p) {
            if ($p - $last > 1) {
              echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            echo '<li class="page-item' . ($p == $current_page ? ' active' : '') . '">';
            echo '<a class="page-link" href="?page=' . $p . '&query=' . urlencode($_GET['query'] ?? '') . '&field=' . urlencode($field) . '&sort=' . urlencode($_GET['sort'] ?? '') . '&direction=' . $sortDirection . '&per_page=' . $per_page;
            if ($callReadyOnly) { echo '&call_ready=1'; }
            foreach ($displayFields as $df) { echo '&display[]=' . urlencode($df); }
            echo '">' . $p . '</a>';
            echo '</li>';
            $last = $p;
          }
          ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($fieldSaveError)): ?>
  <div class="alert alert-error" style="margin-top:70px;z-index:1050;position:relative;"> <?= e($fieldSaveError) ?> </div>
<?php elseif (isset($_GET['applied']) && $_GET['applied'] === '1'): ?>
  <div class="alert alert-success" style="margin-top:70px;z-index:1050;position:relative;">Column visibility updated.</div>
<?php elseif (isset($_GET['daily_call_status']) && $_GET['daily_call_status'] === 'sent'): ?>
  <div class="alert alert-success" style="margin-top:70px;z-index:1050;position:relative;">Daily call list emailed successfully (<?= (int) ($_GET['daily_call_count'] ?? 0) ?> contacts).</div>
<?php elseif (isset($_GET['daily_call_status']) && $_GET['daily_call_status'] === 'empty'): ?>
  <div class="alert alert-warning" style="margin-top:70px;z-index:1050;position:relative;">No eligible Ontario contacts with phone numbers were found for today's list.</div>
<?php elseif (isset($_GET['daily_call_status']) && $_GET['daily_call_status'] === 'marked'): ?>
  <div class="alert alert-success" style="margin-top:70px;z-index:1050;position:relative;">Contact marked as called.</div>
<?php elseif (isset($_GET['daily_call_status']) && $_GET['daily_call_status'] === 'error'): ?>
  <div class="alert alert-error" style="margin-top:70px;z-index:1050;position:relative;">Failed to send daily call list: <?= e($_GET['daily_call_error'] ?? 'Unknown error') ?></div>
<?php endif; ?>

<!-- Field Visibility Panel -->
<?php
$openFieldPanel = false; // Hide the Customize Visible Columns panel by default
?>
<div id="fieldPanel" class="card shadow-sm mb-4" style="display:<?= $openFieldPanel ? 'block' : 'none' ?>; max-width: 600px;">
  <form method="POST" class="p-3">
    <input type="hidden" name="apply" value="1">
    <?php renderCSRFInput(); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Customize Visible Columns</h5>
      <button type="button" class="btn-close js-toggle-panel" data-target="fieldPanel" aria-label="Close"></button>
    </div>
    <div class="row g-2 mb-3">
      <?php foreach ($schema as $f): ?>
        <div class="col-6 col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="display[]" value="<?= $f ?>" id="field-<?= $f ?>" <?= in_array($f, $displayFields) ? 'checked' : '' ?><?= $f === 'contact_id' ? ' style="display:none;"' : '' ?>>
            <label class="form-check-label" for="field-<?= $f ?>"<?= $f === 'contact_id' ? ' style="display:none;"' : '' ?>>
              <?= ucfirst(str_replace('_', ' ', $f)) ?>
            </label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2 justify-content-end">
      <button type="submit" class="btn btn-primary">Apply Changes</button>
      <button type="button" class="btn btn-outline-secondary js-toggle-panel" data-target="fieldPanel" onclick="window.location.href='contacts_list.php?openPanel=1'">Cancel</button>
    </div>
  </form>
</div>


    <!-- Contacts Table -->
    <!-- Contacts Table -->
    <div id="table-scroll-outer" class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th style="min-width:120px;">Actions</th>
            <?php foreach ($displayFields as $f): ?>
              <th>
                <?php
                  // Build the sort link with all displayFields as display[] params
                  $sortUrl = '?query=' . urlencode($_GET['query'] ?? '') . '&field=' . urlencode($field) . '&sort=' . e($f) . '&direction=' . ((in_array($f, $sortFields) && $sortDirection === 'asc') ? 'desc' : 'asc') . '&per_page=' . $per_page;
                  if ($callReadyOnly) { $sortUrl .= '&call_ready=1'; }
                  foreach ($displayFields as $df) { $sortUrl .= '&display[]=' . urlencode($df); }
                ?>
                <a href="<?= $sortUrl ?>" class="text-decoration-none text-dark">
                  <?= ucfirst(str_replace('_', ' ', $f)) ?>
                  <?php if (isset($activeSort[$f])): ?>
                    <i class="bi bi-caret-<?= $sortDirection === 'desc' ? 'down' : 'up' ?>-fill ms-1"></i>
                  <?php else: ?>
                    <i class="bi bi-arrow-down-up ms-1 text-secondary"></i>
                  <?php endif; ?>
                </a>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($page_contacts)): ?>
            <tr>
              <td colspan="<?= count($displayFields) + 1 ?>" class="empty-state">
                <div class="empty-state-content">
                  <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm.256 7a4.474 4.474 0 0 1-.229-1.004H3c.001-.246.154-.986.832-1.664C4.484 10.68 5.711 10 8 10c.26 0 .507.009.74.025.226-.341.496-.65.804-.918C9.077 9.038 8.564 9 8 9c-5 0-6 3-6 4s1 1 1 1h5.256Z"/>
                    <path d="M16 12.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Zm-1.993-1.679a.5.5 0 0 0-.686.172l-1.17 1.95-.547-.547a.5.5 0 0 0-.708.708l.774.773a.75.75 0 0 0 1.174-.144l1.335-2.226a.5.5 0 0 0-.172-.686Z"/>
                  </svg>
                  <h3>No contacts found</h3>
                  <p><?= $query ? 'Try adjusting your search or filters' : 'Get started by adding your first contact' ?></p>
                  <?php if (!$query): ?>
                    <a href="contact_form.php" class="btn btn-primary">Add Contact</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($page_contacts as $contact): ?>
              <?php
                $id = isset($contact['contact_id']) ? $contact['contact_id'] : '';
                $email = $contact['email'] ?? '';
                $isDuplicate = !empty($email) && $emailCount[strtolower(trim($email))] > 1;
                $isCalled = !empty($calledContactMap[(string) $id]);
              ?>
                <tr id="contact-<?= escapeAttr($id) ?>" class="contact-row" data-contact-id="<?= escapeAttr($id) ?>">
                <td>
                  <div class="btn-group" role="group">
                    <a href="contact_view.php?id=<?= escapeAttr($id) ?>" class="btn btn-sm btn-outline-primary" title="View contact"><i class="bi bi-person-lines-fill"></i></a>
                    <?php
                      // Build edit link with all current GET params for redirect after save
                      $editParams = [
                        'id' => $id,
                        'edit' => 1
                      ];
                      $paramsToPropagate = ['page','query','sort','direction','per_page','field','call_ready'];
                      foreach ($paramsToPropagate as $p) {
                        if (isset($_GET[$p])) {
                          $editParams[$p] = $_GET[$p];
                        }
                      }
                      if (isset($_GET['display']) && is_array($_GET['display'])) {
                        foreach ($_GET['display'] as $df) {
                          $editParams['display[]'][] = $df;
                        }
                      } elseif (isset($_GET['display'])) {
                        $editParams['display[]'][] = $_GET['display'];
                      }
                      $editUrl = 'contact_view.php?' . http_build_query($editParams) . '#edit';
                    ?>
                    <a href="<?= htmlspecialchars($editUrl) ?>" class="btn btn-sm btn-outline-secondary" title="Edit contact"><i class="bi bi-pencil"></i></a>
                    <form method="POST" class="d-inline">
                      <?php renderCSRFInput(); ?>
                      <input type="hidden" name="mark_called_contact" value="1">
                      <input type="hidden" name="contact_id" value="<?= escapeAttr($id) ?>">
                      <button type="submit" class="btn btn-sm <?= $isCalled ? 'btn-success' : 'btn-outline-success' ?>" title="<?= $isCalled ? 'Already marked called' : 'Mark as called' ?>" <?= $isCalled ? 'disabled' : '' ?>>
                        <i class="bi bi-telephone-check"></i>
                      </button>
                    </form>
                    <form method="POST" action="delete_contact.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this contact?');">
                      <?php renderCSRFInput(); ?>
                      <input type="hidden" name="contact_id" value="<?= escapeAttr($id) ?>">
                      <?php
                        // Propagate all relevant GET params as hidden fields for redirect after delete
                        $paramsToPropagate = ['page','query','sort','direction','per_page','field','call_ready'];
                        // Helper to recursively flatten arrays (declare only if not already defined)
                        if (!function_exists('flattenArray')) {
                          function flattenArray($arr) {
                            $flat = [];
                            foreach ($arr as $v) {
                              if (is_array($v)) {
                                foreach (flattenArray($v) as $vv) $flat[] = $vv;
                              } else {
                                $flat[] = $v;
                              }
                            }
                            return $flat;
                          }
                        }
                        foreach ($paramsToPropagate as $p) {
                          if (isset($_GET[$p])) {
                            $val = $_GET[$p];
                            if (is_array($val)) {
                              foreach (flattenArray($val) as $v) {
                                echo '<input type="hidden" name="' . htmlspecialchars($p) . '[]" value="' . htmlspecialchars($v) . '">';
                              }
                            } else {
                              echo '<input type="hidden" name="' . htmlspecialchars($p) . '" value="' . htmlspecialchars($val) . '">';
                            }
                          }
                        }
                        if (isset($_GET['display']) && is_array($_GET['display'])) {
                          foreach (flattenArray($_GET['display']) as $df) {
                            echo '<input type="hidden" name="display[]" value="' . htmlspecialchars($df) . '">';
                          }
                        } elseif (isset($_GET['display'])) {
                          echo '<input type="hidden" name="display[]" value="' . htmlspecialchars($_GET['display']) . '">';
                        }
                      ?>
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete contact"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php if (!empty($contact['company'])): ?>
                      <a href="contact_form.php?company=<?= urlencode($contact['company']) ?>" class="btn btn-sm btn-outline-success ms-1" title="Add another contact for this company">
                        <i class="bi bi-person-plus"></i>
                      </a>
                      <?php
                        // Check if customer already exists for this company
                        $conn = get_mysql_connection();
                        $companyName = $contact['company'];
                        $stmt = $conn->prepare("SELECT customer_id FROM customers LEFT JOIN contacts ON customers.contact_id = contacts.contact_id WHERE contacts.company = ?");
                        $stmt->bind_param('s', $companyName);
                        $stmt->execute();
                        $stmt->bind_result($customerId);
                        $hasCustomer = $stmt->fetch();
                        $stmt->close();
                        $conn->close();
                      ?>
                    <?php endif; ?>
                  </div>
                </td>
                <?php foreach ($displayFields as $f): ?>
                  <td>
                    <?php if ($f === 'email'): ?>
                      <span class="d-flex align-items-center gap-2">
                        <?= e($contact[$f] ?? '') ?>
                        <?php if ($isDuplicate): ?>
                          <span class="badge bg-danger" title="Duplicate email detected">Duplicate</span>
                        <?php endif; ?>
                      </span>
                    <?php elseif ($f === 'company'): ?>
                      <strong><?= e($contact[$f] ?? '') ?></strong>
                    <?php else: ?>
                      <?= e($contact[$f] ?? '') ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
/* ========== PAGE HEADER ========== */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 28px;
  flex-wrap: wrap;
  gap: 16px;
  position: relative;
  z-index: 5;
}

.page-title-section h1 {
  margin: 0;
  font-size: 32px;
  font-weight: 700;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-subtitle {
  margin: 4px 0 0 0;
  font-size: 14px;
  color: #6b7280;
  font-weight: 400;
}

.page-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  position: relative;
  z-index: 6;
}

.page-actions .btn {
  position: relative;
  z-index: 7;
}

.page-actions .btn svg {
  margin-right: 6px;
}

/* ========== COLLAPSIBLE PANEL ========== */
.collapsible-panel {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
  border-bottom: 1px solid #e5e7eb;
}

.panel-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #374151;
}

.btn-close {
  background: none;
  border: none;
  font-size: 28px;
  color: #9ca3af;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: all 0.2s;
}

.btn-close:hover {
  background: #e5e7eb;
  color: #374151;
}

.panel-body {
  padding: 20px;
}

.panel-footer {
  padding: 16px 20px;
  background: #f9fafb;
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.checkbox-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 6px;
  transition: background 0.2s;
}

.checkbox-label:hover {
  background: #f3f4f6;
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #0099A8;
}

.checkbox-text {
  font-size: 14px;
  color: #374151;
  user-select: none;
}

/* ========== SEARCH & FILTER BAR ========== */
.search-filter-section {
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.quick-search-bar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input-wrapper {
  position: relative;
  flex: 1;
  min-width: 280px;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 10px 40px 10px 44px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 15px;
  transition: all 0.2s;
}

.search-input:focus {
  outline: none;
  border-color: #0099A8;
  box-shadow: 0 0 0 3px rgba(0, 153, 168, 0.1);
}

.clear-search {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.clear-search:hover {
  background: #f3f4f6;
  color: #374151;
}

.filter-select {
  padding: 10px 14px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 160px;
}

.filter-select:focus {
  outline: none;
  border-color: #0099A8;
  box-shadow: 0 0 0 3px rgba(0, 153, 168, 0.1);
}

.advanced-filters {
  padding: 20px;
  background: #f9fafb;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.filter-item label {
  display: block;
  margin-bottom: 6px;
  font-weight: 500;
  font-size: 14px;
  color: #374151;
}

.form-control {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  transition: all 0.2s;
}

.form-control:focus {
  outline: none;
  border-color: #0099A8;
  box-shadow: 0 0 0 3px rgba(0, 153, 168, 0.1);
}

/* ========== RESULTS INFO BAR ========== */
.results-info-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  border-radius: 8px;
  margin-bottom: 16px;
  border: 1px solid #bae6fd;
}

.results-count {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #374151;
}

.text-muted {
  color: #6b7280;
  font-size: 13px;
}

.pagination-info {
  font-size: 14px;
  color: #6b7280;
}

/* ========== MODERN PAGINATION ========== */
.pagination-nav {
  margin-top: 12px;
  padding-top: 8px;
  border-top: 1px solid #e5e7eb;
}

.pagination-controls {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 2px;
  flex-wrap: wrap;
}

.pagination-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid #d1d5db;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  color: #374151;
  text-decoration: none;
}

.pagination-btn:hover {
  background: #f3f4f6;
  border-color: #0099A8;
  color: #0099A8;
}

.pagination-btn-disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination-btn-disabled:hover {
  background: white;
  border-color: #d1d5db;
  color: #374151;
}

.pagination-pages {
  display: flex;
  gap: 2px;
  align-items: center;
}

.pagination-page {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border: 1px solid #d1d5db;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  color: #374151;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
}

.pagination-page:hover {
  background: #f3f4f6;
  border-color: #0099A8;
  color: #0099A8;
}

.pagination-page-active {
  background: linear-gradient(135deg, #0099A8 0%, #00859a 100%);
  color: white;
  border-color: #0099A8;
  font-weight: 600;
}

.pagination-page-active:hover {
  background: linear-gradient(135deg, #00859a 0%, #007489 100%);
  color: white;
}

.pagination-ellipsis {
  color: #9ca3af;
  padding: 0 4px;
}

/* ========== TABLE STYLES ========== */
.table-container {
  overflow-x: auto;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  margin-top: 16px;
}

.contacts-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.contacts-table thead {
  background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
  border-bottom: 2px solid #e5e7eb;
}

.contacts-table th {
  padding: 14px 16px;
  text-align: left;
  font-weight: 600;
  font-size: 13px;
  color: #374151;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
}

.th-actions {
  width: 140px;
  min-width: 140px;
}

.th-sortable {
  cursor: pointer;
}

.sort-header {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #374151;
  text-decoration: none;
  transition: color 0.2s;
}

.sort-header:hover {
  color: #0099A8;
}

.sort-icon {
  font-size: 14px;
  color: #0099A8;
  font-weight: bold;
}

.sort-icon-inactive {
  color: #d1d5db;
  font-weight: normal;
}

.contacts-table tbody tr {
  border-bottom: 1px solid #f3f4f6;
  transition: all 0.15s;
}

.contacts-table tbody tr:hover {
  background: #f9fafb;
}

.contacts-table td {
  padding: 14px 16px;
  font-size: 14px;
  color: #374151;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 0;
  vertical-align: middle;
  box-sizing: border-box;
}

.contacts-table td:hover {
  overflow: visible;
  white-space: normal;
}

.actions-cell {
  padding: 10px 16px !important;
}

.action-buttons {
  display: flex;
  gap: 6px;
  align-items: center;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1px solid #e5e7eb;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  color: #6b7280;
  text-decoration: none;
}

.action-btn:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
  transform: translateY(-1px);
}

.action-btn-view:hover {
  background: #dbeafe;
  border-color: #93c5fd;
  color: #1e40af;
}

.action-btn-edit:hover {
  background: #fef3c7;
  border-color: #fcd34d;
  color: #92400e;
}

.action-btn-delete:hover {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #991b1b;
}

.email-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  white-space: normal !important;
  overflow: visible !important;
}

.email-cell-cell {
  white-space: normal;
}

.contacts-table td.company-cell {
  font-weight: 600;
  white-space: normal;
  word-wrap: break-word;
}

/* ========== EMPTY STATE ========== */
.empty-state {
  padding: 60px 20px;
  text-align: center;
}

.empty-state-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  color: #6b7280;
}

.empty-state-content svg {
  color: #d1d5db;
  margin-bottom: 8px;
}

.empty-state-content h3 {
  margin: 0;
  font-size: 20px;
  color: #374151;
  font-weight: 600;
}

.empty-state-content p {
  margin: 0;
  font-size: 14px;
  color: #9ca3af;
  max-width: 400px;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .page-title-section h1 {
    font-size: 24px;
  }
  
  .quick-search-bar {
    flex-direction: column;
  }
  
  .search-input-wrapper {
    min-width: 100%;
  }
  
  .filter-grid {
    grid-template-columns: 1fr;
  }
  
  .checkbox-grid {
    grid-template-columns: 1fr;
  }
  
  .table-container {
    border-radius: 0;
    margin-left: -20px;
    margin-right: -20px;
    border-left: 0;
    border-right: 0;
  }
  
  .pagination-controls {
    gap: 4px;
  }
  
  .pagination-btn,
  .pagination-page {
    width: 32px;
    height: 32px;
    min-width: 32px;
    font-size: 13px;
  }
}
</style>



<div style="margin: 24px 0; text-align: right;">
  <a href="export_table.php" class="btn btn-primary" style="font-size:16px;padding:8px 20px;">
    Export Data Tables
  </a>
</div>

<!-- Footer can be included here if layout_end.php provides it -->
</main>
</body>
<script src="js/modern-ui.js"></script>
<?php if ($openFieldPanel): ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var panel = document.getElementById('fieldPanel');
    if (panel) panel.style.display = 'block';
  });
</script>
<?php endif; ?>
</html>
