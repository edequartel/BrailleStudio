<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?session-api$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$sessionBase = $scriptDir;
$apiSessionBase = ($appBase === '' ? '' : $appBase) . '/api/session-api';

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$jsValue = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="nl">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#206bc4">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Brailleles">
  <link rel="manifest" href="<?= $htmlUrl($urlFor($apiSessionBase, 'manifest.webmanifest')) ?>">
  <link rel="icon" href="<?= $htmlUrl($urlFor($apiSessionBase, 'icon-start.svg')) ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= $htmlUrl($urlFor($apiSessionBase, 'icon-start.svg')) ?>">
  <title>Brailleles starten</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <div class="page-wrapper">
      <div class="container-tight py-4">
        <div class="card card-md">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <span class="avatar avatar-xl bg-primary-lt text-primary mb-3">
                <i class="ti ti-qrcode fs-1" aria-hidden="true"></i>
              </span>
              <h1 class="h1 mb-2">Start de brailleles</h1>
              <p class="text-secondary mb-0">Scan de code uit het boek of typ de code in.</p>
            </div>

            <div class="mb-4">
              <label class="form-label" for="stepLinkCodeInput">Boekcode</label>
              <div class="input-icon">
                <span class="input-icon-addon">
                  <i class="ti ti-barcode" aria-hidden="true"></i>
                </span>
                <input id="stepLinkCodeInput" class="form-control form-control-lg" type="text" placeholder="Scan of typ de code" autocomplete="off">
              </div>
              <div class="form-hint">Een volledige link plakken mag ook.</div>
            </div>

            <div id="cameraField" class="mb-4 d-none" hidden>
              <label class="form-label" for="scannerVideo">Camerabeeld</label>
              <div class="ratio ratio-4x3 bg-dark rounded overflow-hidden">
                <video id="scannerVideo" playsinline muted class="w-100 h-100 object-fit-cover d-none" hidden></video>
                <canvas id="scannerCanvas" class="d-none" hidden></canvas>
              </div>
            </div>

            <div class="row g-2">
              <div class="col-12">
                <button id="resolveBtn" class="btn btn-primary btn-lg w-100" type="button">
                  <i class="ti ti-player-play me-2" aria-hidden="true"></i>
                  <span>Start de les</span>
                </button>
              </div>
              <div class="col-12">
                <button id="startScanBtn" class="btn btn-outline-secondary btn-lg w-100" type="button">
                  <i class="ti ti-camera me-2" aria-hidden="true"></i>
                  <span>Camera gebruiken</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div id="statusCard" class="alert alert-info mt-3 mb-0" role="status" aria-live="polite">
          <div class="d-flex">
            <div>
              <i id="statusIcon" class="ti ti-info-circle me-2" aria-hidden="true"></i>
            </div>
            <div>
              <div class="fw-bold">Status</div>
              <p id="statusText" class="mb-0">Klaar om te starten.</p>
            </div>
          </div>
        </div>

        <pre id="payloadBox" class="d-none" aria-hidden="true">Nog geen lesgegevens ontvangen.</pre>
        <pre id="logBox" class="d-none" aria-hidden="true">Klaar.</pre>
      </div>

      <footer class="footer footer-transparent">
        <div class="container-tight">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <a class="navbar-brand navbar-brand-autodark m-0" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
              <span class="avatar avatar-sm bg-primary-lt me-2">
                <i class="ti ti-braille text-primary" aria-hidden="true"></i>
              </span>
              <span>BrailleStudio</span>
            </a>
            <img src="https://www.tastenbraille.com/braillestudio-data/assets/bartimeus.png" width="132" alt="Bartiméus">
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

  <script>
    const STORAGE_SESSION_KEY = 'braillestudio_session_api_active_session';
    const STORAGE_RESOLVED_KEY = 'braillestudio_session_api_last_resolved';
    const JOIN_URL = <?= $jsValue($urlFor($sessionBase, 'join.php')) ?>;
    const RESOLVE_URL = <?= $jsValue($urlFor($sessionBase, 'resolve.php')) ?>;

    const params = new URLSearchParams(location.search);
    const urlCode = String(params.get('code') || '').trim();
    const urlSessionId = String(params.get('sessionId') || '').trim();
    const $ = (id) => document.getElementById(id);
    let activeScannerStream = null;
    let scanTimer = null;
    let scanHandled = false;
    let startRequestInFlight = false;
    let joinRequestInFlight = false;

    function safeJsonParse(raw, fallback = null) {
      try {
        return JSON.parse(raw);
      } catch (err) {
        return fallback;
      }
    }

    function logLine(message, data) {
      const box = $('logBox');
      if (!box) return;
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      let line = `[${timestamp}] ${String(message || '').trim()}`;
      if (typeof data !== 'undefined') {
        try {
          line += `\n${JSON.stringify(data, null, 2)}`;
        } catch {
          line += `\n${String(data)}`;
        }
      }
      box.textContent = box.textContent.trim()
        ? `${box.textContent}\n${line}`
        : line;
      box.scrollTop = box.scrollHeight;
    }

    function getActiveSession() {
      return safeJsonParse(localStorage.getItem(STORAGE_SESSION_KEY) || '', null);
    }

    function getInitialSessionId() {
      if (urlSessionId) {
        return urlSessionId;
      }
      const session = getActiveSession();
      return String(session?.sessionId || '').trim();
    }

    function getSessionIdOrThrow() {
      const sessionId = getInitialSessionId();
      if (!sessionId) {
        throw new Error('Geen sessie gevonden in deze QR-link.');
      }
      return sessionId;
    }

    function normalizeStepLinkCode(rawValue) {
      const value = String(rawValue || '').trim();
      if (!value) return '';
      try {
        const url = new URL(value);
        const fromQuery = String(url.searchParams.get('code') || '').trim();
        if (fromQuery) return fromQuery;
        const parts = url.pathname.split('/').filter(Boolean);
        return String(parts[parts.length - 1] || '').trim();
      } catch {
        return value;
      }
    }

    async function postJson(url, payload) {
      logLine('POST request started.', { url, payload });
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload || {})
      });
      const data = await res.json().catch(() => ({}));
      logLine('POST response received.', {
        url,
        status: res.status,
        ok: Boolean(res.ok),
        data
      });
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }
      return data;
    }

    async function markSessionJoined() {
      const sessionId = getInitialSessionId();
      if (!sessionId || joinRequestInFlight) {
        return;
      }
      joinRequestInFlight = true;
      try {
        const response = await postJson(JOIN_URL, { sessionId });
        logLine('Session join registered.', response);
      } catch (err) {
        logLine('Session join could not be registered.', { message: err.message || String(err) });
      } finally {
        joinRequestInFlight = false;
      }
    }

    function setStatus(message, type = 'info') {
      const statusText = $('statusText');
      const statusCard = $('statusCard');
      const statusIcon = $('statusIcon');
      if (!statusText || !statusCard) {
        return;
      }
      const variants = {
        info: ['alert alert-info mt-3 mb-0', 'ti ti-info-circle me-2'],
        success: ['alert alert-success mt-3 mb-0', 'ti ti-circle-check me-2'],
        error: ['alert alert-danger mt-3 mb-0', 'ti ti-alert-circle me-2'],
        warning: ['alert alert-warning mt-3 mb-0', 'ti ti-alert-triangle me-2']
      };
      const [className, iconClass] = variants[type] || variants.info;
      statusCard.className = className;
      if (statusIcon) {
        statusIcon.className = iconClass;
      }
      statusText.textContent = String(message || '').trim() || 'Klaar om te starten.';
    }

    function setButtonLabel(button, iconClass, label) {
      if (!button) return;
      button.replaceChildren();
      const icon = document.createElement('i');
      icon.className = `${iconClass} me-2`;
      icon.setAttribute('aria-hidden', 'true');
      const text = document.createElement('span');
      text.textContent = label;
      button.append(icon, text);
    }

    function setBusyState(isBusy) {
      startRequestInFlight = Boolean(isBusy);
      $('resolveBtn').disabled = startRequestInFlight;
      $('startScanBtn').disabled = startRequestInFlight;
      $('stepLinkCodeInput').disabled = startRequestInFlight;
      setButtonLabel($('resolveBtn'), startRequestInFlight ? 'ti ti-loader-2' : 'ti ti-player-play', startRequestInFlight ? 'Starten...' : 'Start de les');
    }

    async function submitStepLink(source = 'manual') {
      if (startRequestInFlight) {
        logLine('Start request ignored because another request is in flight.', { source });
        return;
      }

      const sessionId = getSessionIdOrThrow();
      const stepLinkCode = normalizeStepLinkCode($('stepLinkCodeInput').value);
      if (!stepLinkCode) {
        throw new Error('Vul eerst een boekcode in.');
      }

      setBusyState(true);
      $('stepLinkCodeInput').value = stepLinkCode;
      stopScanner();
      logLine('Submitting step-link.', {
        source,
        sessionId,
        code: stepLinkCode
      });

      try {
        setStatus('De les wordt gestart...');
        const response = await postJson(RESOLVE_URL, {
          sessionId,
          code: stepLinkCode
        });

        if (response.accepted === false && response.ignored === true) {
          $('payloadBox').textContent = JSON.stringify(response, null, 2);
          setStatus('De laptop is bezig. Deze stap wordt overgeslagen.', 'success');
          logLine('Step-link ignored because laptop session is active.', response);
          return;
        }

        localStorage.setItem(STORAGE_RESOLVED_KEY, JSON.stringify(response));
        $('payloadBox').textContent = JSON.stringify(response, null, 2);
        setStatus(`Stap ${response.code} is gestart.`, 'success');
        logLine('Step-link started successfully.', response);
      } finally {
        setBusyState(false);
      }
    }

    async function handleScannedValue(rawValue) {
      const code = normalizeStepLinkCode(rawValue);
      if (!code || scanHandled || startRequestInFlight) {
        return;
      }
      scanHandled = true;
      $('stepLinkCodeInput').value = code;
      logLine('Camera scan detected step-link code.', { rawValue, code });
      try {
        await submitStepLink('scanner');
      } catch (err) {
        scanHandled = false;
        logLine('Automatic start after scan failed.', { message: err.message || String(err) });
        setStatus(err.message || String(err), 'error');
      }
    }

    function detectQrWithJsQr(video) {
      const canvas = $('scannerCanvas');
      if (!canvas || !video?.videoWidth || !video?.videoHeight || typeof window.jsQR !== 'function') {
        return '';
      }
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const context = canvas.getContext('2d', { willReadFrequently: true });
      if (!context) {
        return '';
      }
      context.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
      const result = window.jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'dontInvert'
      });
      return String(result?.data || '').trim();
    }

    async function scanFrame() {
      const video = $('scannerVideo');
      if (!video || video.readyState < 2) {
        return;
      }
      let value = '';
      if (typeof BarcodeDetector !== 'undefined') {
        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        const barcodes = await detector.detect(video);
        if (Array.isArray(barcodes) && barcodes.length) {
          value = String(barcodes[0]?.rawValue || '').trim();
        }
      }
      if (!value) {
        value = detectQrWithJsQr(video);
      }
      if (value) {
        await handleScannedValue(value);
      }
    }

    async function startScanner() {
      if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('Scannen met de camera werkt niet op dit apparaat of in deze browser.');
      }
      if (typeof BarcodeDetector === 'undefined' && typeof window.jsQR !== 'function') {
        throw new Error('QR-scannen is nu niet beschikbaar. Typ de code handmatig in.');
      }
      stopScanner();
      scanHandled = false;
      const stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: { ideal: 'environment' }
        },
        audio: false
      });
      activeScannerStream = stream;
      const video = $('scannerVideo');
      $('cameraField')?.classList.remove('d-none');
      $('cameraField').hidden = false;
      video.srcObject = stream;
      video.classList.remove('d-none');
      video.hidden = false;
      await video.play();
      setStatus('Camera staat aan. Richt op de QR-code in het boek.');
      setButtonLabel($('startScanBtn'), 'ti ti-camera-off', 'Camera stoppen');
      logLine('Camera scanner started.', {
        barcodeDetector: typeof BarcodeDetector !== 'undefined',
        jsQrFallback: typeof window.jsQR === 'function'
      });
      scanTimer = window.setInterval(() => {
        scanFrame().catch((err) => {
          logLine('Camera scan frame failed.', { message: err.message || String(err) });
        });
      }, 500);
    }

    function stopScanner() {
      if (scanTimer) {
        window.clearInterval(scanTimer);
        scanTimer = null;
      }
      if (activeScannerStream) {
        activeScannerStream.getTracks().forEach((track) => track.stop());
        activeScannerStream = null;
      }
      const video = $('scannerVideo');
      if (video) {
        video.pause();
        video.srcObject = null;
        video.classList.add('d-none');
        video.hidden = true;
      }
      if ($('cameraField')) {
        $('cameraField').classList.add('d-none');
        $('cameraField').hidden = true;
      }
      setButtonLabel($('startScanBtn'), 'ti ti-camera', 'Camera gebruiken');
    }

    function bootstrap() {
      $('stepLinkCodeInput').value = urlCode;
      setStatus(urlCode ? 'Code staat klaar. Tik op Start de les.' : 'Klaar om te starten.');
      logLine('Start page bootstrapped.', {
        urlCode,
        urlSessionId,
        initialSessionId: getInitialSessionId()
      });
      markSessionJoined();

      $('resolveBtn').addEventListener('click', async () => {
        try {
          await submitStepLink('button');
        } catch (err) {
          logLine('Start step-link failed.', { message: err.message || String(err) });
          setStatus(err.message || String(err), 'error');
        }
      });
      $('stepLinkCodeInput').addEventListener('keydown', async (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        try {
          await submitStepLink('enter');
        } catch (err) {
          logLine('Start step-link failed.', { message: err.message || String(err) });
          setStatus(err.message || String(err), 'error');
        }
      });
      $('startScanBtn').addEventListener('click', async () => {
        if (activeScannerStream) {
          stopScanner();
          setStatus('Camera gestopt. Je kunt de code typen of opnieuw scannen.');
          return;
        }
        try {
          await startScanner();
        } catch (err) {
          logLine('Camera scanner failed to start.', { message: err.message || String(err) });
          setStatus(err.message || String(err), 'error');
        }
      });
      window.addEventListener('beforeunload', stopScanner);
    }

    bootstrap();
  </script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
