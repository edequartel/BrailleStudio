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
    body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
    h1, h2 { margin-bottom: 12px; }
    h2 { margin-top: 0; }
    .grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; }
    .card { border: 1px solid #ddd; border-radius: 10px; padding: 16px; background: #fff; }
    .muted { color: #666; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #eee; text-align: left; padding: 8px; vertical-align: top; }
    th { background: #fafafa; }
    label { display: block; margin-top: 12px; font-weight: 600; }
    input[type="text"], textarea, select {
      width: 100%; padding: 10px; box-sizing: border-box; margin-top: 6px;
      border: 1px solid #ccc; border-radius: 8px; font: inherit;
    }
    textarea { min-height: 90px; }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    button, .btn {
      display: inline-block; border: 0; border-radius: 8px; padding: 10px 14px;
      background: #2563eb; color: #fff; cursor: pointer; text-decoration: none;
    }
    .btn.secondary { background: #666; }
    .btn.danger { background: #b91c1c; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .section-head { margin-bottom: 16px; }
    .small { font-size: 13px; }
    .status-pill {
      display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px;
      background: #eee;
    }
    .status-active { background: #dcfce7; }
    .status-draft { background: #fef3c7; }
    .status-archived { background: #e5e7eb; }
    .topbar { margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: end; }
    .topbar form { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; }
    pre { white-space: pre-wrap; word-break: break-word; }
  </style>
</head>
<body>
  <h1>BrailleStudio Instructions</h1>
  <p class="muted">
    Beheer hier herbruikbare instructies voor Blockly-lessen. Blockly bewaart dan alleen een
    <code>instructionId</code> en niet overal losse tekst of mp3-paden.
  </p>

  <div class="topbar">
    <form method="get" action="">
      <div>
        <label for="q">Zoeken</label>
        <input type="text" name="q" id="q" value="<?= h($q) ?>" placeholder="zoek op id, titel, tekst, tags">
      </div>
      <div>
        <label for="status">Status</label>
        <select name="status" id="status">
          <option value="">alle</option>
          <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>draft</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>active</option>
          <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>archived</option>
        </select>
      </div>
      <div>
        <button type="submit">Filter</button>
      </div>
      <div>
        <a class="btn secondary" href="index.php">Reset</a>
      </div>
    </form>
  </div>

  <div class="grid">
    <div class="card">
      <div class="section-head">
        <h2>Lijst</h2>
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
      <div class="section-head">
        <h2><?= $editId !== '' ? 'Instructie bewerken' : 'Nieuwe instructie' ?></h2>
      </div>

      <div class="actions" style="margin-bottom:16px;">
        <button type="submit" form="instructionForm">Opslaan</button>
        <a class="btn secondary" href="index.php">Nieuw</a>
      </div>

      <form id="instructionForm">
        <label for="id">ID</label>
        <input type="text" name="id" id="id" value="<?= h($editItem['id']) ?>" placeholder="instr_raak_woord_aan_v1">

        <label for="title">Titel</label>
        <input type="text" name="title" id="title" value="<?= h($editItem['title']) ?>">

        <label for="text">Tekst</label>
        <textarea name="text" id="text"><?= h($editItem['text']) ?></textarea>

        <div class="row">
          <div>
            <label for="audioMode">Audio mode</label>
            <select name="audioMode" id="audioMode" onchange="toggleAudioMode()">
              <option value="single_mp3" <?= $editItem['audioMode'] === 'single_mp3' ? 'selected' : '' ?>>single_mp3</option>
              <option value="playlist" <?= $editItem['audioMode'] === 'playlist' ? 'selected' : '' ?>>playlist</option>
            </select>
          </div>
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
        </div>

        <div id="playlistWrap">
          <label for="audioPlaylist">AudioPlaylist</label>
          <textarea name="audioPlaylist" id="audioPlaylist" placeholder="1 pad per regel"><?= h($playlistText) ?></textarea>
        </div>

        <label for="tags">Tags</label>
        <input type="text" name="tags" id="tags" value="<?= h($tagsText) ?>" placeholder="cursor, woorden">

        <label for="notes">Notities</label>
        <textarea name="notes" id="notes"><?= h($editItem['notes']) ?></textarea>

      </form>

      <div id="message" style="margin-top:14px;" class="muted"></div>
    </div>
  </div>

  <script>
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
        id: document.getElementById('id').value.trim(),
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
  </script>
</body>
</html>
