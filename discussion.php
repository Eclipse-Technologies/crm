<?php
// discussion.php - Discussion Log Page
require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/csrf_helper.php';

$pageTitle = 'Discussion Log';

function discussion_has_column(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $has = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $has;
}

$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_touchpoint'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $flashMessage = 'Security validation failed. Please refresh and try again.';
        $flashType = 'danger';
    } else {
        $contactIdRaw = trim((string) ($_POST['contact_id'] ?? ''));
        $contactId = $contactIdRaw !== '' ? (int) $contactIdRaw : 0;
        $author = trim((string) ($_POST['author'] ?? ''));
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $valueDelivered = trim((string) ($_POST['value_delivered'] ?? ''));
        $nextAction = trim((string) ($_POST['next_action'] ?? ''));
        $touchType = trim((string) ($_POST['touch_type'] ?? 'check-in'));
        $channel = trim((string) ($_POST['channel'] ?? 'phone'));
        $visibility = trim((string) ($_POST['visibility'] ?? 'private'));
        $nextTouchAtRaw = trim((string) ($_POST['next_touch_at'] ?? ''));
        $nextTouchAt = $nextTouchAtRaw !== '' ? str_replace('T', ' ', $nextTouchAtRaw) : null;

        if ($author === '' && function_exists('auth_current_user')) {
            $currentUser = auth_current_user();
            $author = trim((string) ($currentUser['username'] ?? ''));
        }
        if ($author === '') {
            $author = 'System';
        }

        if ($contactId <= 0 || $summary === '') {
            $flashMessage = 'Contact and summary are required to log a touchpoint.';
            $flashType = 'danger';
        } else {
            $entryLines = [
                '[Touchpoint] ' . ucfirst($touchType) . ' via ' . $channel,
                'Summary: ' . $summary,
            ];
            if ($valueDelivered !== '') {
                $entryLines[] = 'Value delivered: ' . $valueDelivered;
            }
            if ($nextAction !== '') {
                $entryLines[] = 'Next action: ' . $nextAction;
            }
            if ($nextTouchAt !== null) {
                $entryLines[] = 'Next touch: ' . $nextTouchAt;
            }
            $entryText = implode("\n", $entryLines);

            $conn = get_mysql_connection();

            $stmt = $conn->prepare('INSERT INTO discussion_log (contact_id, author, entry_text, linked_opportunity_id, visibility, timestamp) VALUES (?, ?, ?, ?, ?, NOW())');
            if ($stmt) {
                $blankOpportunity = '';
                $stmt->bind_param('sssss', $contactIdRaw, $author, $entryText, $blankOpportunity, $visibility);
                $stmt->execute();
                $stmt->close();
            }

            if (discussion_has_column($conn, 'customer_touchpoints', 'id')) {
                $customerIdForTouch = null;
                $stmt = $conn->prepare('SELECT customer_id FROM customers WHERE contact_id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $contactId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result ? $result->fetch_assoc() : null;
                    if ($result) {
                        $result->free();
                    }
                    $stmt->close();
                    $customerIdForTouch = $row['customer_id'] ?? null;
                }

                if ($customerIdForTouch !== null) {
                    $stmt = $conn->prepare('INSERT INTO customer_touchpoints (customer_id, contact_id, touch_type, channel, summary, value_delivered, next_action, next_touch_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    if ($stmt) {
                        $stmt->bind_param('sssssssss', $customerIdForTouch, $contactIdRaw, $touchType, $channel, $summary, $valueDelivered, $nextAction, $nextTouchAt, $author);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            if (discussion_has_column($conn, 'contacts', 'last_touch_at')) {
                if (discussion_has_column($conn, 'contacts', 'next_touch_at')) {
                    $stmt = $conn->prepare('UPDATE contacts SET last_touch_at = NOW(), next_touch_at = COALESCE(?, next_touch_at) WHERE contact_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('si', $nextTouchAt, $contactId);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    $stmt = $conn->prepare('UPDATE contacts SET last_touch_at = NOW() WHERE contact_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('i', $contactId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            if (discussion_has_column($conn, 'customers', 'last_touch_at')) {
                $customerSet = ['last_touch_at = NOW()'];
                $params = [];
                $types = '';

                if (discussion_has_column($conn, 'customers', 'last_touch_summary')) {
                    $customerSet[] = 'last_touch_summary = ?';
                    $params[] = $summary;
                    $types .= 's';
                }
                if (discussion_has_column($conn, 'customers', 'next_touch_goal')) {
                    $customerSet[] = 'next_touch_goal = ?';
                    $params[] = $nextAction;
                    $types .= 's';
                }
                if ($nextTouchAt !== null && discussion_has_column($conn, 'customers', 'next_touch_at')) {
                    $customerSet[] = 'next_touch_at = ?';
                    $params[] = $nextTouchAt;
                    $types .= 's';
                }
                if (discussion_has_column($conn, 'customers', 'preferred_channel')) {
                    $customerSet[] = 'preferred_channel = ?';
                    $params[] = $channel;
                    $types .= 's';
                }
                if (discussion_has_column($conn, 'customers', 'last_modified')) {
                    $customerSet[] = 'last_modified = NOW()';
                }

                $sql = 'UPDATE customers SET ' . implode(', ', $customerSet) . ' WHERE contact_id = ?';
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $types .= 'i';
                    $params[] = $contactId;
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->close();

            $flashMessage = 'Touchpoint logged successfully.';
            $flashType = 'success';
        }
    }
}

$conn = get_mysql_connection();
$contacts = [];
$contactResult = $conn->query('SELECT contact_id, company, first_name, last_name FROM contacts ORDER BY company ASC LIMIT 500');
if ($contactResult) {
    while ($row = $contactResult->fetch_assoc()) {
        $contacts[] = $row;
    }
    $contactResult->free();
}

// Only show logs with valid integer contact_id and join to contacts for company name
$sql = "SELECT d.*, c.company FROM discussion_log d LEFT JOIN contacts c ON d.contact_id = c.contact_id WHERE d.contact_id REGEXP '^[0-9]+$' ORDER BY d.timestamp DESC LIMIT 100";
$result = $conn->query($sql);
$discussions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
if ($result) {
    $result->free();
}
$conn->close();

require_once __DIR__ . '/layout_start.php';
?>
<div class="container mt-4">
  <h1 class="mb-4">Discussion Log</h1>
  <p class="text-muted">Showing the 100 most recent entries. Visit a contact page to see full history per contact.</p>

  <?php if ($flashMessage !== ''): ?>
    <div class="alert alert-<?= $flashType === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($flashMessage) ?></div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header"><strong>Log Touchpoint</strong></div>
    <div class="card-body">
      <form method="POST" class="row g-3">
        <?php renderCSRFInput(); ?>
        <input type="hidden" name="log_touchpoint" value="1">

        <div class="col-md-4">
          <label class="form-label">Contact</label>
          <select name="contact_id" class="form-select" required>
            <option value="">Select contact...</option>
            <?php foreach ($contacts as $contact): ?>
              <option value="<?= htmlspecialchars((string) $contact['contact_id']) ?>">
                <?= htmlspecialchars(($contact['company'] ?? 'No Company') . ' - ' . trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Type</label>
          <select name="touch_type" class="form-select">
            <option value="check-in">Check-in</option>
            <option value="advisory">Advisory</option>
            <option value="follow-up">Follow-up</option>
            <option value="renewal">Renewal</option>
            <option value="issue-resolution">Issue Resolution</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Channel</label>
          <select name="channel" class="form-select">
            <option value="phone">Phone</option>
            <option value="email">Email</option>
            <option value="text">Text</option>
            <option value="meeting">Meeting</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Visibility</label>
          <select name="visibility" class="form-select">
            <option value="private">Private</option>
            <option value="internal">Internal</option>
            <option value="public">Public</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Author</label>
          <input type="text" name="author" class="form-control" placeholder="Auto if blank">
        </div>

        <div class="col-12">
          <label class="form-label">Summary</label>
          <textarea name="summary" class="form-control" rows="2" required></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Value Delivered</label>
          <textarea name="value_delivered" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Next Action</label>
          <textarea name="next_action" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-4">
          <label class="form-label">Next Touch Date/Time</label>
          <input type="datetime-local" name="next_touch_at" class="form-control">
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary">Log Touchpoint</button>
        </div>
      </form>
    </div>
  </div>

  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th>Timestamp</th>
        <th>Author</th>
        <th>Company</th>
        <th>Entry</th>
        <th>Visibility</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($discussions as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['timestamp']) ?></td>
          <td><?= htmlspecialchars($row['author']) ?></td>
          <td><?= htmlspecialchars($row['company']) ?></td>
          <td><?= nl2br(htmlspecialchars($row['entry_text'])) ?></td>
          <td><?= htmlspecialchars($row['visibility']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($discussions)): ?>
        <tr><td colspan="5" class="text-center">No discussion log entries found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
