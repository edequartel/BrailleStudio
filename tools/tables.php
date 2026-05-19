<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/tools$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Braille Tables</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2"><i class="ti ti-braille text-primary" aria-hidden="true"></i></span>
          <span>BrailleStudio</span>
        </a>
        <div class="navbar-nav flex-row ms-auto">
          <div class="nav-item">
            <div class="btn-list">
              <button class="btn btn-primary" id="refreshBtn" type="button">
                <i class="ti ti-refresh me-2" aria-hidden="true"></i>
                Refresh
              </button>
              <a class="btn btn-outline-secondary" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
                <i class="ti ti-home me-2" aria-hidden="true"></i>
                Home
              </a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-3 align-items-center">
            <div class="col">
              <div class="page-pretitle">BrailleStudio tools</div>
              <h1 class="page-title">Braille tables</h1>
              <div class="text-secondary mt-2">Browse and select tables exposed by the local BrailleBridge runtime.</div>
            </div>
            <div class="col-auto">
              <span class="badge bg-secondary-lt">http://localhost:5000</span>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="row row-cards">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <label class="form-label" for="search">Filter tables</label>
                  <input id="search" class="form-control font-monospace" type="text" placeholder="Filter by language, filename, description..." aria-label="Search tables">
                </div>
              </div>
            </div>

            <div class="col-12 col-md-4">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total tables</div>
                  <div class="h2 mb-0 font-monospace" id="totalCount">-</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Matching filter</div>
                  <div class="h2 mb-0 font-monospace" id="filteredCount">-</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Status</div>
                  <div class="h2 mb-0 font-monospace text-secondary" id="statusText">idle</div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div id="tableList" class="row row-cards"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>

  <script>
    (function () {
      const THEME_KEY = "bs_theme";
      function getInitialTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === "light" || saved === "dark") return saved;
        return window.matchMedia &&
          window.matchMedia("(prefers-color-scheme: dark)").matches
          ? "dark"
          : "light";
      }
      document.documentElement.setAttribute("data-theme", getInitialTheme());
      window.addEventListener("storage", (ev) => {
        if (ev.key !== THEME_KEY) return;
        if (ev.newValue === "light" || ev.newValue === "dark") {
          document.documentElement.setAttribute("data-theme", ev.newValue);
        }
      });

      const el = (id) => document.getElementById(id);
      const search = el("search");
      const refreshBtn = el("refreshBtn");
      const totalCount = el("totalCount");
      const filteredCount = el("filteredCount");
      const statusText = el("statusText");
      const tableList = el("tableList");

      let tables = [];
      const baseUrl = "http://localhost:5000";

      function setStatus(text, state) {
        statusText.textContent = text;
        statusText.className = "h2 mb-0 font-monospace";
        if (state === "ok") {
          statusText.classList.add("text-success");
        } else if (state === "err") {
          statusText.classList.add("text-danger");
        } else {
          statusText.classList.add("text-secondary");
        }
      }

      function chip(label) {
        const span = document.createElement("span");
        span.className = "badge bg-secondary-lt me-1 mb-1";
        span.textContent = label;
        return span;
      }

      function addIfValue(chips, label, value) {
        if (value === null || value === undefined || value === "") return;
        chips.appendChild(chip(`${label}: ${value}`));
      }

      function addIfArray(chips, label, arr) {
        if (!Array.isArray(arr) || arr.length === 0) return;
        chips.appendChild(chip(`${label}: ${arr.join(", ")}`));
      }

      function render() {
        const q = search.value.trim().toLowerCase();
        const filtered = tables.filter((t) => {
          if (!q) return true;
          const hay = [
            t.FileName,
            t.DisplayName,
            t.Language,
            t.Region,
            t.Grade,
            t.Description
          ].filter(Boolean).join(" ").toLowerCase();
          return hay.includes(q);
        });

        filteredCount.textContent = String(filtered.length);
        tableList.innerHTML = "";

        filtered.forEach((t) => {
          const col = document.createElement("div");
          col.className = "col-12 col-md-6 col-xl-4";

          const card = document.createElement("div");
          card.className = "card h-100";

          const body = document.createElement("div");
          body.className = "card-body";

          const name = document.createElement("h3");
          name.className = "card-title";
          name.textContent = t.DisplayName || t.FileName || "Unnamed table";

          const desc = document.createElement("div");
          desc.className = "text-secondary small mb-3";
          desc.textContent = t.Description || "No description";

          const chips = document.createElement("div");
          chips.className = "mb-3";
          addIfValue(chips, "Lang", t.Language);
          addIfValue(chips, "Region", t.Region);
          addIfValue(chips, "Grade", t.Grade);
          addIfArray(chips, "Type", t.Metadata && t.Metadata.type);
          addIfArray(chips, "Contract", t.Metadata && t.Metadata.contraction);
          addIfArray(chips, "System", t.Metadata && t.Metadata.system);
          addIfArray(chips, "Dots", t.Metadata && t.Metadata.dots);
          addIfArray(chips, "Dir", t.Metadata && t.Metadata.direction);
          addIfArray(chips, "Variant", t.Metadata && t.Metadata.variant);

          const actions = document.createElement("div");
          actions.className = "card-footer bg-transparent";
          const useBtn = document.createElement("button");
          useBtn.className = "btn btn-outline-primary btn-sm";
          useBtn.textContent = "Use table";
          useBtn.addEventListener("click", () => selectTable(t, card));
          actions.appendChild(useBtn);

          body.appendChild(name);
          body.appendChild(desc);
          body.appendChild(chips);
          card.appendChild(body);
          card.appendChild(actions);
          col.appendChild(card);
          tableList.appendChild(col);
        });
      }

      async function selectTable(table, cardEl) {
        if (!table || !table.FileName) return;
        const base = baseUrl.replace(/\/$/, "");
        setStatus(`setting ${table.FileName}...`, "");
        try {
          const resp = await fetch(base + "/brailletable", {
            method: "POST",
            headers: { "Content-Type": "text/plain; charset=utf-8" },
            body: table.FileName
          });
          const ok = resp.ok;
          setStatus(ok ? "table set" : `error ${resp.status}`, ok ? "ok" : "err");
          if (ok) {
            document.querySelectorAll(".card.border-primary").forEach((el) => {
              el.classList.remove("border-primary");
            });
            if (cardEl) cardEl.classList.add("border-primary");
          }
        } catch (err) {
          setStatus("error setting table", "err");
        }
      }

      async function loadTables() {
        const base = baseUrl.replace(/\/$/, "");
        setStatus("loading...", "");
        try {
          const resp = await fetch(base + "/tables");
          const data = await resp.json();
          if (!resp.ok || !data || data.ok !== true) {
            throw new Error("bad response");
          }
          tables = Array.isArray(data.tables) ? data.tables : [];
          totalCount.textContent = String(data.count ?? tables.length);
          setStatus("ok", "ok");
          render();
        } catch (err) {
          tables = [];
          totalCount.textContent = "-";
          filteredCount.textContent = "-";
          tableList.innerHTML = "";
          setStatus("error fetching tables", "err");
        }
      }

      search.addEventListener("input", render);
      refreshBtn.addEventListener("click", loadTables);

      loadTables();
    })();
  </script>
</body>
</html>
