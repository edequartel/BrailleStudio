<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/klanken$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="nl">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/braillestudio/apple-touch-icon.png">
  <link rel="manifest" href="/braillestudio/site.webmanifest">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Klanken Inspectie</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-music text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="page-pretitle">Aanvankelijklijst</div>
          <h1 class="page-title">Klanken Inspectie</h1>
          <div class="text-secondary mt-2">
            Overzicht van woorden, klanken, nieuwe klanken en categorie-indeling uit
            <code>aanvankelijklijst.json</code>.
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="row row-cards">
            <div class="col-12">
              <div id="stats" class="row row-cards"></div>
              <div id="meta" class="text-secondary mt-3">Bezig met laden...</div>
            </div>

            <div class="col-12 col-lg-3">
              <div class="card">
                <div class="card-header">
                  <h2 class="card-title">Filters</h2>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label" for="searchInput">Zoek op woord of klank</label>
                    <input id="searchInput" class="form-control" type="search" placeholder="bijv. bal, aa, medeklinkers">
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="categorySelect">Filter op categorie</label>
                    <select id="categorySelect" class="form-select">
                      <option value="">Alle categorieen</option>
                    </select>
                  </div>
                  <div>
                    <label class="form-label" for="newSoundSelect">Toon alleen woorden met nieuwe klanken</label>
                    <select id="newSoundSelect" class="form-select">
                      <option value="">Alles</option>
                      <option value="yes">Ja</option>
                      <option value="no">Nee</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="card mt-3">
                <div class="card-header">
                  <h2 class="card-title">Categorie Samenvatting</h2>
                </div>
                <div class="card-body">
                  <div id="categorySummary"></div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-9">
              <div class="card">
                <div class="card-header">
                  <h2 class="card-title">Woorden</h2>
                </div>
                <div class="table-responsive">
                  <table class="table table-vcenter card-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Woord</th>
                        <th>Klanken</th>
                        <th>Nieuwe Klanken</th>
                        <th>Bekende Klanken</th>
                        <th>Categorieen</th>
                      </tr>
                    </thead>
                    <tbody id="tableBody">
                      <tr><td class="text-secondary text-center py-4" colspan="6">Bezig met laden...</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script>
    const statsEl = document.getElementById("stats");
    const metaEl = document.getElementById("meta");
    const tableBodyEl = document.getElementById("tableBody");
    const categorySummaryEl = document.getElementById("categorySummary");
    const searchInputEl = document.getElementById("searchInput");
    const categorySelectEl = document.getElementById("categorySelect");
    const newSoundSelectEl = document.getElementById("newSoundSelect");

    let rows = [];
    let categoryNames = [];

    function escapeHtml(value) {
      return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
    }

    function flattenCategories(categoryMap) {
      if (!categoryMap || typeof categoryMap !== "object") return [];
      return Object.entries(categoryMap)
        .filter(([, items]) => Array.isArray(items) && items.length > 0)
        .map(([name, items]) => `${name}: ${items.join(", ")}`);
    }

    function makeStats(data) {
      const allSounds = new Set();
      const newSounds = new Set();
      const knownSounds = new Set();

      data.forEach((item) => {
        (item.sounds || []).forEach((sound) => allSounds.add(sound));
        (item.newSounds || []).forEach((sound) => newSounds.add(sound));
        (item.knownSounds || []).forEach((sound) => knownSounds.add(sound));
      });

      const stats = [
        { label: "Woorden", value: data.length },
        { label: "Unieke Klanken", value: allSounds.size },
        { label: "Nieuwe Klanken", value: newSounds.size },
        { label: "Bekende Klanken", value: knownSounds.size }
      ];

      statsEl.innerHTML = stats.map((stat) => `
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body">
              <div class="subheader">${escapeHtml(stat.label)}</div>
              <div class="h1 mb-0 text-primary">${escapeHtml(stat.value)}</div>
            </div>
          </div>
        </div>
      `).join("");
    }

    function makeCategorySummary(data) {
      const counts = new Map();

      data.forEach((item) => {
        Object.entries(item.categories || {}).forEach(([name, values]) => {
          counts.set(name, (counts.get(name) || 0) + (Array.isArray(values) ? values.length : 0));
        });
      });

      categoryNames = Array.from(counts.keys()).sort();
      categorySelectEl.innerHTML = [
        '<option value="">Alle categorieen</option>',
        ...categoryNames.map((name) => `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`)
      ].join("");

      categorySummaryEl.innerHTML = categoryNames.map((name) => `
        <span class="badge bg-secondary-lt me-1 mb-1">${escapeHtml(name)}: ${escapeHtml(counts.get(name))}</span>
      `).join("");
    }

    function matchesFilters(item) {
      const query = searchInputEl.value.trim().toLowerCase();
      const category = categorySelectEl.value;
      const onlyNew = newSoundSelectEl.value;

      if (query) {
        const haystack = [
          item.word,
          ...(item.sounds || []),
          ...(item.newSounds || []),
          ...(item.knownSounds || []),
          ...flattenCategories(item.categories || {})
        ].join(" ").toLowerCase();

        if (!haystack.includes(query)) return false;
      }

      if (category) {
        const categoryItems = item.categories && item.categories[category];
        if (!Array.isArray(categoryItems) || categoryItems.length === 0) return false;
      }

      if (onlyNew === "yes" && (!Array.isArray(item.newSounds) || item.newSounds.length === 0)) return false;
      if (onlyNew === "no" && Array.isArray(item.newSounds) && item.newSounds.length > 0) return false;

      return true;
    }

    function renderSoundBadges(items, tone = "secondary") {
      const list = Array.isArray(items) ? items : [];
      if (!list.length) return '<span class="text-secondary">-</span>';
      return list.map((item) => `<span class="badge bg-${tone}-lt me-1 mb-1">${escapeHtml(item)}</span>`).join("");
    }

    function renderTable() {
      const visibleRows = rows.filter(matchesFilters);

      if (visibleRows.length === 0) {
        tableBodyEl.innerHTML = '<tr><td class="text-secondary text-center py-4" colspan="6">Geen resultaten voor deze filters.</td></tr>';
        return;
      }

      tableBodyEl.innerHTML = visibleRows.map((item) => `
        <tr>
          <td>${escapeHtml(item.listIndex)}</td>
          <td><strong>${escapeHtml(item.word)}</strong></td>
          <td>${renderSoundBadges(item.sounds, "secondary")}</td>
          <td>${renderSoundBadges(item.newSounds, "primary")}</td>
          <td>${renderSoundBadges(item.knownSounds, "success")}</td>
          <td>${flattenCategories(item.categories).map(escapeHtml).join("<br>") || '<span class="text-secondary">-</span>'}</td>
        </tr>
      `).join("");
    }

    async function init() {
      try {
        const response = await fetch("./aanvankelijklijst.json", { cache: "no-store" });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        if (!Array.isArray(data)) throw new Error("JSON root is geen array");

        rows = data.map((item, index) => ({
          ...item,
          listIndex: index
        }));
        makeStats(rows);
        makeCategorySummary(rows);
        renderTable();
        metaEl.textContent = `${data.length} items geladen uit ./aanvankelijklijst.json`;
      } catch (error) {
        metaEl.textContent = "Laden mislukt.";
        tableBodyEl.innerHTML = `<tr><td class="text-danger text-center py-4" colspan="6">${escapeHtml(error.message)}</td></tr>`;
      }
    }

    searchInputEl.addEventListener("input", renderTable);
    categorySelectEl.addEventListener("change", renderTable);
    newSoundSelectEl.addEventListener("change", renderTable);

    init();
  </script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
