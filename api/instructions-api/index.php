<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/instructions-api/_instructions_lib.php';

$items = load_instructions();

$q = normalize_string($_GET['q'] ?? '');
$statusFilter = normalize_string($_GET['status'] ?? '');
$editId = normalize_string($_GET['edit'] ?? '');

$filtered = array_filter($items, function (array $item) use ($q, $statusFilter) {
    if ($q !== '') {
        $haystack = mb_strtolower(
            ($item['id'] ?? '') . ' ' .
            ($item['title'] ?? '') . ' ' .
            ($item['text'] ?? '') . ' ' .
            implode(' ', $item['tags'] ?? [])
        );
        if (!str_contains($haystack, mb_strtolower($q))) {
            return false;
        }
    }

    if ($statusFilter !== '' && ($item['status'] ?? '') !== $statusFilter) {
        return false;
    }

    return true;
});

$totalCount = count($items);
$filteredCount = count($filtered);
$statusCounts = [
    'draft' => 0,
    'active' => 0,
    'archived' => 0,
];
foreach ($items as $item) {
    $status = (string)($item['status'] ?? 'draft');
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

$editItem = [
    'id' => '',
    'title' => '',
    'text' => '',
    'audioMode' => 'single_mp3',
    'audioRef' => '',
    'audioPlaylist' => [],
    'tags' => [],
    'status' => 'draft',
    'notes' => '',
];

if ($editId !== '') {
    $found = find_instruction_by_id($editId, $items);
    if ($found) {
        $editItem = $found;
    }
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$playlistText = implode("\n", $editItem['audioPlaylist'] ?? []);
$tagsText = implode(', ', $editItem['tags'] ?? []);
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>BrailleStudio Instructions</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg: #f4f7fb;
      --card: #ffffff;
      --text: #172033;
      --muted: #5f6c85;
      --line: #d9e2ef;
      --blue: #2563eb;
      --blue-dark: #1d4ed8;
      --blue-soft: #dbeafe;
      --green-soft: #dcfce7;
      --amber-soft: #fef3c7;
      --gray-soft: #e5e7eb;
      --danger: #b91c1c;
      --shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 24px;
      background: linear-gradient(180deg, #eef4ff 0%, var(--bg) 220px);
      color: var(--text);
      font: 15px/1.5 Arial, sans-serif;
    }
    h1, h2, h3, p { margin-top: 0; }
    .page {
      max-width: 1440px;
      margin: 0 auto;
    }
    .hero {
      background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #60a5fa 100%);
      color: #fff;
      border-radius: 22px;
      padding: 28px;
      box-shadow: var(--shadow);
      margin-bottom: 20px;
    }
    .hero h1 {
      margin-bottom: 8px;
      font-size: 34px;
      line-height: 1.1;
    }
    .hero p {
      max-width: 760px;
      color: rgba(255,255,255,0.84);
      margin-bottom: 18px;
    }
    .stats {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
    }
    .stat {
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 16px;
      padding: 14px 16px;
      backdrop-filter: blur(6px);
    }
    .stat strong {
      display: block;
      font-size: 28px;
      line-height: 1;
      margin-bottom: 6px;
    }
    .stat span {
      color: rgba(255,255,255,0.82);
      font-size: 13px;
    }
    .grid {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(340px, 0.8fr);
      gap: 20px;
      align-items: start;
    }
    .card {
      border: 1px solid var(--line);
      border-radius: 20px;
      padding: 20px;
      background: var(--card);
      box-shadow: var(--shadow);
    }
    .card-header {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: start;
      margin-bottom: 16px;
    }
    .muted { color: var(--muted); font-size: 14px; }
    .small { font-size: 13px; }
    .filterbar {
      display: grid;
      grid-template-columns: 1.4fr 220px;
      gap: 10px;
      align-items: end;
      margin-bottom: 18px;
    }
    .form-toprow {
      display: grid;
      grid-template-columns: 1.4fr 220px;
      gap: 10px;
      align-items: end;
      margin-bottom: 18px;
    }
    .field-block {
      display: flex;
      flex-direction: column;
      justify-content: end;
    }
    .field-block label {
      margin-top: 0;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td {
      border-bottom: 1px solid #edf2f7;
      text-align: left;
      padding: 12px 10px;
      vertical-align: top;
    }
    th {
      background: #f8fbff;
      font-size: 13px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    tr:hover td { background: #fafcff; }
    label { display: block; margin-top: 12px; font-weight: 700; color: #24324a; }
    input[type="text"], textarea, select {
      width: 100%; padding: 12px 14px; margin-top: 6px;
      border: 1px solid #cbd5e1; border-radius: 12px; font: inherit;
      background: #fff;
    }
    select {
      min-height: 48px;
      height: 48px;
    }
    input[type="text"]:focus, textarea:focus, select:focus {
      outline: none;
      border-color: #60a5fa;
      box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.18);
    }
    textarea { min-height: 110px; resize: vertical; }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    button, .btn {
      display: inline-flex; align-items: center; justify-content: center;
      border: 0; border-radius: 12px; padding: 12px 16px;
      min-height: 48px;
      height: 48px;
      background: var(--blue); color: #fff; cursor: pointer; text-decoration: none;
      font-weight: 700;
      font-size: 15px;
      line-height: 1;
      font-family: Arial, sans-serif;
      white-space: nowrap;
      transition: transform 0.15s ease, background 0.15s ease;
    }
    button:hover, .btn:hover { transform: translateY(-1px); background: var(--blue-dark); }
    .btn.secondary { background: #64748b; }
    .btn.secondary:hover { background: #475569; }
    .btn.danger { background: var(--danger); }
    .btn.danger:hover { background: #991b1b; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .card-header .actions { margin: 0; }
    .status-pill {
      display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 12px;
      background: #eef2ff;
      color: #334155;
      font-weight: 700;
    }
    .status-active { background: var(--green-soft); }
    .status-draft { background: var(--amber-soft); }
    .status-archived { background: var(--gray-soft); }
    .list-meta {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }
    .meta-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: #eff6ff;
      color: #1e3a8a;
      font-size: 13px;
      font-weight: 700;
    }
    .helper {
      margin-top: 12px;
      padding: 12px 14px;
      border-radius: 14px;
      background: #f8fbff;
      border: 1px dashed #bfd3ea;
    }
    .field-hint {
      display: block;
      margin-top: 6px;
      color: var(--muted);
      font-size: 12px;
    }
    .message {
      margin-top: 14px;
      padding: 12px 14px;
      border-radius: 12px;
      background: #f8fafc;
      border: 1px solid var(--line);
    }
    pre { white-space: pre-wrap; word-break: break-word; }
    code {
      background: #eff6ff;
      color: #1d4ed8;
      padding: 2px 6px;
      border-radius: 8px;
    }
    @media (max-width: 1080px) {
      .grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 760px) {
      body { padding: 14px; }
      .hero { padding: 20px; }
      .hero h1 { font-size: 28px; }
      .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .filterbar { grid-template-columns: 1fr; }
      .row { grid-template-columns: 1fr; }
      table, thead, tbody, th, td, tr { display: block; }
      thead { display: none; }
      tbody tr {
        border: 1px solid #edf2f7;
        border-radius: 16px;
        margin-bottom: 12px;
        overflow: hidden;
      }
      tbody td {
        border-bottom: 1px solid #edf2f7;
      }
      tbody td:last-child { border-bottom: 0; }
    }
  </style>
</head>
<body>
  <div class="page">
    <section class="hero">
      <h1>BrailleStudio Instructions</h1>
      <div class="stats">
        <div class="stat"><strong><?= $totalCount ?></strong><span>Totaal instructies</span></div>
        <div class="stat"><strong><?= $statusCounts['active'] ?></strong><span>Active</span></div>
        <div class="stat"><strong><?= $statusCounts['draft'] ?></strong><span>Draft</span></div>
        <div class="stat"><strong><?= $statusCounts['archived'] ?></strong><span>Archived</span></div>
      </div>
    </section>

    <div class="grid">
    <div class="card">
      <div class="card-header">
        <div>
          <h2>Lijst</h2>
        </div>
        <div class="actions">
          <a class="btn secondary" href="index.php">Reset</a>
        </div>
      </div>

      <form method="get" action="" id="instructionFilterForm" class="filterbar">
        <div class="field-block">
          <label for="q">Zoeken</label>
          <input type="text" name="q" id="q" value="<?= h($q) ?>" placeholder="zoek op id, titel, tekst of tags">
        </div>
        <div class="field-block">
          <label for="status">Status</label>
          <select name="status" id="status" onchange="document.getElementById('instructionFilterForm').requestSubmit();">
            <option value="">alle</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>draft</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>active</option>
            <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>archived</option>
          </select>
        </div>
      </form>

      <div class="list-meta">
        <span class="meta-chip"><?= $filteredCount ?> zichtbaar</span>
        <?php if ($q !== ''): ?><span class="meta-chip">zoekterm: <?= h($q) ?></span><?php endif; ?>
        <?php if ($statusFilter !== ''): ?><span class="meta-chip">status: <?= h($statusFilter) ?></span><?php endif; ?>
      </div>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Titel</th>
            <th>Audio</th>
            <th>Status</th>
            <th>Acties</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filtered as $item): ?>
          <tr>
            <td><code><?= h($item['id']) ?></code></td>
            <td>
              <strong><?= h($item['title']) ?></strong><br>
              <span class="small muted"><?= h(implode(', ', $item['tags'] ?? [])) ?></span>
            </td>
            <td>
              <?= h($item['audioMode']) ?><br>
              <?php if (($item['audioMode'] ?? '') === 'single_mp3'): ?>
                <span class="small muted"><?= h($item['audioRef'] ?? '') ?></span>
              <?php else: ?>
                <span class="small muted"><?= count($item['audioPlaylist'] ?? []) ?> delen</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="status-pill status-<?= h($item['status']) ?>"><?= h($item['status']) ?></span>
            </td>
            <td class="actions">
              <a class="btn secondary small" href="?edit=<?= urlencode($item['id']) ?>">Bewerk</a>
              <button class="btn danger small" type="button" onclick="deleteInstruction('<?= h($item['id']) ?>')">Verwijder</button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($filtered) === 0): ?>
          <tr><td colspan="5" class="muted">Geen instructies gevonden.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-header">
        <div>
          <h2>Instructie</h2>
        </div>
        <div class="actions">
          <button type="submit" form="instructionForm">Opslaan</button>
          <a class="btn secondary" href="index.php">Nieuw</a>
        </div>
      </div>

      <form id="instructionForm">
        <input type="hidden" name="id" id="id" value="<?= h($editItem['id']) ?>">

        <div class="form-toprow">
          <div class="field-block">
            <label for="title">Titel</label>
            <input type="text" name="title" id="title" value="<?= h($editItem['title']) ?>">
          </div>
          <div class="field-block">
            <label for="audioMode">Audio mode</label>
            <select name="audioMode" id="audioMode" onchange="toggleAudioMode()">
              <option value="single_mp3" <?= $editItem['audioMode'] === 'single_mp3' ? 'selected' : '' ?>>single_mp3</option>
              <option value="playlist" <?= $editItem['audioMode'] === 'playlist' ? 'selected' : '' ?>>playlist</option>
            </select>
          </div>
        </div>

        <label for="text">Tekst</label>
        <textarea name="text" id="text"><?= h($editItem['text']) ?></textarea>

        <div class="row">
          <div>
            <label for="status2">Status</label>
            <select name="status" id="status2">
              <option value="draft" <?= $editItem['status'] === 'draft' ? 'selected' : '' ?>>draft</option>
              <option value="active" <?= $editItem['status'] === 'active' ? 'selected' : '' ?>>active</option>
              <option value="archived" <?= $editItem['status'] === 'archived' ? 'selected' : '' ?>>archived</option>
            </select>
          </div>
        </div>

        <div id="audioRefWrap">
          <label for="audioRef">AudioRef</label>
          <input type="text" name="audioRef" id="audioRef" value="<?= h($editItem['audioRef']) ?>" placeholder="instructions/raak_een_woord_aan.mp3">
          <span class="field-hint">Bijvoorbeeld <code>instructions/...</code> of <code>phonemes/aa.mp3</code>.</span>
        </div>

        <div id="playlistWrap">
          <label for="audioPlaylist">AudioPlaylist</label>
          <textarea name="audioPlaylist" id="audioPlaylist" placeholder="1 pad per regel"><?= h($playlistText) ?></textarea>
          <span class="field-hint">Elke regel is één audio-item. De volgorde is de afspeelvolgorde.</span>
        </div>

        <label for="tags">Tags</label>
        <input type="text" name="tags" id="tags" value="<?= h($tagsText) ?>" placeholder="cursor, woorden">

        <label for="notes">Notities</label>
        <textarea name="notes" id="notes"><?= h($editItem['notes']) ?></textarea>

      </form>

      <div class="helper small">
        Tip: gebruik tags zoals <code>klank</code>, <code>woorden</code> of <code>cursor</code> om later snel te filteren.
      </div>

      <div id="message" class="message muted"></div>
    </div>
  </div>
  </div>

  <script>
    function slugifyInstructionId(value) {
      return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_+/g, '_');
    }

    function generateInstructionId() {
      const title = document.getElementById('title').value.trim();
      const base = slugifyInstructionId(title) || 'instruction';
      const stamp = new Date().toISOString().replace(/[-:.TZ]/g, '').slice(0, 14);
      return 'instr_' + base + '_' + stamp;
    }

    function ensureInstructionId() {
      const idInput = document.getElementById('id');
      if (!idInput) return '';
      if (!idInput.value.trim()) {
        idInput.value = generateInstructionId();
      }
      return idInput.value.trim();
    }

    function toggleAudioMode() {
      const mode = document.getElementById('audioMode').value;
      document.getElementById('audioRefWrap').style.display = mode === 'single_mp3' ? 'block' : 'none';
      document.getElementById('playlistWrap').style.display = mode === 'playlist' ? 'block' : 'none';
    }

    async function deleteInstruction(id) {
      if (!confirm('Weet je zeker dat je deze instructie wilt verwijderen?')) return;

      const res = await fetch('/braillestudio/instructions-api/instructions_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });

      const data = await res.json();
      if (data.ok) {
        alert('Verwijderd: ' + id);
        location.href = 'index.php';
      } else {
        alert(data.error || 'Verwijderen mislukt');
      }
    }

    document.getElementById('instructionForm').addEventListener('submit', async function (e) {
      e.preventDefault();

      const payload = {
        id: ensureInstructionId(),
        title: document.getElementById('title').value.trim(),
        text: document.getElementById('text').value.trim(),
        audioMode: document.getElementById('audioMode').value,
        audioRef: document.getElementById('audioRef').value.trim(),
        audioPlaylist: document.getElementById('audioPlaylist').value,
        tags: document.getElementById('tags').value,
        status: document.getElementById('status2').value,
        notes: document.getElementById('notes').value.trim()
      };

      const res = await fetch('/braillestudio/instructions-api/instructions_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      const msg = document.getElementById('message');

      if (data.ok) {
        msg.textContent = 'Opgeslagen: ' + data.item.id;
        location.href = 'index.php?edit=' + encodeURIComponent(data.item.id);
      } else {
        msg.textContent = (data.error || 'Opslaan mislukt') + (data.errors ? ' — ' + data.errors.join('; ') : '');
      }
    });

    toggleAudioMode();
    ensureInstructionId();
  </script>
</body>
</html>
