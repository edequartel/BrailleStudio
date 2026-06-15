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
$jsValue = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$countriesPath = __DIR__ . '/countries.json';
$countriesData = [];
if (is_file($countriesPath)) {
    $decodedCountries = json_decode((string)file_get_contents($countriesPath), true);
    if (is_array($decodedCountries)) {
        $countriesData = $decodedCountries;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Braille Tables</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <style>
    .country-chip {
      max-width: 100%;
      min-width: 13rem;
    }

    .country-chip__flag {
      font-size: 1.25rem;
      line-height: 1;
    }

    .country-chip__title,
    .country-chip__meta {
      min-width: 0;
    }

    .country-chip__title {
      max-width: 18rem;
    }

    .country-chip__meta {
      max-width: 26rem;
    }

    .table-list-row {
      cursor: pointer;
    }

    .table-list-row:hover {
      background: var(--tblr-bg-surface-secondary);
    }

    .table-list-row.is-selected {
      background: var(--tblr-primary-lt);
      box-shadow: inset .25rem 0 0 var(--tblr-primary);
    }

    .table-row-title {
      min-width: 0;
    }

    .table-row-flags {
      flex: 0 0 auto;
      min-width: 2rem;
      text-align: left;
      white-space: nowrap;
    }

    .table-details {
      border-top: var(--tblr-border-width) solid var(--tblr-border-color);
      background: var(--tblr-bg-surface);
    }

    @media (max-width: 575.98px) {
      .country-chip {
        width: 100%;
      }

      .table-row-flags {
        min-width: 2rem;
      }
    }
  </style>
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
              <span id="bridgeBaseBadge" class="badge bg-secondary-lt">BrailleBridge local</span>
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
                  <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg">
                      <label class="form-label" for="search">Filter tables</label>
                      <input id="search" class="form-control" type="text" placeholder="Filter by language, country, filename, description..." aria-label="Search tables">
                    </div>
                    <div class="col-12 col-lg-4">
                      <label class="form-label" for="countryFilter">Country</label>
                      <select id="countryFilter" class="form-select" aria-label="Filter by country">
                        <option value="">All countries</option>
                      </select>
                    </div>
                    <div class="col-12 col-lg-auto">
                      <button id="useSelectedBtn" class="btn btn-primary w-100" type="button" disabled>
                        <i class="ti ti-check me-2" aria-hidden="true"></i>
                        Use table
                      </button>
                    </div>
                  </div>
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
              <div class="card">
                <div id="tableList" class="list-group list-group-flush"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>

  <script>
    (function () {
      const COUNTRIES_DATA = <?= $jsValue($countriesData) ?>;
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
      const countryFilter = el("countryFilter");
      const useSelectedBtn = el("useSelectedBtn");
      const refreshBtn = el("refreshBtn");
      const totalCount = el("totalCount");
      const filteredCount = el("filteredCount");
      const statusText = el("statusText");
      const tableList = el("tableList");
      const bridgeBaseBadge = el("bridgeBaseBadge");

      let tables = [];
      let countries = [];
      let countriesReady = false;
      let countryByCode = new Map();
      let countryByBcp47 = new Map();
      let countryByExactName = new Map();
      let countriesByLanguage = new Map();
      let languageDisplay = null;
      let scriptDisplay = null;
      let selectedTable = null;
      let selectedTableKey = "";
      let selectedTableElement = null;
      let expandedTableKeys = new Set();
      const bridgeBaseUrls = [
        "http://localhost:5000",
        "http://127.0.0.1:5000"
      ];
      let activeBridgeBaseUrl = bridgeBaseUrls[0];

      try {
        languageDisplay = new Intl.DisplayNames(["en"], { type: "language" });
      } catch {
        languageDisplay = null;
      }
      try {
        scriptDisplay = new Intl.DisplayNames(["en"], { type: "script" });
      } catch {
        scriptDisplay = null;
      }

      const LIBLOUIS_LANGUAGE_ALIASES = {
        af: "afr",
        ar: "ara",
        be: "bel",
        bg: "bul",
        cs: "ces",
        cy: "cym",
        da: "dan",
        de: "deu",
        el: "ell",
        en: "eng",
        es: "spa",
        et: "est",
        fi: "fin",
        fr: "fra",
        ga: "gle",
        he: "heb",
        hr: "hrv",
        hu: "hun",
        is: "isl",
        it: "ita",
        ja: "jpn",
        ko: "kor",
        lt: "lit",
        lv: "lav",
        mk: "mkd",
        nl: "nld",
        no: "nor",
        pl: "pol",
        pt: "por",
        ro: "ron",
        ru: "rus",
        sk: "slk",
        sl: "slv",
        sr: "srp",
        sv: "swe",
        sw: "swa",
        tr: "tur",
        uk: "ukr",
        vi: "vie",
        zh: "zho"
      };

      const LIBLOUIS_LANGUAGE_NAMES = {
        akk: "Akkadian",
        arc: "Aramaic",
        ba: "Bashkir",
        chr: "Cherokee",
        cop: "Coptic",
        elx: "Elamite",
        grc: "Ancient Greek",
        hbo: "Biblical Hebrew",
        hit: "Hittite",
        jpa: "Jewish Palestinian Aramaic",
        oar: "Old Aramaic",
        obm: "Moabite",
        peo: "Old Persian",
        sux: "Sumerian",
        syc: "Classical Syriac",
        syr: "Syriac",
        uga: "Ugaritic",
        xeb: "Eblaite",
        xhu: "Hurrian",
        xlu: "Luwian",
        xur: "Urartian"
      };

      function normalizeText(value) {
        return String(value ?? "")
          .trim()
          .toLowerCase()
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "");
      }

      function countryName(country) {
        return String(country?.name?.common || country?.cca2 || "").trim();
      }

      function normalizeLanguageCode(code) {
        const primary = normalizeText(code).split(/[-_]/)[0] || "";
        return LIBLOUIS_LANGUAGE_ALIASES[primary] || primary;
      }

      function languageNameForCode(code) {
        const text = String(code || "").trim();
        if (!text) return "";
        const parts = text.split(/[-_]/).filter(Boolean);
        const primary = parts[0] || text;
        const alias = normalizeLanguageCode(primary);
        const country = countries.find((item) => Object.prototype.hasOwnProperty.call(item.languages || {}, alias));
        const fromCountry = country?.languages?.[alias];
        let baseName = fromCountry ? String(fromCountry).trim() : "";
        if (!baseName && LIBLOUIS_LANGUAGE_NAMES[normalizeText(primary)]) {
          baseName = LIBLOUIS_LANGUAGE_NAMES[normalizeText(primary)];
        }
        try {
          const display = languageDisplay?.of(primary);
          if (!baseName && display && display !== primary) baseName = display;
        } catch {
          // Keep code fallback below.
        }

        const script = parts.find((part) => /^[A-Z][a-z]{3}$/.test(part));
        let scriptName = "";
        if (script) {
          try {
            scriptName = scriptDisplay?.of(script) || "";
          } catch {
            scriptName = "";
          }
        }
        return [baseName || text, scriptName && !String(baseName || text).includes(scriptName) ? scriptName : ""]
          .filter(Boolean)
          .join(" ");
      }

      function tableLanguageCodes(table) {
        const raw = [
          table.Language,
          table.Metadata?.language,
          table.Metadata?.lang
        ].flatMap((value) => Array.isArray(value) ? value : [value]);
        return Array.from(new Set(raw.map((value) => String(value || "").trim()).filter(Boolean)));
      }

      function tableLanguageNames(table) {
        return Array.from(new Set(tableLanguageCodes(table).map(languageNameForCode).filter(Boolean)));
      }

      function liblouisFileParts(table) {
        const base = String(table?.FileName || "").trim().replace(/\.[^.]+$/, "");
        return base.split(/[-_]/).map((part) => normalizeText(part)).filter(Boolean);
      }

      function findCountryByLanguageTag(tag) {
        const normalizedTag = normalizeText(tag).replace(/_/g, "-");
        if (countryByBcp47.has(normalizedTag)) return countryByBcp47.get(normalizedTag);
        const parts = normalizedTag.split(/[-_]/).filter(Boolean);
        if (parts.length < 2) return null;
        const language = parts[0];
        for (let index = 1; index < parts.length; index += 1) {
          const part = parts[index];
          if (part === language) continue;
          if (countryByCode.has(part)) return countryByCode.get(part);
        }
        return null;
      }

      function findCountryFromFileName(table) {
        const parts = liblouisFileParts(table);
        if (parts.length < 2) return null;
        const language = parts[0];
        const region = parts[1];
        if (region === language) return null;
        return countryByCode.get(region) || null;
      }

      function countryOfficialName(country) {
        return String(country?.name?.official || "").trim();
      }

      function nativeCountryName(country) {
        const names = Object.values(country?.name?.nativeName || {})
          .map((item) => String(item?.common || item?.official || "").trim())
          .filter(Boolean);
        return names.find((name) => name && name !== countryName(country)) || "";
      }

      function countryLanguages(country) {
        return Object.values(country?.languages || {})
          .map((language) => String(language || "").trim())
          .filter(Boolean);
      }

      function countryRegionText(country) {
        return [country?.region, country?.subregion]
          .map((value) => String(value || "").trim())
          .filter(Boolean)
          .join(" / ");
      }

      function countryOptionText(country, count) {
        const official = countryOfficialName(country);
        const code = country?.cca3 || country?.cca2 || "";
        return [
          `${country.flag || ""} ${countryName(country)}`,
          official && official !== countryName(country) ? official : "",
          code,
          `(${count})`
        ].filter(Boolean).join(" · ");
      }

      function countryTooltip(country) {
        return [
          countryOfficialName(country),
          nativeCountryName(country),
          countryRegionText(country),
          countryLanguages(country).join(", ")
        ].filter(Boolean).join("\n");
      }

      function countrySearchText(country) {
        const nativeNames = Object.values(country?.name?.nativeName || {})
          .flatMap((item) => [item?.common, item?.official])
          .filter(Boolean);
        const bcp47Tags = [
          ...(country?.bcp47 || []),
          ...(country?.bcp47Locales || []).map((locale) => locale?.code)
        ];
        return [
          country?.cca2,
          country?.cca3,
          countryName(country),
          country?.name?.official,
          country?.region,
          country?.subregion,
          ...bcp47Tags,
          ...Object.values(country?.languages || {}),
          ...nativeNames
        ].filter(Boolean).join(" ");
      }

      function buildCountryIndexes() {
        countryByCode = new Map();
        countryByBcp47 = new Map();
        countryByExactName = new Map();
        countriesByLanguage = new Map();
        countries.forEach((country) => {
          [country.cca2, country.cca3].filter(Boolean).forEach((code) => {
            countryByCode.set(normalizeText(code), country);
          });
          [
            ...(country.bcp47 || []),
            ...(country.bcp47Locales || []).map((locale) => locale?.code)
          ].forEach((tag) => {
            countryByBcp47.set(normalizeText(tag).replace(/_/g, "-"), country);
          });
          [
            countryName(country),
            country?.name?.official,
            ...Object.values(country?.name?.nativeName || {}).flatMap((item) => [item?.common, item?.official])
          ].filter(Boolean).forEach((name) => {
            countryByExactName.set(normalizeText(name), country);
          });
          Object.values(country.languages || {}).forEach((language) => {
            const key = normalizeText(language);
            if (!key) return;
            const list = countriesByLanguage.get(key) || [];
            list.push(country);
            countriesByLanguage.set(key, list);
          });
        });
      }

      function findCountryByText(value) {
        const needle = normalizeText(value).replace(/_/g, "-");
        if (!needle) return null;
        if (countryByBcp47.has(needle)) return countryByBcp47.get(needle);
        if (countryByCode.has(needle)) return countryByCode.get(needle);
        if (countryByExactName.has(needle)) return countryByExactName.get(needle);

        const localeParts = needle.split(/[-_]/).filter(Boolean);
        if (localeParts.length > 1) {
          const countryCode = localeParts[localeParts.length - 1];
          if (countryByCode.has(countryCode)) return countryByCode.get(countryCode);
        }

        return null;
      }

      function inferTableCountries(table) {
        const found = new Map();
        let hasSpecificCountry = false;
        const addCountry = (country) => {
          if (!country?.cca2) return;
          found.set(country.cca2, country);
        };

        tableLanguageCodes(table).forEach((tag) => {
          const country = findCountryByLanguageTag(tag);
          if (country) {
            hasSpecificCountry = true;
            addCountry(country);
          }
        });

        const fileCountry = findCountryFromFileName(table);
        if (fileCountry) {
          hasSpecificCountry = true;
          addCountry(fileCountry);
        }

        [
          table.Country,
          table.CountryCode,
          table.Region,
          table.Locale,
          table.Metadata?.country,
          table.Metadata?.countryCode,
          table.Metadata?.region,
          table.Metadata?.locale
        ].flatMap((value) => Array.isArray(value) ? value : [value]).forEach((value) => {
          const country = findCountryByText(value);
          if (country) {
            hasSpecificCountry = true;
            addCountry(country);
          }
        });

        if (!hasSpecificCountry) {
          tableLanguageCodes(table).forEach((code) => {
            const language = normalizeLanguageCode(code);
            (countriesByLanguage.get(language) || []).forEach(addCountry);
          });
        }

        return Array.from(found.values()).sort((a, b) => countryName(a).localeCompare(countryName(b)));
      }

      function enrichTables() {
        tables = tables.map((table) => ({
          ...table,
          _countries: inferTableCountries(table),
          _languageCodes: tableLanguageCodes(table),
          _languageNames: tableLanguageNames(table)
        }));
      }

      function tableDisplayName(table) {
        if (table.DisplayName) return table.DisplayName;
        const parts = [
          table._languageNames?.[0] || table.Language,
          table._countries?.[0] ? countryName(table._countries[0]) : "",
          table.Grade ? `grade ${table.Grade}` : "",
          table.Metadata?.type?.[0] && !table.Grade ? table.Metadata.type[0] : ""
        ].filter(Boolean);
        return parts.length ? parts.join(" ") : (table.FileName || "Unnamed table");
      }

      function tableKey(table) {
        return String(table?.FileName || tableDisplayName(table) || "").trim();
      }

      function tableFlags(table) {
        const flags = (table._countries || [])
          .map((country) => String(country.flag || "").trim())
          .filter(Boolean);
        return Array.from(new Set(flags)).slice(0, 3).join(" ");
      }

      function tableLiblouisLang(table) {
        return Array.from(new Set([
          table.Language,
          table.Metadata?.language
        ].flatMap((value) => Array.isArray(value) ? value : [value])
          .map((value) => String(value || "").trim())
          .filter(Boolean)))
          .slice(0, 4)
          .join(", ");
      }

      function renderCountryFilter() {
        const selected = countryFilter.value;
        const counts = new Map();
        tables.forEach((table) => {
          (table._countries || []).forEach((country) => {
            counts.set(country.cca2, (counts.get(country.cca2) || 0) + 1);
          });
        });

        const options = Array.from(counts.keys())
          .map((code) => countries.find((country) => country.cca2 === code))
          .filter(Boolean)
          .sort((a, b) => countryName(a).localeCompare(countryName(b)));

        countryFilter.innerHTML = '<option value="">All countries</option>';
        options.forEach((country) => {
          const option = document.createElement("option");
          option.value = country.cca2;
          option.textContent = countryOptionText(country, counts.get(country.cca2));
          countryFilter.appendChild(option);
        });
        countryFilter.value = counts.has(selected) ? selected : "";
      }

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

      async function fetchBridge(path, options = {}) {
        const bases = [
          activeBridgeBaseUrl,
          ...bridgeBaseUrls.filter((base) => base !== activeBridgeBaseUrl)
        ];
        let lastError = null;

        for (const baseUrl of bases) {
          const base = baseUrl.replace(/\/$/, "");
          try {
            const resp = await fetch(base + path, options);
            activeBridgeBaseUrl = baseUrl;
            bridgeBaseBadge.textContent = baseUrl;
            return resp;
          } catch (err) {
            lastError = err;
          }
        }

        throw lastError || new Error("BrailleBridge is niet bereikbaar.");
      }

      function normalizeTablesResponse(data) {
        if (Array.isArray(data)) {
          return {
            count: data.length,
            tables: data
          };
        }
        const items = Array.isArray(data?.tables)
          ? data.tables
          : (Array.isArray(data?.Tables) ? data.Tables : []);
        return {
          count: data?.count ?? data?.Count ?? items.length,
          tables: items
        };
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

      function countryBadge(country) {
        const wrapper = document.createElement("div");
        wrapper.className = "country-chip d-inline-flex align-items-start gap-2 border rounded px-2 py-1 bg-blue-lt text-blue me-2 mb-2";
        wrapper.title = countryTooltip(country);

        const flag = document.createElement("span");
        flag.className = "country-chip__flag";
        flag.textContent = country.flag || country.cca2 || "";

        const body = document.createElement("div");
        body.className = "min-w-0";

        const title = document.createElement("div");
        title.className = "country-chip__title text-truncate fw-medium";
        title.textContent = `${countryName(country)}${country.cca3 ? ` · ${country.cca3}` : ""}`;

        const nativeName = nativeCountryName(country);
        const official = countryOfficialName(country);
        const nameLine = nativeName || (official !== countryName(country) ? official : "");
        const meta = document.createElement("div");
        meta.className = "country-chip__meta text-truncate small text-secondary";
        meta.textContent = [
          nameLine,
          countryRegionText(country),
          countryLanguages(country).slice(0, 2).join(", ")
        ].filter(Boolean).join(" · ");

        body.appendChild(title);
        if (meta.textContent) body.appendChild(meta);
        wrapper.appendChild(flag);
        wrapper.appendChild(body);
        return wrapper;
      }

      function updateSelectedState(rowEl, table) {
        if (selectedTableElement && selectedTableElement !== rowEl) {
          selectedTableElement.classList.remove("is-selected");
        }
        selectedTable = table;
        selectedTableKey = tableKey(table);
        selectedTableElement = rowEl;
        if (rowEl) rowEl.classList.add("is-selected");
        useSelectedBtn.disabled = !selectedTable;
      }

      function restoreSelectedState(rowEl, table) {
        if (selectedTableKey && tableKey(table) === selectedTableKey) {
          selectedTable = table;
          selectedTableElement = rowEl;
          rowEl.classList.add("is-selected");
          useSelectedBtn.disabled = false;
        }
      }

      function renderTableDetails(table) {
        const details = document.createElement("div");
        details.className = "table-details px-3 py-3";

        const fileName = document.createElement("div");
        fileName.className = "text-secondary small font-monospace mb-2";
        fileName.textContent = table.FileName || "";

        const desc = document.createElement("div");
        desc.className = "text-secondary small mb-2";
        desc.textContent = table.Description || "No description";

        const countriesRow = document.createElement("div");
        countriesRow.className = "mb-2";
        (table._countries || []).slice(0, 6).forEach((country) => countriesRow.appendChild(countryBadge(country)));
        if ((table._countries || []).length > 6) {
          countriesRow.appendChild(chip(`+${table._countries.length - 6} more`));
        }

        const chips = document.createElement("div");
        chips.className = "mb-0";
        addIfValue(chips, "Lang", (table._languageNames || []).slice(0, 3).join(", ") || table.Language);
        addIfValue(chips, "Code", (table._languageCodes || []).slice(0, 4).join(", "));
        addIfValue(chips, "Region", table._countries?.[0] ? countryName(table._countries[0]) : table.Region);
        addIfValue(chips, "Grade", table.Grade);
        addIfArray(chips, "Type", table.Metadata && table.Metadata.type);
        addIfArray(chips, "Contract", table.Metadata && table.Metadata.contraction);
        addIfArray(chips, "System", table.Metadata && table.Metadata.system);
        addIfArray(chips, "Dots", table.Metadata && table.Metadata.dots);
        addIfArray(chips, "Dir", table.Metadata && table.Metadata.direction);
        addIfArray(chips, "Variant", table.Metadata && table.Metadata.variant);

        if (fileName.textContent) details.appendChild(fileName);
        details.appendChild(desc);
        if (countriesRow.childElementCount) details.appendChild(countriesRow);
        details.appendChild(chips);
        return details;
      }

      function render() {
        const q = normalizeText(search.value);
        const selectedCountry = countryFilter.value;
        const filtered = tables.filter((t) => {
          if (selectedCountry && !(t._countries || []).some((country) => country.cca2 === selectedCountry)) {
            return false;
          }
          if (!q) return true;
          const hay = normalizeText([
            t.FileName,
            t.DisplayName,
            t.Language,
            ...(t._languageCodes || []),
            ...(t._languageNames || []),
            t.Region,
            t.Grade,
            t.Description,
            ...(t._countries || []).map(countrySearchText)
          ].filter(Boolean).join(" "));
          return hay.includes(q);
        });

        filteredCount.textContent = String(filtered.length);
        tableList.innerHTML = "";

        if (filtered.length === 0) {
          const empty = document.createElement("div");
          empty.className = "empty";
          empty.innerHTML = '<div class="empty-icon"><i class="ti ti-search"></i></div><p class="empty-title">No matching tables</p><p class="empty-subtitle text-secondary">Adjust the search or country filter.</p>';
          tableList.appendChild(empty);
          return;
        }

        filtered.forEach((t) => {
          const key = tableKey(t);
          const isExpanded = expandedTableKeys.has(key);
          const item = document.createElement("div");
          item.className = "list-group-item p-0 table-list-row";
          item.addEventListener("click", (event) => {
            if (event.target.closest("button")) return;
            updateSelectedState(item, t);
          });
          item.addEventListener("dblclick", (event) => {
            if (event.target.closest("button")) return;
            updateSelectedState(item, t);
            selectTable(t, item);
          });

          const row = document.createElement("div");
          row.className = "d-flex align-items-center gap-2 px-3 py-2";

          const flags = document.createElement("div");
          flags.className = "table-row-flags fs-3";
          flags.textContent = tableFlags(t) || " ";

          const titleWrap = document.createElement("div");
          titleWrap.className = "table-row-title flex-fill";

          const name = document.createElement("div");
          name.className = "fw-medium text-truncate";
          name.textContent = tableDisplayName(t);

          const meta = document.createElement("div");
          meta.className = "text-secondary small text-truncate";
          meta.textContent = [
            `Lang: ${tableLiblouisLang(t) || "-"}`,
            t.Grade ? `grade ${t.Grade}` : "",
            t._countries?.[0] ? countryName(t._countries[0]) : ""
          ].filter(Boolean).join(" · ");
          titleWrap.appendChild(name);
          if (meta.textContent) titleWrap.appendChild(meta);

          const expandBtn = document.createElement("button");
          expandBtn.className = "btn btn-icon btn-outline-secondary";
          expandBtn.type = "button";
          expandBtn.setAttribute("aria-label", isExpanded ? "Collapse details" : "Expand details");
          expandBtn.setAttribute("aria-expanded", isExpanded ? "true" : "false");
          expandBtn.innerHTML = `<i class="ti ${isExpanded ? "ti-chevron-up" : "ti-chevron-down"}" aria-hidden="true"></i>`;
          expandBtn.addEventListener("click", (event) => {
            event.stopPropagation();
            if (expandedTableKeys.has(key)) {
              expandedTableKeys.delete(key);
            } else {
              expandedTableKeys.add(key);
            }
            render();
          });

          row.appendChild(flags);
          row.appendChild(titleWrap);
          row.appendChild(expandBtn);
          item.appendChild(row);
          if (isExpanded) item.appendChild(renderTableDetails(t));
          restoreSelectedState(item, t);
          tableList.appendChild(item);
        });
      }

      async function selectTable(table, cardEl) {
        if (!table || !table.FileName) return;
        setStatus(`setting ${table.FileName}...`, "");
        try {
          const resp = await fetchBridge("/brailletable", {
            method: "POST",
            headers: { "Content-Type": "text/plain; charset=utf-8" },
            body: table.FileName
          });
          const ok = resp.ok;
          setStatus(ok ? "table set" : `error ${resp.status}`, ok ? "ok" : "err");
          if (ok) {
            updateSelectedState(cardEl, table);
          }
        } catch (err) {
          setStatus("error setting table", "err");
        }
      }

      async function loadTables() {
        setStatus("loading...", "");
        try {
          const resp = await fetchBridge("/tables");
          const data = await resp.json();
          const normalized = normalizeTablesResponse(data);
          if (!resp.ok || !normalized.tables.length && Number(normalized.count || 0) > 0) {
            throw new Error("bad response");
          }
          if (data && data.ok === false) {
            throw new Error(data.error || "bad response");
          }
          tables = normalized.tables;
          if (countriesReady) {
            enrichTables();
            renderCountryFilter();
          }
          totalCount.textContent = String(normalized.count ?? tables.length);
          setStatus("ok", "ok");
          render();
        } catch (err) {
          tables = [];
          totalCount.textContent = "-";
          filteredCount.textContent = "-";
          tableList.innerHTML = "";
          setStatus("BrailleBridge niet bereikbaar", "err");
          bridgeBaseBadge.textContent = "localhost:5000 / 127.0.0.1:5000";
        }
      }

      async function loadCountries() {
        if (Array.isArray(COUNTRIES_DATA) && COUNTRIES_DATA.length) {
          countries = COUNTRIES_DATA;
          buildCountryIndexes();
          countriesReady = true;
          return;
        }

        try {
          const resp = await fetch("countries.json", { cache: "force-cache" });
          const data = await resp.json();
          countries = Array.isArray(data) ? data : [];
          buildCountryIndexes();
          countriesReady = true;
        } catch (err) {
          countries = [];
          countriesReady = false;
        }
      }

      search.addEventListener("input", render);
      countryFilter.addEventListener("change", render);
      refreshBtn.addEventListener("click", loadTables);
      useSelectedBtn.addEventListener("click", () => {
        if (!selectedTable) return;
        selectTable(selectedTable, selectedTableElement);
      });

      loadCountries().finally(loadTables);
    })();
  </script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
