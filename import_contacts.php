<?php
require_once __DIR__ . '/layout_start.php';
require_once 'csrf_helper.php';
initializeCSRFToken();

$import_type = null;
$schema = [];
$rows = [];
$preview = [];
$validation_errors = [];
$duplicate_emails = [];
$is_contacts = false;
$is_discussion = false;
$skipped_rows = 0;
$header = [];

// Detect import type and schema
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('CSRF validation failed');
  }
  $file = $_FILES['csv_file']['tmp_name'];
  if (($handle = fopen($file, 'r')) !== false) {
    $header = fgetcsv($handle);
    if ($header) {
      // Remove duplicate headers (keep first occurrence), trim whitespace, and fix typos
      $seen = [];
      $new_header = [];
      foreach ($header as $h) {
        $h = strtolower(trim($h));
        if ($h === 'compan') $h = 'company';
        // Prefer 'discussion_text' over 'entry_text'
        if ($h === 'entry_text' && in_array('discussion_text', $new_header)) continue;
        if (!in_array($h, $seen)) {
          $new_header[] = $h;
          $seen[] = $h;
        }
      }
      $header = $new_header;
      if (($peek = fgetcsv($handle)) !== false) {
        // Rewind file pointer to just after header
        fseek($handle, 0);
        fgetcsv($handle); // skip header again
      }

      // Alias entry_text to discussion_text BEFORE type detection
      if (!in_array('discussion_text', $header) && in_array('entry_text', $header)) {
        $header = array_map(function($h) {
          return $h === 'entry_text' ? 'discussion_text' : $h;
        }, $header);
      }
      // Detect type by header
      $contact_header_candidates = ['email', 'first_name', 'last_name', 'company', 'phone', 'address', 'city', 'province', 'postal_code', 'country'];
      $contact_header_matches = count(array_intersect($contact_header_candidates, $header));
      if (in_array('email', $header) && $contact_header_matches >= 2) {
        $import_type = 'contacts';
        $is_contacts = true;
        // Keep preview and validation aligned with practical contact CSV columns.
        $schema = ['first_name', 'last_name', 'email', 'phone', 'company', 'address', 'city', 'province', 'postal_code', 'country'];
      } elseif ((in_array('discussion_text', $header) || in_array('entry_text', $header)) && in_array('contact_id', $header)) {
        $import_type = 'discussion_log';
        $is_discussion = true;
        $schema = ['contact_id', 'author', 'timestamp', 'discussion_text', 'linked_opportunity_id', 'visibility', 'company'];
        // If 'entry_text' is present but 'discussion_text' is not, alias it
        if (!in_array('discussion_text', $header) && in_array('entry_text', $header)) {
          $header = array_map(function($h) {
            return $h === 'entry_text' ? 'discussion_text' : $h;
          }, $header);
          // Also update all rows already read
          foreach ($rows as &$row) {
            if (isset($row['entry_text'])) {
              $row['discussion_text'] = $row['entry_text'];
              unset($row['entry_text']);
            }
          }
          unset($row);
        }
      }
      // Read all rows, skip rows with column mismatch, and warn if any are skipped
      while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($header)) {
          $skipped_rows++;
        } else {
          $rows[] = array_combine($header, $row);
        }
      }
    }
    fclose($handle);
  }


// Validation and preview logic for contacts
if ($is_contacts) {
  $seenEmailKeys = [];
  foreach ($rows as $i => $row) {
    $row_errors = [];
    $email = trim((string)($row['email'] ?? ''));

    if ($email === '') {
      $row_errors[] = "email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $row_errors[] = "Invalid email";
    } else {
      $emailKey = strtolower($email);
      if (isset($seenEmailKeys[$emailKey])) {
        $row_errors[] = "Duplicate email in file";
        $duplicate_emails[$i + 2] = $email;
      } else {
        $seenEmailKeys[$emailKey] = true;
      }
    }

    if (!empty($row['delivery_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$row['delivery_date']))) {
      $row_errors[] = "Invalid delivery_date format (expected YYYY-MM-DD)";
    }

    if (empty($row_errors)) {
      $clean = [];
      foreach ($schema as $col) {
        $clean[$col] = trim((string)($row[$col] ?? ''));
      }
      $preview[] = $clean;
    } else {
      $validation_errors[$i+2] = $row_errors; // +2 for header and 0-index
    }
  }

} elseif ($is_discussion) {
  foreach ($rows as $i => $row) {
    $row_errors = [];
    $contactId = trim((string)($row['contact_id'] ?? ''));
    $author = trim((string)($row['author'] ?? ''));
    $discussionText = trim((string)($row['discussion_text'] ?? $row['entry_text'] ?? ''));

    if ($contactId === '' || !ctype_digit($contactId)) {
      $row_errors[] = "contact_id is required and must be numeric";
    }
    if ($author === '') {
      $row_errors[] = "author is required";
    }
    if ($discussionText === '') {
      $row_errors[] = "discussion_text is required";
    }

    if (empty($row_errors)) {
      $clean = [];
      foreach ($schema as $col) {
        $clean[$col] = trim((string)($row[$col] ?? ''));
      }
      if ($clean['discussion_text'] === '' && isset($row['entry_text'])) {
        $clean['discussion_text'] = trim((string)$row['entry_text']);
      }
      $preview[] = $clean;
    } else {
      $validation_errors[$i + 2] = $row_errors;
    }
  }
}
}

if (!empty($preview)) {
  $_SESSION['import_preview'] = $preview;
  $_SESSION['import_type'] = $is_contacts ? 'contacts' : 'discussion_log';
} else {
  unset($_SESSION['import_preview'], $_SESSION['import_type']);
}
?>
<div style="margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; max-width: 500px;">
  <h2>Import Contacts or Discussion Log</h2>
  <div style="margin: 14px 0 18px; padding: 12px; border: 1px solid #d9dee3; background: #fff; border-radius: 6px; font-size: 13px;">
    <h4 style="margin-top: 0; margin-bottom: 8px;">Header Labels and CSV Format</h4>
    <p style="margin-bottom: 8px;"><strong>Contacts headers (email required, others optional):</strong><br>first_name,last_name,email,phone,company,address,city,province,postal_code,country</p>
    <p style="margin-bottom: 8px;"><strong>Discussion log headers (required):</strong><br>contact_id,author,timestamp,discussion_text,linked_opportunity_id,visibility,company</p>
    <p style="margin-bottom: 0;"><strong>Format rules:</strong> include a header row on line 1, use comma-separated values (.csv), and keep each data row aligned to the same number of columns. Header matching is case-insensitive.</p>
  </div>
  <form method="POST" enctype="multipart/form-data">
    <?php renderCSRFInput(); ?>
    <div class="mb-3">
      <label for="csv_file" class="form-label">Select CSV file to import:</label>
      <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
    </div>
    <button type="submit" class="btn btn-primary">Upload &amp; Preview</button>
  </form>
</div>

<?php
global $is_contacts, $is_discussion;


// Show error if no preview and not recognized
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_contacts && !$is_discussion) {
  echo '<div class="alert alert-danger" style="margin:30px 0;">CSV file not recognized. Please check your headers and file format.';
  if (isset($header) && is_array($header)) {
    echo '<br><strong>Detected headers:</strong> ' . htmlspecialchars(implode(', ', $header));
  } else {
    echo '<br><strong>No header row detected or file could not be read.</strong>';
  }
  if (isset($skipped_rows) && $skipped_rows > 0) {
    echo '<br><strong>Warning:</strong> ' . $skipped_rows . ' row(s) were skipped due to column mismatch.';
  }
  echo '</div>';
}


if ($is_contacts || $is_discussion) {
  $total_rows = count($rows);
  $valid_rows = count($preview);
  $issues_found = count($validation_errors) + $skipped_rows;
  ?>
  <div style="margin-top: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 4px;">
    <h3>Import Summary</h3>
    <p><strong>Total rows:</strong> <?= $total_rows ?></p>
    <p><strong>Valid rows:</strong> <span style="color: <?= $valid_rows > 0 ? 'green' : '#b45309' ?>;"><?= $valid_rows ?></span></p>
    <p><strong>Issues found:</strong> <span style="color: <?= $issues_found > 0 ? '#b91c1c' : 'green' ?>;"><?= $issues_found ?></span></p>
    <p style="color: <?= $valid_rows > 0 ? 'green' : '#b45309' ?>; font-weight: bold;"><?= $valid_rows > 0 ? '✓ Ready to import valid rows only' : 'No valid rows to import' ?></p>
  </div>

  <?php if ($skipped_rows > 0): ?>
    <div class="alert alert-warning" style="margin-top: 12px;">
      <strong>Warning:</strong> <?= (int)$skipped_rows ?> row(s) were skipped due to column mismatch.
    </div>
  <?php endif; ?>

  <?php if (!empty($validation_errors)): ?>
    <div class="alert alert-danger" style="margin-top: 12px;">
      <strong>Validation issues were found.</strong>
      <ul style="margin:8px 0 0 18px;">
        <?php foreach (array_slice($validation_errors, 0, 10, true) as $line => $errs): ?>
          <li>Row <?= (int)$line ?>: <?= htmlspecialchars(implode('; ', $errs)) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if (count($validation_errors) > 10): ?>
        <div style="margin-top: 6px;">Showing first 10 rows with issues.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($preview)):
  ?>
    <h3 style="margin-top: 20px;">Preview - <?= $is_contacts ? 'Valid Contacts' : 'Discussion Log Entries' ?> (<?= count($preview) ?>)</h3>
    <form method="POST" action="commit_import.php" id="commitForm">
      <?php renderCSRFInput(); ?>
      <input type="hidden" name="import_type" value="<?= $is_contacts ? 'contacts' : 'discussion_log' ?>">
      <table class="spec-table" style="font-size: 12px;"><thead><tr>
        <?php foreach ($schema as $col): ?>
          <th><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $col))) ?></th>
        <?php endforeach; ?>
      </tr></thead><tbody>
        <?php foreach ($preview as $entry): ?>
          <tr>
            <?php foreach ($schema as $col): ?>
              <?php
                $value = $entry[$col] ?? '';
              ?>
              <td><?= htmlspecialchars(substr($value, 0, 50)) ?><?= (strlen($value) > 50 ? '...' : '') ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
      <div style="margin-top:20px; text-align:right;">
        <button type="submit" class="btn btn-success"><?= $is_contacts ? 'Commit Valid Contacts' : 'Commit Valid Discussion Entries' ?></button>
      </div>
    </form>
  <?php endif; ?>
<?php }
?>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h3>Confirm Import</h3>
    <p>This will permanently add these contacts to the system. Proceed?</p>
    <button onclick="document.getElementById('commitForm').submit()">Yes, Commit</button>
    <button onclick="hideConfirmModal()">Cancel</button>
  </div>
</div>

<!-- Modal Styles & Script -->
<style>
.modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center;
}
.modal-box {
  background: #fff; padding: 20px 30px; border-radius: 8px; text-align: center;
  box-shadow: 0 0 10px rgba(0,0,0,0.2);
}
.modal-box button {
  margin: 10px; padding: 8px 16px; border: none; border-radius: 4px;
  background: #0077cc; color: #fff; cursor: pointer;
}
.modal-box button:hover {
  background: #005fa3;
}
</style>

<script>
function showConfirmModal() {
  document.getElementById('confirmModal').style.display = 'flex';
}
function hideConfirmModal() {
  document.getElementById('confirmModal').style.display = 'none';
}
</script>


<?php include_once 'layout_end.php'; ?>
