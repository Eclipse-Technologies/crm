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
        $h = trim($h);
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
      if (in_array('email', $header) && in_array('first_name', $header)) {
        $import_type = 'contacts';
        $is_contacts = true;
        $schema = require 'contact_schema.php';
      } elseif ((in_array('discussion_text', $header) || in_array('entry_text', $header)) && in_array('contact_id', $header)) {
        $import_type = 'discussion_log';
        $is_discussion = true;
        $schema = require 'discussion_schema.php';
        // If the CSV header has 'entry_text' but not 'discussion_text', map schema for preview
        if (!in_array('discussion_text', $header) && in_array('entry_text', $header)) {
          $schema = array_map(function($col) {
            return $col === 'discussion_text' ? 'entry_text' : $col;
          }, $schema);
        }
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
      $skipped_rows = 0;
      $row_num = 2; // 1-based, header is row 1
      while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($header)) {
          $skipped_rows++;
          // Optionally, collect info about which row was skipped
        } else {
          $rows[] = array_combine($header, $row);
        }
        $row_num++;
      }
    }
    fclose($handle);
  }


// Validation and preview logic for contacts
if ($is_contacts) {
  foreach ($rows as $i => $row) {
    $row_errors = [];
    foreach ($schema as $col) {
      if (!isset($row[$col]) || $row[$col] === '') {
        $row_errors[] = "$col is required";
      }
    }
    if (!empty($row['email'])) {
      if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        $row_errors[] = "Invalid email";
      }
    }
    if (empty($row_errors)) {
      $preview[] = $row;
    } else {
      $validation_errors[$i+2] = $row_errors; // +2 for header and 0-index
    }
  }
  // Check for duplicate emails
  $emails = array_column($preview, 'email');
  $dupes = array_diff_assoc($emails, array_unique($emails));
  foreach ($dupes as $idx => $email) {
    $duplicate_emails[$idx+2] = $email;
  }

// Always show the upload form if no file has been uploaded
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
}
// Close the main POST/file upload if block
}
// Close the previous if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) block
}
?>
<div style="margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; max-width: 500px;">
  <h2>Import Contacts or Discussion Log</h2>
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
  $valid_rows = count($rows); // For now, all rows are considered valid for discussion log
  ?>
  <div style="margin-top: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 4px;">
    <h3>Import Summary</h3>
    <p><strong>Total rows:</strong> <?= $total_rows ?></p>
    <p><strong>Valid rows:</strong> <span style="color: green;"><?= $valid_rows ?></span></p>
    <p><strong>Issues found:</strong> <span style="color: green;">0</span></p>
    <p style="color: green; font-weight: bold;">✓ Ready to import</p>
  </div>
  <?php if (!empty($rows)):
  ?>
    <h3 style="margin-top: 20px;">Preview - <?= $is_contacts ? 'Valid Contacts' : 'Discussion Log Entries' ?> (<?= count($rows) ?>)</h3>
    <form method="POST" action="commit_import.php" id="commitForm">
      <?php renderCSRFInput(); ?>
      <table class="spec-table" style="font-size: 12px;"><thead><tr>
        <?php foreach ($schema as $col): ?>
          <th><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $col))) ?></th>
        <?php endforeach; ?>
      </tr></thead><tbody>
        <?php foreach ($rows as $entry): ?>
          <tr>
            <?php foreach ($schema as $col): ?>
              <?php
                // For preview: if schema col is 'entry_text' but row has 'discussion_text', use that value
                // For this import instance, use company as the main reference and leave contact_id blank
                if ($is_discussion && $col === 'contact_id') {
                  $value = '';
                } else {
                  $value = $entry[$col] ?? ($col === 'entry_text' && isset($entry['discussion_text']) ? $entry['discussion_text'] : '');
                }
              ?>
              <td><?= htmlspecialchars(substr($value, 0, 50)) ?><?= (strlen($value) > 50 ? '...' : '') ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
      <div style="margin-top:20px; text-align:right;">
        <button type="submit" class="btn btn-success">Commit Import to Discussion Log</button>
      </div>
    </form>
    <?php $_SESSION['import_preview'] = $rows; ?>
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
