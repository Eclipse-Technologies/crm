<?php
// Security headers
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$pageTitle = 'AI Settings';

require_once 'simple_auth/middleware.php';
require_once 'layout_start.php';
require_once 'db_mysql.php';
require_once 'csrf_helper.php';
require_once 'ai_helper.php';

$conn = get_mysql_connection();
ai_ensure_config_table($conn);

$message = '';
$messageType = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai_config'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $newProvider = $_POST['ai_provider'] ?? '';
        $newModel    = $_POST['ai_model']    ?? '';
    $selectionMode = $_POST['ai_selection_mode'] ?? 'manual';

        $registry = ai_get_models();
    if (($selectionMode !== 'manual' && $selectionMode !== 'cheapest') || !isset($registry[$newProvider]['models'][$newModel])) {
            $message = 'Invalid provider or model selection.';
            $messageType = 'error';
        } else {
      if (ai_save_config($conn, $newProvider, $newModel, $selectionMode)) {
        $message = $selectionMode === 'cheapest'
          ? 'AI settings saved. Cheapest configured model will be chosen automatically at request time.'
          : 'AI settings saved. Active model updated to: ' . htmlspecialchars($registry[$newProvider]['models'][$newModel]['label']);
                $messageType = 'success';
            } else {
                $message = 'Failed to save settings. Check DB permissions.';
                $messageType = 'error';
            }
        }
    }
}

$cfg      = ai_get_config($conn);
$registry = ai_get_models();

// Flatten all models sorted by input cost for the comparison table
$flatModels = [];
foreach ($registry as $providerKey => $providerData) {
    foreach ($providerData['models'] as $modelKey => $modelData) {
        $flatModels[] = array_merge($modelData, [
            'provider_key' => $providerKey,
            'provider_label' => $providerData['label'],
            'model_key'    => $modelKey,
        ]);
    }
}
usort($flatModels, fn($a, $b) => $a['input'] <=> $b['input']);

$csrfToken = getCSRFToken();
?>

<style>
  .ai-settings-wrap { max-width: 900px; margin: 0 auto; padding: 24px; }
  h1 { font-size: 22px; font-weight: 700; margin: 0 0 6px 0; }
  .subtitle { color: #6b7280; font-size: 13px; margin: 0 0 24px 0; }

  .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; font-weight: 500; }
  .alert-success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
  .alert-error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

  .card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px; overflow: hidden; }
  .card-header { background: #f9fafb; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 8px; }
  .card-header h2 { font-size: 14px; font-weight: 700; margin: 0; color: #111827; }
  .card-body { padding: 16px; }

  /* Cost comparison table */
  .model-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .model-table th { text-align: left; padding: 8px 10px; background: #f3f4f6; border-bottom: 2px solid #e5e7eb; font-size: 11px; text-transform: uppercase; color: #6b7280; font-weight: 700; }
  .model-table td { padding: 9px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
  .model-table tr:last-child td { border-bottom: none; }
  .model-table tr:hover td { background: #f9fafb; }
  .model-table tr.active-row td { background: #eff6ff; }

  .provider-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
  .badge-openai    { background: #dbeafe; color: #1d4ed8; }
  .badge-anthropic { background: #fce7f3; color: #9d174d; }
  .badge-google    { background: #d1fae5; color: #065f46; }

  .recommended-tag { background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; margin-left: 4px; }
  .cost-value { font-family: monospace; font-size: 12px; }
  .cheapest { color: #059669; font-weight: 700; }

  .radio-cell input[type=radio] { width: 16px; height: 16px; cursor: pointer; accent-color: #3b82f6; }

  /* API key status */
  .key-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
  .key-card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; }
  .key-status-icon { font-size: 18px; }
  .key-card-body { flex: 1; }
  .key-name { font-size: 12px; font-family: monospace; font-weight: 600; color: #1f2937; }
  .key-desc { font-size: 11px; color: #6b7280; margin-top: 2px; }
  .key-ok   { border-color: #6ee7b7; background: #f0fdf4; }
  .key-miss { border-color: #fca5a5; background: #fef2f2; }

  .env-note { font-size: 12px; color: #6b7280; margin-top: 10px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 12px; font-family: monospace; line-height: 1.7; }

  .active-summary { display: flex; align-items: center; gap: 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px 14px; margin-bottom: 20px; }
  .active-label { font-size: 12px; color: #6b7280; }
  .active-value { font-size: 14px; font-weight: 700; color: #1d4ed8; }
  .active-cost  { font-size: 12px; color: #374151; margin-left: auto; font-family: monospace; }
  .guard-banner { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; font-weight: 600; }

  .btn-save { background: #3b82f6; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
  .btn-save:hover { background: #2563eb; }
</style>

<div class="ai-settings-wrap">
  <h1>⚙️ AI Settings</h1>
  <p class="subtitle">Choose your AI provider and model. Prices shown per 1 million tokens (USD). Select cheapest that meets your quality needs.</p>

  <?php if ($message !== ''): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <?php
    $activeMeta = $registry[$cfg['provider']]['models'][$cfg['model']] ?? null;
    $activeCost = $activeMeta ? '$' . number_format($activeMeta['input'], 3) . ' / $' . number_format($activeMeta['output'], 3) . ' per 1M tokens' : 'Unknown';
    $activeLabel = $activeMeta ? htmlspecialchars($registry[$cfg['provider']]['label'] . ' — ' . $activeMeta['label']) : htmlspecialchars($cfg['provider'] . ' / ' . $cfg['model']);
    $cheapestConfigured = ai_get_cheapest_available_model();
  ?>

  <?php if (empty($cfg['enabled'])): ?>
    <div class="guard-banner">AI no-spend mode is ON. Requests are logged, but no external model calls are sent until <code>AI_ENABLED=1</code> is set in the environment.</div>
  <?php endif; ?>

  <div class="active-summary">
    <div>
      <div class="active-label"><?= ($cfg['selection_mode'] ?? 'manual') === 'cheapest' ? 'Selection mode' : 'Active model' ?></div>
      <div class="active-value"><?= ($cfg['selection_mode'] ?? 'manual') === 'cheapest' ? 'Cheapest configured model' : $activeLabel ?></div>
    </div>
    <div class="active-cost">
      <?php if (($cfg['selection_mode'] ?? 'manual') === 'cheapest' && $cheapestConfigured): ?>
        Currently resolves to <?= htmlspecialchars($cheapestConfigured['provider_label'] . ' — ' . $cheapestConfigured['label']) ?>
      <?php else: ?>
        <?= $activeCost ?> (input / output)
      <?php endif; ?>
    </div>
  </div>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="save_ai_config" value="1">
    <input type="hidden" name="ai_provider" id="hidden_provider" value="<?= htmlspecialchars($cfg['provider']) ?>">
    <input type="hidden" name="ai_model"    id="hidden_model"    value="<?= htmlspecialchars($cfg['model']) ?>">

    <div class="card">
      <div class="card-header">
        <span>🧠</span>
        <h2>Selection Strategy</h2>
      </div>
      <div class="card-body">
        <label style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;cursor:pointer;">
          <input type="radio" name="ai_selection_mode" value="manual" <?= ($cfg['selection_mode'] ?? 'manual') === 'manual' ? 'checked' : '' ?> style="margin-top:2px;">
          <span>
            <strong>Manual</strong><br>
            <span style="color:#6b7280;font-size:12px;">Always use the provider/model selected below.</span>
          </span>
        </label>
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
          <input type="radio" name="ai_selection_mode" value="cheapest" <?= ($cfg['selection_mode'] ?? 'manual') === 'cheapest' ? 'checked' : '' ?> style="margin-top:2px;">
          <span>
            <strong>Cheapest configured</strong><br>
            <span style="color:#6b7280;font-size:12px;">At request time, use the lowest-cost model across providers that currently have API keys configured.</span>
            <?php if ($cheapestConfigured): ?>
              <br><span style="color:#059669;font-size:12px;">Current cheapest: <?= htmlspecialchars($cheapestConfigured['provider_label'] . ' — ' . $cheapestConfigured['label']) ?></span>
            <?php else: ?>
              <br><span style="color:#dc2626;font-size:12px;">No configured providers available yet.</span>
            <?php endif; ?>
          </span>
        </label>
      </div>
    </div>

    <!-- Model comparison table -->
    <div class="card">
      <div class="card-header">
        <span>📊</span>
        <h2>Model Cost Comparison — sorted cheapest first</h2>
      </div>
      <div class="card-body" style="padding:0;">
        <table class="model-table">
          <thead>
            <tr>
              <th>Select</th>
              <th>Provider</th>
              <th>Model</th>
              <th>Input / 1M</th>
              <th>Output / 1M</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($flatModels as $m):
              $isActive = ($m['provider_key'] === $cfg['provider'] && $m['model_key'] === $cfg['model']);
              $rowId    = 'model_' . $m['provider_key'] . '_' . str_replace(['.', '-'], '_', $m['model_key']);
              $isCheapest = ($m === $flatModels[0]);
            ?>
            <tr class="<?= $isActive ? 'active-row' : '' ?>">
              <td class="radio-cell">
                <input type="radio"
                  name="model_radio"
                  id="<?= $rowId ?>"
                  value="<?= htmlspecialchars($m['provider_key'] . '|' . $m['model_key']) ?>"
                  <?= $isActive ? 'checked' : '' ?>
                  onchange="selectModel('<?= htmlspecialchars($m['provider_key']) ?>', '<?= htmlspecialchars($m['model_key']) ?>')">
              </td>
              <td>
                <span class="provider-badge badge-<?= htmlspecialchars($m['provider_key']) ?>">
                  <?= htmlspecialchars($m['provider_label']) ?>
                </span>
              </td>
              <td>
                <label for="<?= $rowId ?>" style="cursor:pointer;font-weight:<?= $isActive ? '700' : '400' ?>">
                  <?= htmlspecialchars($m['label']) ?>
                </label>
                <?php if ($m['recommended']): ?>
                  <span class="recommended-tag">★ Recommended</span>
                <?php endif; ?>
              </td>
              <td class="cost-value <?= $isCheapest ? 'cheapest' : '' ?>">
                $<?= number_format($m['input'], 3) ?>
              </td>
              <td class="cost-value">
                $<?= number_format($m['output'], 3) ?>
              </td>
              <td style="color:#6b7280;font-size:12px;">
                <?= htmlspecialchars($m['notes']) ?>
                <?php if (!ai_key_configured($m['provider_key'])): ?>
                  <span style="color:#dc2626;font-size:11px;"> — no key</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <button type="submit" class="btn-save">💾 Save Selection</button>
  </form>

  <!-- API Key Status -->
  <div class="card" style="margin-top:24px;">
    <div class="card-header">
      <span>🔑</span>
      <h2>API Key Status</h2>
    </div>
    <div class="card-body">
      <div class="key-grid">
        <?php
        $keyDefs = [
            ['provider' => 'openai',    'env' => 'OPENAI_API_KEY',    'label' => 'OpenAI',        'url' => 'https://platform.openai.com/api-keys'],
            ['provider' => 'anthropic', 'env' => 'ANTHROPIC_API_KEY', 'label' => 'Anthropic',     'url' => 'https://console.anthropic.com/account/keys'],
            ['provider' => 'google',    'env' => 'GOOGLE_AI_KEY',     'label' => 'Google Gemini', 'url' => 'https://aistudio.google.com/app/apikey'],
        ];
        foreach ($keyDefs as $kd):
            $ok = ai_key_configured($kd['provider']);
        ?>
        <div class="key-card <?= $ok ? 'key-ok' : 'key-miss' ?>">
          <div class="key-status-icon"><?= $ok ? '✅' : '❌' ?></div>
          <div class="key-card-body">
            <div class="key-name"><?= htmlspecialchars($kd['env']) ?></div>
            <div class="key-desc">
              <?= $ok ? $kd['label'] . ' — configured' : $kd['label'] . ' — not set' ?>
              <?php if (!$ok): ?>
                · <a href="<?= htmlspecialchars($kd['url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:11px;">Get key</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="env-note">
        Add keys to your <strong>.env</strong> file in the CRM folder:<br>
        OPENAI_API_KEY=sk-...<br>
        ANTHROPIC_API_KEY=sk-ant-...<br>
        GOOGLE_AI_KEY=AIza...
      </div>
    </div>
  </div>

</div>

<script>
function selectModel(provider, model) {
  document.getElementById('hidden_provider').value = provider;
  document.getElementById('hidden_model').value    = model;
}
</script>

<?php require_once 'layout_end.php'; ?>
