<?php
$cid = $contact['id'] ?? '';
if (empty($cid)) return;

$discussionSchema = require __DIR__ . '/discussion_schema.php';
require_once __DIR__ . '/discussion_mysql.php';
$entries = fetch_discussions_mysql($cid, $discussionSchema);
?>

<div style="margin-top:40px;">
  <h3>Discussion</h3>
  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:40px; align-items:start;">

    <!-- Add Discussion Entry (Left) -->
    <div>
      <h4>Add Discussion Entry</h4>
      <form method="post" style="display:grid; gap:15px;">
        <input type="hidden" name="contact_id" value="<?= htmlspecialchars($cid) ?>">

        <div>
          <label for="author">Author:</label><br>
          <input type="text" name="author" id="author" value="Robert Lee" required>
        </div>

        <div>
          <label for="entry_text">Comment:</label><br>
          <textarea name="entry_text" id="entry_text" rows="4" required></textarea>
        </div>

        <div>
          <label for="linked_opportunity_id">Linked Opportunity ID (optional):</label><br>
          <input type="text" name="linked_opportunity_id" id="linked_opportunity_id">
        </div>

        <div>
          <label for="visibility">Visibility:</label><br>
          <select name="visibility" id="visibility">
            <option value="public">Public</option>
            <option value="internal">Internal</option>
          </select>
        </div>

        <button type="submit" class="btn-primary">💬 Log Discussion</button>
      </form>
    </div>

    <!-- Discussion History (Right) -->
    <div>
      <h4>Discussion History</h4>
      <?php if (!empty($entries)): ?>
        <?php foreach ($entries as $entry): ?>
          <div style="border-left:3px solid #0099A8; padding-left:10px; margin-bottom:15px;">
            <div style="font-size:0.9em; color:#555;">
              <strong><?= htmlspecialchars($entry['author'] ?? 'Admin') ?></strong>
              <em><?= htmlspecialchars($entry['timestamp'] ?? '') ?></em>
              <?php if (!empty($entry['linked_opportunity_id'])): ?>
                • <span>Opportunity: <?= htmlspecialchars($entry['linked_opportunity_id']) ?></span>
              <?php endif; ?>
              <?php if (!empty($entry['visibility'])): ?>
                • <span>Visibility: <?= htmlspecialchars($entry['visibility']) ?></span>
              <?php endif; ?>
            </div>
            <div style="margin-top:5px;">
              <?= nl2br(htmlspecialchars($entry['entry_text'] ?? '')) ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:#666;">No discussion history found for this contact.</p>
      <?php endif; ?>
    </div>

  </div>
</div>
