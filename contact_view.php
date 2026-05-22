<?php
ob_start();
// Security headers
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff;');

// Page metadata
$pageTitle = 'Contact Details';

// Load ONLY required functions BEFORE output - do NOT include layout_start.php yet!
require_once 'db_mysql.php';
require_once 'audit_handler.php';
require_once __DIR__ . '/csrf_helper.php';
initializeCSRFToken();
require_once 'simple_auth/middleware.php';

$crmConn = get_mysql_connection();
$saveSuccess = false;
$pageError = '';

$errorMap = [
  'csrf' => 'Security token validation failed. Refresh the page and try again.',
  'missing_discussion_fields' => 'Please provide Your Name and Notes before adding a log entry.',
  'discussion_prepare' => 'Could not prepare the discussion save operation. Please try again.',
  'discussion_insert' => 'Could not save the discussion entry due to a database error.',
  'update_prepare' => 'Could not prepare the contact update operation. Please try again.',
  'update_execute' => 'Could not save contact changes due to a database error.',
  'unknown_post' => 'The submitted form action was not recognized. Please retry from the page form.',
];

$errorCode = trim((string) ($_GET['error'] ?? ''));
if ($errorCode !== '' && isset($errorMap[$errorCode])) {
  $pageError = $errorMap[$errorCode];
}

function redirect_safely(string $url): void {
  if (!headers_sent()) {
    header('Location: ' . $url);
    exit;
  }

  $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
  echo '<script>window.location.href=' . json_encode($url) . ';</script>';
  echo '<noscript><meta http-equiv="refresh" content="0;url=' . $escaped . '"></noscript>';
  exit;
}

// Handle contact update form submission

// Handle discussion log form submission
$isDiscussionPost = (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && isset($_POST['contact_id'])
  && (
    isset($_POST['add_discussion'])
    || (($_POST['form_action'] ?? '') === 'add_discussion')
  )
);

if ($isDiscussionPost) {
  file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " FORM RECEIVED: add_discussion POST\n", FILE_APPEND);
  $contactId = trim((string) ($_POST['contact_id'] ?? ''));
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " CSRF FAILED\n", FILE_APPEND);
    redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&error=csrf");
  } else {
    $author = trim((string) ($_POST['author'] ?? ''));
    $entryText = trim((string) ($_POST['entry_text'] ?? ''));
    $linkedOppId = trim((string) ($_POST['linked_opportunity_id'] ?? ''));
    $visibility = 'private';
    file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " Fields: contactId=$contactId, author=$author, hasText=" . (!empty($entryText) ? 'yes' : 'no') . "\n", FILE_APPEND);
    if ($contactId && $author && $entryText) {
      $sql = "INSERT INTO discussion_log (contact_id, author, entry_text, linked_opportunity_id, visibility, timestamp) VALUES (?, ?, ?, ?, ?, NOW())";
      $stmt = $crmConn->prepare($sql);
      if ($stmt) {
        $linkedOppIdNull = ($linkedOppId === '') ? null : $linkedOppId;
        $stmt->bind_param('sssss', $contactId, $author, $entryText, $linkedOppIdNull, $visibility);
        if ($stmt->execute()) {
          file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " SUCCESS: Inserted discussion for contact_id=$contactId, author=$author\n", FILE_APPEND);
          redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&log_added=1#discussions");
        } else {
          file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " ERROR: Insert failed - " . $stmt->error . "\n", FILE_APPEND);
          redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&error=discussion_insert");
        }
        $stmt->close();
      } else {
        file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " ERROR: Prepare failed (discussion) - " . $crmConn->error . "\n", FILE_APPEND);
        redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&error=discussion_prepare");
      }
    } else {
      redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&error=missing_discussion_fields");
    }
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_id']) && !$isDiscussionPost) {
  $contactId = trim((string) ($_POST['contact_id'] ?? ''));
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&error=csrf");
  } else {
    $fields = [
      'first_name', 'last_name', 'company', 'email', 'phone', 'address',
      'city', 'province', 'postal_code', 'country', 'notes', 'status'
    ];

    // Fetch old contact data before update for audit logging
    $oldContact = null;
    $stmtOld = $crmConn->prepare('SELECT * FROM contacts WHERE contact_id = ? LIMIT 1');
    if (!$stmtOld) {
      file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " ERROR: Prepare failed (oldContact) - " . $crmConn->error . "\n", FILE_APPEND);
    }
    if ($stmtOld) {
      $stmtOld->bind_param('s', $contactId);
      $stmtOld->execute();
      $result = $stmtOld->get_result();
      if ($result) {
        $oldContact = $result->fetch_assoc();
        $result->free();
      }
      $stmtOld->close();
    }

    $updates = [];
    $params = [];
    $types = '';

    foreach ($fields as $field) {
      if (!isset($_POST[$field])) {
        continue;
      }
      $value = $_POST[$field];
      if ($field === 'is_customer') {
        $value = !empty($_POST[$field]) ? '1' : '0';
      }
      $updates[] = "`$field` = ?";
      $params[] = $value;
      $types .= 's';
    }

    if (!empty($updates) && $contactId !== '') {
      $sql = "UPDATE contacts SET " . implode(", ", $updates) . ", last_modified = NOW() WHERE contact_id = ?";
      $params[] = $contactId;
      $types .= 's';

      $stmt = $crmConn->prepare($sql);
      if (!$stmt) {
        file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " ERROR: Prepare failed (update) - " . $crmConn->error . "\n", FILE_APPEND);
        redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&error=update_prepare");
      }
      if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $saveSuccess = $stmt->execute();
        if (!$saveSuccess) {
          file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " ERROR: Execute failed (update) - " . $stmt->error . "\n", FILE_APPEND);
          redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&error=update_execute");
        }
        $stmt->close();

        // Log the audit trail after successful update
        if ($saveSuccess && $oldContact) {
          $newContact = null;
          $stmtNew = $crmConn->prepare('SELECT * FROM contacts WHERE contact_id = ? LIMIT 1');
          if (!$stmtNew) {
            file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " ERROR: Prepare failed (newContact) - " . $crmConn->error . "\n", FILE_APPEND);
          }
          if ($stmtNew) {
            $stmtNew->bind_param('s', $contactId);
            $stmtNew->execute();
            $resultNew = $stmtNew->get_result();
            if ($resultNew) {
              $newContact = $resultNew->fetch_assoc();
              $resultNew->free();
            }
            $stmtNew->close();
          }
          if ($newContact) {
            auditUpdateContact($contactId, $oldContact, $newContact);
          }
        }
      } else {
        $saveSuccess = false;
      }
    } else {
      $saveSuccess = false;
    }

    // Reload the page to show updated info and avoid resubmission
    if ($saveSuccess) {
      redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&updated=1#edit");
    }
  }
}



// Defensive: Always exit after POST to avoid using a possibly-invalid $crmConn
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $contactId = trim((string) ($_POST['contact_id'] ?? $_GET['id'] ?? ''));
  redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&error=unknown_post");
}

// NOW that all POST handlers are done, include layout_start to output HTML
require_once('layout_start.php');

// Force reconnect if $crmConn is closed before any DB usage (GET or POST)
if (!($crmConn instanceof mysqli) || !$crmConn->ping()) {
  file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " ERROR: Connection closed before any DB usage. Attempting reconnect.\n", FILE_APPEND);
  $crmConn = get_mysql_connection();
  if (!($crmConn instanceof mysqli) || !$crmConn->ping()) {
    echo '<div style="background:#fee;border:2px solid #c33;padding:10px;margin:10px 0;">';
    echo '<strong>Error:</strong> Database connection is closed or invalid (reconnect failed).</strong>';
    echo '</div>';
    file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " ERROR: Reconnect failed.\n", FILE_APPEND);
    exit;
  } else {
    file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " INFO: Reconnect succeeded.\n", FILE_APPEND);
  }
}

// Get contactId from GET or POST
$contactId = $_GET['id'] ?? $_POST['contact_id'] ?? null;
$contact = null;
if ($contactId) {
  $stmt = $crmConn->prepare('SELECT * FROM contacts WHERE contact_id = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('s', $contactId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && ($row = $result->fetch_assoc())) {
      $contact = $row;
    }
    if ($result) {
      $result->free();
    }
    $stmt->close();
  }
}

// Add Convert to Customer button
if ($contact && isset($contact['contact_id'])) {
  echo '<form method="GET" action="add_customer.php" style="margin:20px 0;">';
  echo '<input type="hidden" name="contact_id" value="' . htmlspecialchars($contact['contact_id']) . '">';
  echo '<button type="submit" class="btn btn-success">Convert to Customer</button>';
  echo '</form>';
}

// Safety: verify contact was loaded
if (!$contact) {
  // Try to fetch the record even if fields are missing
  $stmtRetry = $crmConn->prepare('SELECT * FROM contacts WHERE contact_id = ? LIMIT 1');
  $result = null;
  if ($stmtRetry) {
    $stmtRetry->bind_param('s', $contactId);
    $stmtRetry->execute();
    $result = $stmtRetry->get_result();
  }
  if ($result && ($row = mysqli_fetch_assoc($result))) {
    $contact = $row;
    ?>
    <div style="background:#fffbe6;border:2px solid #ffc;padding:10px;margin:10px 0;">
      <strong>Warning:</strong> This contact record is incomplete. Some fields may be missing or blank.
    </div>
    <?php
    mysqli_free_result($result);
    if ($stmtRetry) {
      $stmtRetry->close();
    }
  } else {
    if ($stmtRetry) {
      $stmtRetry->close();
    }
    // Diagnostic: Show all available contact IDs
    $result = mysqli_query($crmConn, "SELECT contact_id, first_name, last_name FROM contacts ORDER BY contact_id LIMIT 20");
    ?>
    <div style="background:#fee;border:2px solid #c33;padding:10px;margin:10px 0;">
      <strong>Error: Contact not found.</strong><br>
      Available Contact IDs:<br>
      <ul>
      <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <li>ID: <?= htmlspecialchars($row['contact_id']) ?> - <?= htmlspecialchars($row['first_name']) ?> <?= htmlspecialchars($row['last_name']) ?></li>
      <?php endwhile; ?>
      </ul>
      Try <code>contact_view.php?id=&lt;ID&gt;</code> with one of the above.
    </div>
    <?php
    if ($result) {
      mysqli_free_result($result);
    }
    exit;
  }
}

// Load opportunities for this contact
$opportunities = [];
$opportunitySchema = require 'opportunity_schema.php';
$opportunitySchema = is_array($opportunitySchema) && !empty($opportunitySchema)
  ? $opportunitySchema
  : ['opportunity_id', 'contact_id', 'value', 'stage', 'probability', 'expected_close'];
$fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $opportunitySchema));
$stmtOpp = $crmConn->prepare("SELECT $fields FROM opportunities WHERE contact_id = ?");
if ($stmtOpp) {
  $stmtOpp->bind_param('s', $contactId);
  $stmtOpp->execute();
  $result = $stmtOpp->get_result();
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $opportunities[] = $row;
    }
    mysqli_free_result($result);
  }
  $stmtOpp->close();
}

// Find orphaned opportunities (missing contact_id)
$orphanedOpportunities = [];
if (!empty($contact['company'])) {
  $result = mysqli_query($crmConn, "SELECT * FROM opportunities WHERE contact_id IS NULL OR contact_id = ''");
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $orphanedOpportunities[] = $row;
    }
    mysqli_free_result($result);
  }
}

// Handle admin action to link orphaned opportunity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_opportunity_id'], $_POST['contact_id'])) {
    $oppId = (int) $_POST['link_opportunity_id'];
    $contactId = trim((string) ($_POST['contact_id'] ?? ''));
    $stmtLink = $crmConn->prepare('UPDATE opportunities SET contact_id = ? WHERE opportunity_id = ?');
    if ($stmtLink) {
      $stmtLink->bind_param('si', $contactId, $oppId);
      $stmtLink->execute();
      $stmtLink->close();
    }
    redirect_safely("contact_view.php?id=" . urlencode($contactId) . "&linked=1");
}

// NOTE: If a discussion_log entry's contact_id does not exist in the contacts table,
// those discussions will not appear for any contact. Example missing IDs:
//   - 690507e9c30e8
//   - CNT_20251003023317_ecf31f
// To display these, add matching contacts or map them to existing contacts.
// Load discussions from PostgreSQL

// Load discussions for this contact by contact_id or manual_contact_id
$discussions = [];
$discussionSchema = require 'discussion_schema.php';
$desiredDiscussionFields = array_values(array_unique(array_merge(['id'], (array) $discussionSchema, ['manual_contact_id'])));
$availableDiscussionFields = [];
$columnsResult = mysqli_query($crmConn, "SHOW COLUMNS FROM discussion_log");
if ($columnsResult) {
  while ($column = mysqli_fetch_assoc($columnsResult)) {
    $name = $column['Field'] ?? '';
    if ($name !== '') {
      $availableDiscussionFields[] = $name;
    }
  }
  mysqli_free_result($columnsResult);
}

$selectDiscussionFields = array_values(array_filter($desiredDiscussionFields, function($field) use ($availableDiscussionFields) {
  return in_array($field, $availableDiscussionFields, true);
}));

if (empty($selectDiscussionFields)) {
  $selectDiscussionFields = ['contact_id', 'author', 'timestamp', 'entry_text', 'linked_opportunity_id', 'visibility'];
}

$fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $selectDiscussionFields));

// Build discussion query using prepared statement; include manual_contact_id column if available
if (in_array('manual_contact_id', $availableDiscussionFields, true)) {
  $discussionQuery = "SELECT $fields FROM discussion_log WHERE contact_id = ? OR manual_contact_id = ? ORDER BY timestamp DESC";
  $stmtDisc = $crmConn->prepare($discussionQuery);
  if ($stmtDisc) {
    $stmtDisc->bind_param('ss', $contactId, $contactId);
    $stmtDisc->execute();
    $result = $stmtDisc->get_result();
  } else {
    $result = null;
  }
} else {
  $discussionQuery = "SELECT $fields FROM discussion_log WHERE contact_id = ? ORDER BY timestamp DESC";
  $stmtDisc = $crmConn->prepare($discussionQuery);
  if ($stmtDisc) {
    $stmtDisc->bind_param('s', $contactId);
    $stmtDisc->execute();
    $result = $stmtDisc->get_result();
  } else {
    $result = null;
  }
}

if ($result) {
  $seen = [];
  while ($row = mysqli_fetch_assoc($result)) {
    // Avoid duplicates if both contact_id and manual_contact_id match
    $uniqueKey = $row['id'] ?? md5(json_encode($row));
    if (!isset($seen[$uniqueKey])) {
      $discussions[] = $row;
      $seen[$uniqueKey] = true;
    }
  }
  $result->free();
}
if (isset($stmtDisc) && $stmtDisc) {
  $stmtDisc->close();
}
if (!is_array($discussions)) $discussions = [];

$contactDiscussions = $discussions;

// Ensure $contactOpportunities is defined for forms and dropdowns

$contactOpportunities = getContactOpportunities($contact, $opportunities);
// Calculate total opportunity value for this contact
$opportunityValue = 0;
if (is_array($contactOpportunities)) {
  foreach ($contactOpportunities as $opp) {
    $opportunityValue += (float)($opp['value'] ?? 0);
  }
}

// Helper: resolve customer by ID
function findCustomerById($customers, $cid) {
    foreach ($customers as $c) {
        if ($c['customer_id'] === $cid) return $c;
    }
    return null;
}

// Helper: Get initials for avatar
function getInitials($firstName, $lastName) {
    $f = strtoupper(substr($firstName ?? '', 0, 1));
    $l = strtoupper(substr($lastName ?? '', 0, 1));
    return $f . $l;
}

// Helper: Parse tags from CSV
function parseTags($tagString) {
    if (empty($tagString)) return [];
    return array_filter(array_map('trim', explode(',', $tagString)));
}

// Helper: Get contact's opportunities
function getContactOpportunities($contact, $opportunities) {
    if (!is_array($opportunities)) return [];
    return array_filter($opportunities, function($opp) use ($contact) {
      return ($opp['contact_id'] ?? '') === ($contact['contact_id'] ?? '');
    });
}

// â”€â”€ Computed display variables â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$initials     = getInitials($contact['first_name'] ?? '', $contact['last_name'] ?? '');
$status       = $contact['status'] ?? 'Unknown';
$isCustomer   = !empty($contact['is_customer']) && $contact['is_customer'] !== '0';
$tags         = parseTags($contact['tags'] ?? '');
$createdAt    = $contact['created_at'] ?? $contact['date_added'] ?? date('Y-m-d');
$lastModified = $contact['last_modified'] ?? null;

// Avatar colour derived from contact ID (deterministic)
$avatarColors = ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4','#84CC16'];
$avatarColor  = $avatarColors[abs(crc32((string)($contact['contact_id'] ?? ''))) % count($avatarColors)];

// Status badge colour
if (strtolower($status) === 'active')        { $statusColor = '#10B981'; }
elseif (strtolower($status) === 'inactive')  { $statusColor = '#9CA3AF'; }
elseif (strtolower($status) === 'prospect')  { $statusColor = '#F59E0B'; }
else                                          { $statusColor = '#6B7280'; }

?>

<style>
  * { box-sizing: border-box; }
  
  /* Override content-container padding for this page */
  .content-container { padding: 0 !important; }
  
  .contact-header { max-width: 100% !important; margin: 0 !important; padding: 0 32px !important; width: 100% !important; }
  .contact-banner { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 18px; border-radius: 8px; display: flex; align-items: start; gap: 14px; margin-bottom: 16px; flex-wrap: wrap; }
  .contact-avatar { width: 50px; height: 50px; border-radius: 50%; background: <?= $avatarColor ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; flex-shrink: 0; border: 2px solid white; }
  .contact-header-info { flex: 1; min-width: 180px; }
  .contact-header-info h1 { margin: 0 0 8px 0; font-size: 20px; font-weight: 600; }
  .contact-header-info p { margin: 4px 0; font-size: 11px; opacity: 0.9; }
  .contact-header-info a { color: white; text-decoration: none; }
  .contact-status { display: inline-block; padding: 4px 10px; background: rgba(255,255,255,0.2); border-radius: 20px; font-size: 11px; font-weight: 600; margin-top: 8px; }
  .quick-actions { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
  .quick-actions button { background: white; color: #333; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; transition: background 0.2s; }
  .quick-actions button:hover { background: #f0f0f0; }
  .quick-actions button:active { transform: scale(0.98); }
  .stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
  .stat-card { background: white; padding: 8px; border-radius: 6px; border-left: 4px solid #3B82F6; font-size: 11px; }
  .stat-label { color: #999; text-transform: uppercase; font-weight: 600; font-size: 10px; }
  .stat-value { font-size: 15px; font-weight: 600; color: #1a1a1a; margin-top: 4px; }
  
  /* Accordion Styles */
  .accordion { margin-bottom: 16px; }
  .accordion-header { background: white; padding: 18px 24px; border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border: 2px solid #e5e7eb; transition: all 0.3s; user-select: none; }
  .accordion-header:hover { border-color: #3B82F6; background: #f9fafb; }
  .accordion-header.active { border-color: #3B82F6; background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%); }
  .accordion-title { font-size: 16px; font-weight: 700; color: #1f2937; display: flex; align-items: center; gap: 12px; }
  .accordion-icon { font-size: 18px; color: #6b7280; transition: transform 0.3s; }
  .accordion-header.active .accordion-icon { transform: rotate(90deg); color: #3B82F6; }
  .accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out; }
  .accordion-content.active { max-height: 5000px; transition: max-height 0.6s ease-in; }
  .accordion-body { background: white; padding: 24px; border: 2px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; margin-top: -8px; }
  .section { margin-bottom: 25px; }
  .section:last-child { margin-bottom: 0; }
  .section-title { font-size: 12px; font-weight: 700; color: #1a1a1a; text-transform: uppercase; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0; }
  .section-subtext { font-size: 12px; color: #666; margin-bottom: 10px; }
  .empty-state { padding: 16px; background: #f8f9fa; border-radius: 6px; color: #6b7280; text-align: center; font-size: 13px; }
  .info-note { padding: 10px; background: #f0f7ff; border-radius: 4px; margin-top: 12px; font-size: 12px; color: #666; }
  .tip-text { font-size: 12px; color: #6b7280; margin-top: 8px; }
  .timeline-entry {
    width: 100%;
    padding: 15px;
    margin-bottom: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #3B82F6;
  }
  .timeline-head { display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px; }
  .timeline-author { color: #1a1a1a; font-size: 14px; font-weight: 700; }
  .timeline-meta { color: #666; font-size: 11px; margin-bottom: 8px; }
  .timeline-body { color: #1a1a1a; font-size: 13px; line-height: 1.5; margin-bottom: 6px; }
  .linked-note { margin-top: 8px; padding: 8px; background: white; border-left: 3px solid #10B981; border-radius: 3px; font-size: 11px; color: #666; }
  .field-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .field { }
  .field-label { font-size: 11px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 3px; }
  .field-value { font-size: 13px; color: #1a1a1a; word-break: break-word; line-height: 1.4; }
  .field-value.empty { color: #ccc; font-style: italic; }
  .opportunity { background: #f8f9fa; padding: 12px; border-radius: 4px; margin-bottom: 10px; border-left: 3px solid #10B981; }
  .opportunity-title { font-weight: 600; color: #1a1a1a; font-size: 13px; }
  .opportunity-details { font-size: 12px; color: #666; margin-top: 4px; }
  .opportunity-value { font-weight: 600; color: #28a745; font-size: 14px; margin-top: 6px; }
  .timeline-item { padding: 12px 0; border-bottom: 1px solid #e0e0e0; display: flex; gap: 12px; }
  .timeline-item:last-child { border-bottom: none; }
  .timeline-icon { font-size: 16px; flex-shrink: 0; width: 20px; text-align: center; }
  .timeline-content { flex: 1; }
  .timeline-title { font-weight: 600; color: #1a1a1a; font-size: 13px; }
  .timeline-time { font-size: 11px; color: #999; margin-top: 2px; }
  .tags-container { display: flex; flex-wrap: wrap; gap: 6px; }
  .tag { background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
  .tag-new { background: #f0f0f0; color: #666; border: 1px dashed #999; padding: 4px 8px; border-radius: 12px; font-size: 11px; cursor: pointer; }
  .tag-remove { cursor: pointer; }
  .form-section { width: 100%; margin-bottom: 28px; background: #f9fafb; padding: 28px; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .form-section h3 { font-size: 15px; font-weight: 700; margin: -28px -28px 20px -28px; padding: 16px 28px; background: linear-gradient(135deg, #f3f4f6 0%, #ffffff 100%); border-bottom: 2px solid #e5e7eb; border-radius: 8px 8px 0 0; color: #1f2937; }
  .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; width: 100%; }
  .form-group { display: flex; flex-direction: column; }
  .form-group label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 12px; color: #111827; text-transform: uppercase; letter-spacing: 0.6px; }
  .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 18px 16px; border: 1.5px solid #d1d5db; border-radius: 8px; font-family: inherit; font-size: 15px; transition: all 0.2s; background: white; line-height: 1.6; min-height: 60px; box-sizing: border-box; text-align: left; }
  .form-group input::placeholder, .form-group textarea::placeholder { color: #9ca3af; }
  .form-group textarea { resize: none; min-height: 150px; padding: 20px; overflow-y: auto; }
  .form-group input:hover, .form-group textarea:hover, .form-group select:hover { border-color: #9ca3af; }
  .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #3B82F6; background: #eff6ff; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
  .form-group input:disabled, .form-group textarea:disabled, .form-group select:disabled { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }
  .activity-item { padding: 10px 0; border-bottom: 1px solid #e0e0e0; font-size: 12px; }
  .activity-item:last-child { border-bottom: none; }
  .activity-time { color: #999; font-size: 10px; }
  .activity-text { color: #333; margin-top: 3px; }
  .submit-actions { display: flex; gap: 12px; margin-top: 28px; flex-wrap: wrap; }
  .submit-actions button, .submit-actions a { padding: 14px 32px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-block; transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
  .btn-primary { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: white; }
  .btn-primary:hover { background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); transform: translateY(-2px); }
  .btn-primary:active { transform: translateY(0); }
  .btn-secondary { background: #f3f4f6; color: #374151; border: 1.5px solid #d1d5db; }
  .btn-secondary:hover { background: #e5e7eb; }
  .btn-secondary:active { background: #d1d5db; }
  .success-alert { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
  .success-alert a { color: #155724; font-weight: 700; }
  .section-jump {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 14px 0;
  }
  .section-jump a {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 999px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    color: #1d4ed8;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
  }
  .section-jump a:hover { background: #dbeafe; }

  /* Mobile Responsiveness */
  @media (max-width: 900px) {
    .contact-banner { flex-direction: column; }
    .contact-avatar { width: 60px; height: 60px; font-size: 20px; }
    .contact-banner h1 { font-size: 22px; }
    .field-grid { grid-template-columns: 1fr; }
    .form-grid { grid-template-columns: repeat(2, 1fr); }
    .stats-bar { grid-template-columns: repeat(2, 1fr); }
    .quick-actions { gap: 6px; }
    .quick-actions button { padding: 5px 10px; font-size: 11px; }
    .accordion-header { padding: 14px 18px; }
    .accordion-title { font-size: 14px; }
  }

  @media (max-width: 600px) {
    .contact-header { padding: 0 16px !important; }
    .contact-banner { padding: 15px; gap: 12px; }
    .contact-avatar { width: 50px; height: 50px; font-size: 18px; }
    .contact-banner h1 { font-size: 18px; }
    .stats-bar { grid-template-columns: 1fr; gap: 8px; }
    .stat-card { padding: 10px; }
    .form-grid { grid-template-columns: 1fr; }
    .accordion-header { padding: 12px 16px; }
    .accordion-title { font-size: 13px; }
    .accordion-body { padding: 16px; }
    .form-section { padding: 20px; }
    .submit-actions { gap: 6px; }
    .submit-actions button, .submit-actions a { padding: 8px 12px; font-size: 12px; }
  }
  /* Auto-expand textareas */
  .auto-expand-textarea {
    overflow: hidden;
    resize: none;
  }

  /* AI Panel */
  .ai-panel { background: white; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin: 0 0 16px 0; display: none; }
  .ai-panel.visible { display: block; }
  .ai-panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .ai-panel-title { font-size: 13px; font-weight: 700; color: #1d4ed8; }
  .ai-panel-close { background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 18px; padding: 0; line-height: 1; }
  .ai-panel-body { font-size: 13px; color: #1f2937; line-height: 1.7; white-space: pre-wrap; background: #f8faff; border-radius: 6px; padding: 12px; }
  .ai-panel-meta { font-size: 11px; color: #9ca3af; margin-top: 6px; }
  .ai-panel-actions { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
  .ai-copy-btn { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; border-radius: 4px; padding: 5px 12px; font-size: 11px; cursor: pointer; font-weight: 600; }
  .ai-use-btn  { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; border-radius: 4px; padding: 5px 12px; font-size: 11px; cursor: pointer; font-weight: 600; }
  .ai-btn { background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; transition: opacity 0.2s; }
  .ai-btn:hover { opacity: 0.85; }
  .ai-btn:disabled { opacity: 0.5; cursor: default; }
  .ai-spinner { display: inline-block; width: 11px; height: 11px; border: 2px solid rgba(255,255,255,0.35); border-top-color: white; border-radius: 50%; animation: ai-spin 0.7s linear infinite; margin-right: 4px; vertical-align: middle; }
  @keyframes ai-spin { to { transform: rotate(360deg); } }
</style>

<div class="contact-header">
  <div style="margin-bottom: 15px; font-size: 13px;">
    <a href="contacts_list.php" style="color: #3B82F6; text-decoration: none; font-weight: 600;">&larr; Back to Contacts</a>
  </div>

  <!-- Optimized Contact Banner -->
  <div class="contact-banner">
    <div class="contact-avatar"><?= $initials ?></div>
    <div class="contact-header-info">
      <h1><?= htmlspecialchars(trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))) ?: 'Unknown Contact' ?></h1>
      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <span style="font-size:13px;color:#fff;opacity:0.9;">
          <?= htmlspecialchars($contact['company'] ?? 'No Company') ?>
        </span>
        <span class="contact-status" style="background-color: <?= $statusColor ?>;margin-left:0;">&#10003; <?= $status ?></span>
        <?php if ($isCustomer): ?>
          <span style="background:#10B981;color:#fff;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:600;">Customer</span>
        <?php endif; ?>
      </div>
      <div style="margin:8px 0 0 0;display:flex;gap:18px;flex-wrap:wrap;align-items:center;">
        <?php if ($contact['phone']): ?>
          <span>&#9742; <a href="tel:<?= htmlspecialchars($contact['phone']) ?>" style="color:#fff;"><?= htmlspecialchars($contact['phone']) ?></a></span>
        <?php endif; ?>
        <?php if ($contact['email']): ?>
          <span>&#9993; <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" style="color:#fff;"><?= htmlspecialchars($contact['email']) ?></a></span>
        <?php endif; ?>
        <?php if (!empty($tags)): ?>
          <span style="color:#fff;opacity:0.8;">&#128278; <?= htmlspecialchars(implode(', ', $tags)) ?></span>
        <?php endif; ?>
      </div>
      <div class="quick-actions" style="margin-top:10px;">
        <a href="mailto:<?= htmlspecialchars($contact['email'])?>"><button <?= empty($contact['email']) ? 'disabled' : '' ?>>&#9993; Email</button></a>
        <a href="tel:<?= htmlspecialchars($contact['phone'])?>"><button <?= empty($contact['phone']) ? 'disabled' : '' ?>>&#9742; Call</button></a>
        <button onclick="window.location.href='add_task.php?contact_id=<?= urlencode($contact['contact_id']) ?>'">+ Task</button>
        <button onclick="window.location.href='add_opportunity.php?contact_id=<?= urlencode($contact['contact_id']) ?>'">&#128188; Opp</button>
        <button class="ai-btn" onclick="aiAction('summarise_contact', this, 'Summary')">&#129302; Summary</button>
        <button class="ai-btn" onclick="aiAction('suggest_followup', this, 'Follow-up Draft')">&#9993; Follow-up</button>
        <button class="ai-btn" onclick="enrichContactInfo()" id="enrichBtn">&#128269; Fill Info</button>
      </div>
    </div>
  </div>

  <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
    <div class="success-alert">
      &#10003; Contact saved successfully.
      <span style="margin-left:10px;">Continue in <a href="#edit">Edit Details</a>.</span>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['log_added']) && $_GET['log_added'] === '1'): ?>
    <div class="success-alert">&#10003; Log entry added successfully.</div>
  <?php endif; ?>

  <?php if ($pageError !== ''): ?>
    <div style="background:#fee;border:1px solid #f5c2c7;color:#842029;padding:12px;border-radius:6px;margin-bottom:15px;">
      <strong>Error:</strong> <?= htmlspecialchars($pageError) ?>
    </div>
  <?php endif; ?>

  <div class="section-jump" aria-label="Quick section navigation">
    <a href="#edit">Edit Details</a>
    <a href="#opportunities">Opportunities</a>
    <a href="#discussions">Discussions</a>
  </div>


  <!-- Stats Bar -->
  <div class="stats-bar">
    <div class="stat-card">
      <div class="stat-label">Status</div>
      <div class="stat-value"><?= !empty(trim((string)($contact['status'] ?? ''))) ? htmlspecialchars($contact['status']) : '&mdash;' ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Created</div>
      <div class="stat-value"><?= substr($createdAt, 0, 10) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Opportunities</div>
      <div class="stat-value"><?= is_array($contactOpportunities) ? count($contactOpportunities) : 0 ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Value</div>
      <div class="stat-value"><?= $opportunityValue > 0 ? formatCurrency($opportunityValue) : '&mdash;' ?></div>
    </div>
  </div>

  <!-- AI Panel -->
  <div class="ai-panel" id="aiPanel">
    <div class="ai-panel-header">
      <div class="ai-panel-title" id="aiPanelTitle">&#129302; AI</div>
      <button class="ai-panel-close" onclick="closeAiPanel()" title="Close">&#10005;</button>
    </div>
    <div class="ai-panel-body" id="aiPanelBody"></div>
    <div class="ai-panel-meta" id="aiPanelMeta"></div>
    <div class="ai-panel-actions">
      <button class="ai-copy-btn" onclick="copyAiResult()">&#128203; Copy</button>
      <button class="ai-use-btn" id="aiUseBtn" onclick="useAiAsDiscussion()" style="display:none;">&#128221; Paste into discussion</button>
    </div>
  </div>

  <!-- ACCORDION SECTIONS -->
  
  <!-- Edit Details Section -->
  <div class="accordion" id="edit">
    <div class="accordion-header" onclick="toggleAccordion(this)">
      <div class="accordion-title">
        <span>&#9998;</span>
        <span>Edit Contact Details</span>
      </div>
      <div class="accordion-icon">&#9654;</div>
    </div>
    <div class="accordion-content">
      <div class="accordion-body">
        <form method="post">
          <?php renderCSRFInput(); ?>
          <input type="hidden" name="contact_id" value="<?= htmlspecialchars($contact['contact_id']) ?>">

          <!-- Optimized Contact Info Section -->
          <div class="form-section">
            <h3>Contact Information</h3>
            <div class="form-grid">
              <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($contact['first_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?= htmlspecialchars($contact['last_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($contact['email'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($contact['phone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Company</label>
                <?php
                $companyOptions = [];
                $companyResult = mysqli_query($crmConn, "SELECT DISTINCT company FROM contacts WHERE company IS NOT NULL AND company != '' ORDER BY LOWER(company)");
                if ($companyResult) {
                  while ($row = mysqli_fetch_assoc($companyResult)) {
                    $companyOptions[] = $row['company'];
                  }
                  mysqli_free_result($companyResult);
                }
                ?>
                <select name="company">
                  <option value="">-- Select Company --</option>
                  <?php foreach ($companyOptions as $company): ?>
                    <option value="<?= htmlspecialchars($company) ?>" <?= (isset($contact['company']) && $contact['company'] === $company) ? 'selected' : '' ?>><?= htmlspecialchars($company) ?></option>
                  <?php endforeach; ?>
                  <?php if (!empty($contact['company']) && !in_array($contact['company'], $companyOptions)): ?>
                    <option value="<?= htmlspecialchars($contact['company']) ?>" selected><?= htmlspecialchars($contact['company']) ?> (not in list)</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Status</label>
                <select name="status" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                  <option value=""></option>
                  <option value="Active" <?= ($contact['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                  <option value="Inactive" <?= ($contact['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                  <option value="Prospect" <?= ($contact['status'] ?? '') === 'Prospect' ? 'selected' : '' ?>>Prospect</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Address Section -->
          <div class="form-section">
            <h3>Address</h3>
            <div class="form-grid">
              <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($contact['address'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>City</label>
                <input type="text" name="city" value="<?= htmlspecialchars($contact['city'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Province/State</label>
                <input type="text" name="province" value="<?= htmlspecialchars($contact['province'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Postal Code</label>
                <input type="text" name="postal_code" value="<?= htmlspecialchars($contact['postal_code'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Country</label>
                <input type="text" name="country" value="<?= htmlspecialchars($contact['country'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- Notes Section -->
          <div class="form-section">
            <h3>Notes</h3>
            <div class="form-group">
              <label>Additional Notes</label>
              <textarea name="notes" rows="5"><?= htmlspecialchars($contact['notes'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="submit-actions">
            <button type="submit" class="btn-primary">&#128190; Save Changes</button>
            <a href="contacts_list.php" class="btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Opportunities Section -->
  <div class="accordion" id="opportunities">
    <div class="accordion-header" onclick="toggleAccordion(this)">
      <div class="accordion-title">
        <span>&#128188;</span>
        <span>Opportunities (<?= is_array($contactOpportunities) ? count($contactOpportunities) : 0 ?>)</span>
      </div>
      <div class="accordion-icon">&#9654;</div>
    </div>
    <div class="accordion-content">
      <div class="accordion-body">
        <div class="section">
          <div class="section-title">&#128188; Linked Opportunities</div>
          <?php if (!empty($contactOpportunities)): ?>
            <?php foreach ($contactOpportunities as $opp): ?>
              <?php $oppId = $opp['opportunity_id'] ?? $opp['id'] ?? ''; ?>
              <div class="opportunity">
                <div class="opportunity-title">
                  <?= htmlspecialchars($opp['name'] ?? 'Opportunity #' . $oppId) ?>
                  <span style="color:#888;font-size:12px;">#<?= htmlspecialchars($oppId) ?></span>
                </div>
                <div class="opportunity-details">
                  <strong>Stage:</strong> <?= !empty(trim((string)($opp['stage'] ?? ''))) ? htmlspecialchars($opp['stage']) : '&mdash;' ?> 
                  (<?= htmlspecialchars($opp['probability'] ?? '0') ?>%)
                </div>
                <div class="opportunity-details" style="margin-top: 4px;">
                  <strong>Expected Close:</strong> <?= !empty(trim((string)($opp['expected_close'] ?? ''))) ? htmlspecialchars($opp['expected_close']) : '&mdash;' ?>
                </div>
                <div class="opportunity-value">
                  <?= formatCurrency($opp['value'] ?? 0) ?>
                </div>
              </div>
            <?php endforeach; ?>
            <div class="info-note">
              <strong style="color: #1976d2;">Total Opportunity Value:</strong> <?= formatCurrency($opportunityValue) ?>
            </div>
          <?php else: ?>
            <div class="empty-state">
              No opportunities linked to this contact yet.
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($orphanedOpportunities)): ?>
        <div class="section" style="margin-top:30px;">
          <div class="section-title" style="color:#d32f2f;">Orphaned Opportunities (Admin Only)</div>
          <p class="section-subtext">The following opportunities match this company but are not linked to any contact. You can link them below:</p>
          <?php foreach ($orphanedOpportunities as $opp): ?>
            <?php $oppId = $opp['opportunity_id'] ?? $opp['id'] ?? ''; ?>
            <form method="post" style="margin-bottom:10px;display:inline-block;">
              <input type="hidden" name="link_opportunity_id" value="<?= htmlspecialchars($oppId) ?>">
              <input type="hidden" name="contact_id" value="<?= htmlspecialchars($contact['contact_id']) ?>">
              <span style="font-weight:600;">
                <?= htmlspecialchars($opp['name'] ?? 'Opportunity #' . $oppId) ?>
                <span style="color:#888;font-size:12px;">#<?= htmlspecialchars($oppId) ?></span>
              </span> - <?= htmlspecialchars($opp['stage']) ?> ($<?= htmlspecialchars($opp['value']) ?>)
              <button type="submit" class="btn-primary" style="margin-left:10px;">Link to this Contact</button>
            </form>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <!-- Discussions Section -->
  <div class="accordion" id="discussions">
    <div class="accordion-header" onclick="toggleAccordion(this)">
      <div class="accordion-title">
        <span>&#128172;</span>
        <span>Discussions (<?= is_array($contactDiscussions) ? count($contactDiscussions) : 0 ?>)</span>
      </div>
      <div class="accordion-icon">&#9654;</div>
    </div>
    <div class="accordion-content">
      <div class="accordion-body">
        <div class="section">
          <div class="section-title">&#10133; Add Discussion</div>
          <div class="form-section" style="margin-bottom:0;">
            <h3>Add Communication / Discussion Log</h3>
            <form method="post" action="">
              <?php renderCSRFInput(); ?>
              <input type="hidden" name="form_action" value="add_discussion">
              <input type="hidden" name="contact_id" value="<?= htmlspecialchars($contact['contact_id']) ?>">
              <div class="form-group">
                <label for="entry_text">Notes / Communication</label>
                <textarea id="entry_text" name="entry_text" class="form-control" rows="4" required placeholder="Enter details of your call, meeting, email, or note..."></textarea>
              </div>
              <div class="form-group">
                <label for="linked_opportunity_id">Linked Opportunity (Optional)</label>
                <select id="linked_opportunity_id" name="linked_opportunity_id" class="form-control">
                  <option value="">-- None --</option>
                  <?php foreach ($contactOpportunities as $opp): ?>
                    <option value="<?= htmlspecialchars($opp['opportunity_id']) ?>">
                      <?= htmlspecialchars($opp['name'] ?? $opp['opportunity_id']) ?> (<?= htmlspecialchars($opp['stage'] ?? '') ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="author">Your Name</label>
                <input type="text" id="author" name="author" class="form-control" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required>
              </div>
              <div class="submit-actions">
                <button type="submit" name="add_discussion" class="btn-primary">Add Log Entry</button>
              </div>
            </form>
            <div class="tip-text">
              <strong>Tip:</strong> Log communications before, during, or after an opportunity. Link to an opportunity if relevant.
            </div>
          </div>
        </div>

        <!-- Discussion History -->
        <div class="section">
          <div class="section-title">&#128210; Activity & History</div>
          <div class="section-subtext">
            Linked Tasks, Opportunities, and Discussions for this Contact
          </div>
          <?php if (!empty($contactDiscussions)): ?>
            <?php foreach ($contactDiscussions as $disc): ?>
              <div class="timeline-entry">
                <div>
                  <div class="timeline-head">
                    <strong class="timeline-author"><?= htmlspecialchars($disc['author'] ?? 'Unknown') ?></strong>
                    <span style="background: <?= ($disc['visibility'] ?? 'public') === 'public' ? '#e3f2fd' : (($disc['visibility'] ?? '') === 'internal' ? '#fff3cd' : '#f8d7da') ?>; color: <?= ($disc['visibility'] ?? 'public') === 'public' ? '#1976d2' : (($disc['visibility'] ?? '') === 'internal' ? '#856404' : '#721c24') ?>; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase;">
                      <?= htmlspecialchars($disc['visibility'] ?? 'public') ?>
                    </span>
                  </div>
                  <div class="timeline-meta">
                    &#128197; <?= !empty(trim((string)($disc['timestamp'] ?? ''))) ? htmlspecialchars($disc['timestamp']) : '&mdash;' ?>
                    <?php if (!empty($disc['manual_contact_id'])): ?>
                      <span style="margin-left:10px; color:#8B5CF6; font-weight:600; font-size:11px;">&#128279; Linked by manual_contact_id: <?= htmlspecialchars($disc['manual_contact_id']) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="timeline-body">
                    <?= nl2br(htmlspecialchars($disc['entry_text'] ?? '')) ?>
                  </div>
                  <?php if (!empty($disc['linked_opportunity_id'])): ?>
                    <?php
                      $linkedOpp = null;
                      foreach ($contactOpportunities as $opp) {
                        if (($opp['opportunity_id'] ?? '') == $disc['linked_opportunity_id']) {
                          $linkedOpp = $opp;
                          break;
                        }
                      }
                    ?>
                    <div class="linked-note">
                      <strong>&#128206; Linked to Opportunity:
                        <?= htmlspecialchars($linkedOpp['name'] ?? ('#' . $disc['linked_opportunity_id'])) ?>
                        <span style="color:#888;font-size:11px;">#<?= htmlspecialchars($disc['linked_opportunity_id']) ?></span>
                        <?php if (!empty($linkedOpp['value'])): ?>
                          &mdash; <?= formatCurrency($linkedOpp['value']) ?>
                        <?php endif; ?>
                      </strong>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state">
              No discussions logged yet. Use the Add Discussion section above to create the first entry.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
</div>

<script>
  // Accordion toggle function
  function toggleAccordion(header) {
    const content = header.nextElementSibling;
    
    // Toggle current accordion
    header.classList.toggle('active');
    content.classList.toggle('active');
  }

  // Tag management
  function addNewTag(element) {
    const tagName = prompt('Enter tag name:');
    if (tagName && tagName.trim()) {
      const tagsInput = document.getElementById('tags_input');
      const currentTags = tagsInput.value ? tagsInput.value.split(',').map(t => t.trim()) : [];
      currentTags.push(tagName.trim());
      tagsInput.value = currentTags.join(',');
      location.reload();
    }
  }

  function removeTag(element) {
    const tagsInput = document.getElementById('tags_input');
    const tagName = element.previousSibling.textContent.trim();
    const currentTags = tagsInput.value.split(',').map(t => t.trim());
    const filtered = currentTags.filter(t => t !== tagName);
    tagsInput.value = filtered.join(',');
    location.reload();
  }

  // Auto-expand textareas based on content
  const textareas = document.querySelectorAll('.form-group textarea');
  textareas.forEach(textarea => {
    function adjustHeight() {
      textarea.style.height = 'auto';
      textarea.style.height = Math.max(150, textarea.scrollHeight) + 'px';
    }
    
    textarea.addEventListener('input', adjustHeight);
    textarea.addEventListener('change', adjustHeight);
    adjustHeight(); // Initial adjustment
  });

  // Auto-adjust input widths based on content
  const inputs = document.querySelectorAll('.form-group input[type="text"], .form-group input[type="email"], .form-group input[type="tel"], .form-group input[type="number"]');
  inputs.forEach(input => {
    function adjustWidth() {
      const length = input.value.length;
      const charWidth = 9;
      const minWidth = 280;
      input.style.minWidth = Math.max(minWidth, length * charWidth + 60) + 'px';
    }
    
    input.addEventListener('input', adjustWidth);
    input.addEventListener('change', adjustWidth);
    adjustWidth(); // Initial adjustment
  });

  // AI integration
  const AI_CONTACT_ID  = <?= json_encode($contact['contact_id'] ?? '') ?>;
  const AI_CSRF_TOKEN  = <?= json_encode(getCSRFToken()) ?>;

  const AI_BTN_LABELS = {
    'summarise_contact': '&#129302; Summary',
    'suggest_followup':  '&#9993; Follow-up',
  };

  function aiAction(action, btn, label, showUse) {
    const panel = document.getElementById('aiPanel');
    const body  = document.getElementById('aiPanelBody');
    const meta  = document.getElementById('aiPanelMeta');
    const title = document.getElementById('aiPanelTitle');
    const useBtn = document.getElementById('aiUseBtn');

    title.textContent = 'AI: ' + label;
    body.textContent  = 'Thinking...';
    meta.textContent  = '';
    if (useBtn) useBtn.style.display = 'none';
    panel.classList.add('visible');
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    const origLabel = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ai-spinner"></span> Thinking...'; }

    const fd = new FormData();
    fd.append('action',     action);
    fd.append('contact_id', AI_CONTACT_ID);
    fd.append('csrf_token', AI_CSRF_TOKEN);

    fetch('ai_endpoint.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.error) {
          body.textContent = 'Warning: ' + data.error;
        } else {
          body.textContent = data.text || '(no response)';
          if (data.provider && data.model) {
            var selectionLabel = data.selection_mode === 'cheapest' ? ' - chosen by cost' : ' - manual selection';
            meta.textContent = 'via ' + data.provider + ' / ' + data.model + selectionLabel;
          }
          if (showUse && useBtn) useBtn.style.display = 'inline-block';
        }
      })
      .catch(err => {
        body.textContent = 'Network error: ' + err.message;
      })
      .finally(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = origLabel; }
      });
  }

  function closeAiPanel() {
    document.getElementById('aiPanel').classList.remove('visible');
  }

  function copyAiResult() {
    const text = document.getElementById('aiPanelBody').textContent;
    navigator.clipboard.writeText(text).then(() => {
      const btn = document.querySelector('.ai-copy-btn');
      const orig = btn.textContent;
      btn.textContent = 'Copied!';
      setTimeout(() => { btn.textContent = orig; }, 2000);
    });
  }

  function useAiAsDiscussion() {
    const text = document.getElementById('aiPanelBody').textContent;
    const ta   = document.querySelector('textarea[name="entry_text"]');
    if (ta) {
      ta.value = text;
      ta.scrollIntoView({ behavior: 'smooth', block: 'center' });
      ta.focus();
      closeAiPanel();
    }
  }

  // Contact enrichment integration
  const ENRICH_CSRF_TOKEN = <?= json_encode(getCSRFToken()) ?>;
  const ENRICH_CONTACT_ID = <?= json_encode($contact['contact_id'] ?? '') ?>;

  function enrichContactInfo() {
    const btn = document.getElementById('enrichBtn');
    const origLabel = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="ai-spinner"></span> Searching...';

    const fd = new FormData();
    fd.append('contact_id', ENRICH_CONTACT_ID);
    fd.append('csrf_token', ENRICH_CSRF_TOKEN);

    fetch('contact_enrich.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = origLabel;
        
        if (!data.success || (Object.keys(data.found_data || {}).length === 0 && data.errors.length === 0)) {
          showEnrichmentModal(
            data.message || 'No additional information found',
            {},
            data.errors || []
          );
          return;
        }
        
        showEnrichmentModal(
          'Found missing information - review and approve below:',
          data.found_data || {},
          data.errors || [],
          data.matches || {}
        );
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origLabel;
        showEnrichmentModal('Error: ' + err.message, {}, ['Network error']);
      });
  }

  function showEnrichmentModal(message, foundData, errors, matches) {
    const modal = document.createElement('div');
    modal.id = 'enrichModal';
    modal.style.cssText = `
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
    `;

    const content = document.createElement('div');
    content.style.cssText = `
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
      padding: 32px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    `;

    // Header
    const header = document.createElement('div');
    header.style.cssText = 'margin-bottom: 20px;';
    header.innerHTML = `
      <h2 style="margin: 0 0 8px 0; font-size: 20px; color: #1f2937;">
        &#128269; Fill Missing Information
      </h2>
      <p style="margin: 0; color: #666; font-size: 14px;">
        ${escapeHtml(message)}
      </p>
    `;
    content.appendChild(header);

    // Errors
    if (errors.length > 0) {
      const errorBox = document.createElement('div');
      errorBox.style.cssText = `
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 20px;
      `;
      errorBox.innerHTML = '<strong style="color: #991b1b;">Validation Issues:</strong><ul style="margin: 8px 0 0 20px; font-size: 13px; color: #7f1d1d;">' + 
        errors.map(e => '<li>' + escapeHtml(e) + '</li>').join('') + 
        '</ul>';
      content.appendChild(errorBox);
    }

    // Found Data
    if (Object.keys(foundData).length > 0) {
      const dataBox = document.createElement('div');
      dataBox.style.cssText = 'margin-bottom: 20px;';
      dataBox.innerHTML = '<strong style="font-size: 14px; color: #1f2937;">Proposed Changes:</strong>';
      
      const fieldsTable = document.createElement('table');
      fieldsTable.style.cssText = `
        width: 100%;
        margin-top: 12px;
        border-collapse: collapse;
      `;
      
      for (const [field, value] of Object.entries(foundData)) {
        const confidence = matches[field] || 0.7;
        const row = document.createElement('tr');
        row.style.cssText = 'border-bottom: 1px solid #e5e7eb;';
        row.innerHTML = `
          <td style="padding: 10px 0; width: 120px; font-weight: 600; color: #374151; font-size: 13px;">
            ${escapeHtml(field)}
          </td>
          <td style="padding: 10px 12px; font-size: 13px; color: #1f2937;">
            <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">
              ${escapeHtml(String(value))}
            </code>
          </td>
          <td style="padding: 10px 0; text-align: right; font-size: 12px; color: #999;">
            ${Math.round(confidence * 100)}% conf.
          </td>
        `;
        fieldsTable.appendChild(row);
      }
      dataBox.appendChild(fieldsTable);
      content.appendChild(dataBox);
    }

    // Actions
    const actions = document.createElement('div');
    actions.style.cssText = `
      display: flex;
      gap: 12px;
      margin-top: 24px;
      justify-content: flex-end;
    `;

    if (Object.keys(foundData).length > 0) {
      const approveBtn = document.createElement('button');
      approveBtn.textContent = 'Approve & Fill';
      approveBtn.style.cssText = `
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
      `;
      approveBtn.onclick = () => applyEnrichment(foundData, modal);
      actions.appendChild(approveBtn);
    }

    const closeBtn = document.createElement('button');
    closeBtn.textContent = 'Close';
    closeBtn.style.cssText = `
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
    `;
    closeBtn.onclick = () => modal.remove();
    actions.appendChild(closeBtn);

    content.appendChild(actions);
    modal.appendChild(content);
    document.body.appendChild(modal);
  }

  function applyEnrichment(foundData, modal) {
    // Fill form fields with found data
    for (const [field, value] of Object.entries(foundData)) {
      const input = document.querySelector(`input[name="${field}"], textarea[name="${field}"], select[name="${field}"]`);
      if (input) {
        input.value = String(value);
        // Trigger change event for form watchers
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    // Close modal
    modal.remove();

    // Scroll to form and show success
    const editContainer = document.getElementById('edit');
    if (editContainer) {
      editContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Show confirmation alert
    const alert = document.createElement('div');
    alert.style.cssText = `
      background: #d1fae5;
      border: 1px solid #6ee7b7;
      color: #065f46;
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-weight: 600;
    `;
    alert.textContent = 'Information filled in. Review and click "Save Contact" to apply.';
    
    const formSection = document.querySelector('.form-section:has(input[name="first_name"])');
    if (formSection) {
      formSection.parentNode.insertBefore(alert, formSection);
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
</script>

</body>
</html>
<?php include_once('layout_end.php'); ?>

<?php
// Close the database connection at the very end
if (isset($crmConn) && $crmConn instanceof mysqli) {
  $crmConn->close();
}
?>

