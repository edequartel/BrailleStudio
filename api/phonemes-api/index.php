<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'developer', 'docent'], 'page');

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?phonemes-api$~', '', $scriptDir) ?? '';
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>API Inspector</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
        <img src="../../style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="../../style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
        </a>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="page-pretitle">API Inspector</div>
          <h1 class="page-title">list.php tester</h1>
          <div class="text-secondary mt-2">
            Deze pagina bouwt een request voor
            <code id="baseUrlHint">https://tastenbraille.com/api/list.php</code>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">Request parameters</h2>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="baseUrl">API endpoint</label>
                  <input id="baseUrl" class="form-control font-monospace" value="https://tastenbraille.com/api/list.php" placeholder="https://tastenbraille.com/api/list.php">
                </div>

                <div class="col-12 col-lg-6">
                  <label class="form-label" for="folder">folder</label>
                  <select id="folder" class="form-select">
                    <option value="speech">speech</option>
                    <option value="letters">letters</option>
                    <option value="instructions">instructions</option>
                    <option value="feedback">feedback</option>
                    <option value="story">story</option>
                    <option value="general">general</option>
                  </select>
                </div>

                <div class="col-12 col-md-6">
                  <label class="form-label" for="letters">letters</label>
                  <input id="letters" class="form-control font-monospace" placeholder="a,b,k,l">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="klanken">klanken</label>
                  <input id="klanken" class="form-control font-monospace" placeholder="a,e,i,ou,ei">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="onlyletters">onlyletters</label>
                  <input id="onlyletters" class="form-control font-monospace" placeholder="a,l,p,m">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="onlyklanken">onlyklanken</label>
                  <input id="onlyklanken" class="form-control font-monospace" placeholder="a,aa,ei,ou,l,m,p">
                </div>

                <div class="col-12 col-md-6">
                  <label class="form-label" for="onlycombo">onlycombo</label>
                  <select id="onlycombo" class="form-select">
                    <option value="">(leeg)</option>
                    <option value="true">true</option>
                    <option value="false">false</option>
                  </select>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="maxlength">maxlength</label>
                  <input id="maxlength" class="form-control font-monospace" type="number" min="0" placeholder="4">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="length">length</label>
                  <input id="length" class="form-control font-monospace" type="number" min="0" placeholder="3">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="limit">limit</label>
                  <input id="limit" class="form-control font-monospace" type="number" min="0" placeholder="10">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="randomlimit">randomlimit</label>
                  <input id="randomlimit" class="form-control font-monospace" type="number" min="0" placeholder="10">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="sort">sort</label>
                  <select id="sort" class="form-select">
                    <option value="">(leeg)</option>
                    <option value="asc">asc</option>
                    <option value="desc">desc</option>
                    <option value="random">random</option>
                  </select>
                </div>
              </div>

              <div class="btn-list mt-3">
                <button id="buildBtn" class="btn btn-outline-secondary" type="button">Bouw URL</button>
                <button id="testBtn" class="btn btn-primary" type="button">Test API</button>
                <button id="exampleBtn" class="btn btn-outline-secondary" type="button">Voorbeeld invullen</button>
                <button id="clearBtn" class="btn btn-outline-danger" type="button">Leegmaken</button>
              </div>
            </div>
          </div>

          <div class="row row-cards mt-3">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h2 class="card-title">Request URL</h2>
                </div>
                <div class="card-body">
                  <pre id="urlBox" class="form-control font-monospace mb-0"></pre>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-7">
              <div class="card h-100">
                <div class="card-header">
                  <h2 class="card-title">Response</h2>
                </div>
                <div class="card-body">
                  <pre id="responseBox" class="form-control font-monospace mb-0">Nog niets opgehaald.</pre>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-5">
              <div class="card h-100">
                <div class="card-header">
                  <h2 class="card-title">Audio items</h2>
                </div>
                <div class="card-body">
                  <ul id="listBox" class="list-group list-group-flush"></ul>
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
    const fields = [
      'folder', 'letters', 'klanken', 'onlyletters', 'onlyklanken',
      'onlycombo', 'maxlength', 'length', 'limit', 'randomlimit', 'sort'
    ];

    function getBaseUrl() {
      const value = document.getElementById('baseUrl').value.trim();
      return value || 'https://tastenbraille.com/api/list.php';
    }

    function getValue(id) {
      return document.getElementById(id).value.trim();
    }

    function buildUrl() {
      const baseUrl = getBaseUrl();
      const urlObj = new URL(baseUrl, window.location.href);
      const params = new URLSearchParams(urlObj.search);

      for (const field of fields) {
        const value = getValue(field);
        if (value !== '') {
          params.set(field, value);
        }
      }

      urlObj.search = params.toString();
      return urlObj.toString();
    }

    function canUseProxy(url) {
      try {
        const u = new URL(url);
        return u.protocol === 'https:' && u.host === 'tastenbraille.com';
      } catch {
        return false;
      }
    }

    async function fetchViaProxy(url) {
      const proxyUrl = `fetch-remote.php?url=${encodeURIComponent(url)}`;
      return fetch(proxyUrl);
    }

    function looksLikePhpSource(text) {
      const src = String(text || '').trim();
      return src.startsWith('<' + '?php') || src.includes('declare(strict_types=1);');
    }

    async function parseJsonResponse(res, usedProxy) {
      if (!res.ok) {
        const errorText = await res.text();
        return {
          ok: false,
          message: `HTTP ${res.status} ${res.statusText}\n\n${errorText || '(lege response body)'}`
        };
      }

      const contentType = (res.headers.get('content-type') || '').toLowerCase();
      const text = await res.text();

      if (usedProxy && looksLikePhpSource(text)) {
        return {
          ok: false,
          message:
            'De proxy retourneert PHP broncode in plaats van uitgevoerde JSON.\n\n' +
            'Deze pagina draait waarschijnlijk op een statische server zonder PHP.\n' +
            'Gebruik direct https://tastenbraille.com/api/list.php of start een PHP-webserver voor /api/.'
        };
      }

      if (!contentType.includes('application/json') && (text.includes('<!DOCTYPE html') || text.includes('<html'))) {
        return {
          ok: false,
          message:
            'HTML ontvangen in plaats van JSON.\n\n' +
            'Controleer of het endpoint klopt en of de server PHP uitvoert.'
        };
      }

      try {
        return { ok: true, data: JSON.parse(text) };
      } catch {
        return {
          ok: false,
          message: `Geen geldige JSON ontvangen:\n\n${text}`
        };
      }
    }

    function showUrl() {
      document.getElementById('baseUrlHint').textContent = getBaseUrl();
      document.getElementById('urlBox').textContent = buildUrl();
    }

    async function testApi() {
      const url = buildUrl();
      showUrl();

      const responseBox = document.getElementById('responseBox');
      const listBox = document.getElementById('listBox');
      responseBox.textContent = 'Bezig met ophalen...';
      listBox.innerHTML = '';

      try {
        let usedProxy = false;
        let parsed;

        try {
          const directRes = await fetch(url);
          parsed = await parseJsonResponse(directRes, false);
        } catch (directErr) {
          if (!canUseProxy(url)) {
            throw directErr;
          }
          const proxyRes = await fetchViaProxy(url);
          usedProxy = true;
          parsed = await parseJsonResponse(proxyRes, true);
        }

        if (!parsed.ok) {
          responseBox.textContent = parsed.message;
          return;
        }

        const data = parsed.data;
        responseBox.textContent = `${usedProxy ? '(via proxy)\n\n' : ''}${JSON.stringify(data, null, 2)}`;

        if (Array.isArray(data)) {
          for (const item of data) {
            const li = document.createElement('li');
            li.className = 'list-group-item px-0';
            const word = item.word ?? '(geen word)';
            const itemUrl = item.url ?? '';
            li.innerHTML = `<strong>${escapeHtml(word)}</strong>${itemUrl ? ` - <a href="${encodeURI(itemUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(itemUrl)}</a>` : ''}`;
            listBox.appendChild(li);
          }
        }
      } catch (err) {
        responseBox.textContent =
          `Fout bij ophalen:\n\n${err}\n\n` +
          'Mogelijke oorzaak: CORS/netwerk of je draait deze pagina via file://.\n' +
          'Gebruik bij voorkeur een lokale webserver (http://localhost/...) en endpoint "list.php".\n' +
          'Voor https://tastenbraille.com wordt automatisch fallback naar fetch-remote.php geprobeerd.';
      }
    }

    function fillExample() {
      document.getElementById('folder').value = 'speech';
      document.getElementById('baseUrl').value = 'https://tastenbraille.com/api/list.php';
      document.getElementById('letters').value = 'a,b,k,l';
      document.getElementById('klanken').value = 'a,e,i,ou,ei';
      document.getElementById('onlyletters').value = '';
      document.getElementById('onlyklanken').value = '';
      document.getElementById('onlycombo').value = '';
      document.getElementById('maxlength').value = '4';
      document.getElementById('length').value = '';
      document.getElementById('limit').value = '10';
      document.getElementById('randomlimit').value = '';
      document.getElementById('sort').value = 'asc';
      showUrl();
    }

    function clearForm() {
      document.getElementById('folder').value = 'speech';
      document.getElementById('baseUrl').value = 'https://tastenbraille.com/api/list.php';
      for (const field of fields) {
        if (field !== 'folder') document.getElementById(field).value = '';
      }
      document.getElementById('urlBox').textContent = '';
      document.getElementById('responseBox').textContent = 'Nog niets opgehaald.';
      document.getElementById('listBox').innerHTML = '';
    }

    function escapeHtml(str) {
      return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    }

    document.getElementById('buildBtn').addEventListener('click', showUrl);
    document.getElementById('testBtn').addEventListener('click', testApi);
    document.getElementById('exampleBtn').addEventListener('click', fillExample);
    document.getElementById('clearBtn').addEventListener('click', clearForm);
    document.getElementById('baseUrl').addEventListener('input', showUrl);

    showUrl();
  </script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
