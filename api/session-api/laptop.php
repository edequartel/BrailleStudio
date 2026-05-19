<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?session-api$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$sessionBase = $scriptDir;

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$jsValue = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BrailleStudio Lesstarter</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark pe-0 pe-md-3" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-braille text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>

      </div>
    </header>

    <div class="page-wrapper">
      <div class="container-xl">
        <div class="page-header d-print-none py-4">
          <div class="row g-3 align-items-center">
            <div class="col">
              <h1 id="pageTitle" class="page-title">Braille Sessie klaarzetten</h1>
              <div id="pageSubtitle" class="text-secondary mt-2 d-none" hidden></div>
            </div>
            <div class="col-auto">
              <div class="badges-list">
                <span id="sessionBadge" class="badge bg-secondary-lt">
                  <span class="status-dot status-dot-animated bg-secondary me-2"></span>
                  <span>Nog geen actieve les</span>
                </span>
                <span id="incomingStepBadge" class="badge bg-warning-lt text-warning d-none" hidden>
                  <span class="status-dot status-dot-animated bg-warning me-2"></span>
                  <span>Nog geen nieuwe stap</span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="page-body">
          <div id="sessionStageRow" class="row g-3 justify-content-center">
            <div id="sessionSetupColumn" class="col-12 col-md-8 col-lg-5">
              <div class="card mb-3">
                <div class="card-body text-center p-4 p-md-5">
                  <img id="qrImage" class="img-thumbnail d-none mx-auto mb-4" width="280" height="280" alt="QR-code voor deze les" hidden>
                  <button id="refreshQrBtn" class="btn btn-primary" type="button">
                    <i class="ti ti-qrcode me-2" aria-hidden="true"></i>
                    Sessie starten
                  </button>
                  <div id="resolveStatus" class="alert alert-info my-4 text-start">Klaar voor de les. Start eerst een sessie.</div>
                  <button id="showSessionInfoBtn" class="btn btn-outline-secondary btn-sm" type="button">Technische details</button>
                </div>
              </div>

              <div id="sessionInfoCard" class="card d-none" hidden>
                <div class="card-header">
                  <h2 class="card-title">Details</h2>
                </div>
                <div class="card-body">
                  <dl class="row mb-0">
                    <dt class="col-5">Sessie</dt><dd class="col-7 text-end text-break" id="sessionIdText">-</dd>
                    <dt class="col-5">Geldig tot</dt><dd class="col-7 text-end text-break" id="expiresAtText">-</dd>
                    <dt class="col-5">Status</dt><dd class="col-7 text-end text-break" id="sessionStateText">Wachten</dd>
                    <dt class="col-5">Code</dt><dd class="col-7 text-end text-break" id="resolvedCodeText">-</dd>
                    <dt class="col-5">Script</dt><dd class="col-7 text-end text-break" id="resolvedScriptIdText">-</dd>
                    <dt class="col-5">Stap</dt><dd class="col-7 text-end text-break" id="resolvedStepIdText">-</dd>
                  </dl>
                </div>
              </div>
            </div>

            <div id="playerCard" class="col-12 col-lg-8 d-none" hidden>
              <div class="card border-0 shadow-none bg-transparent">
                <div class="card-body p-0">
                  <iframe id="builderFrame" class="w-100 border-0" title="Brailleles" height="220"></iframe>
                </div>
              </div>
              <div id="stepInfoCard" class="card mt-3 d-none" hidden>
                <div class="card-body">
                  <div class="text-secondary small mb-1">Stap</div>
                  <h2 id="stepInfoTitle" class="h3 mb-3">-</h2>
                  <div id="stepInstructionBlock" class="mb-3 d-none" hidden>
                    <div class="text-secondary small mb-1">Instructie</div>
                    <div id="stepInstructionText"></div>
                  </div>
                  <div id="stepDescriptionBlock" class="d-none" hidden>
                    <div class="text-secondary small mb-1">Beschrijving</div>
                    <div id="stepDescriptionText"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <footer class="footer footer-transparent">
        <div class="container-xl text-center">
          <img src="<?= $htmlUrl($urlFor($appBase, 'assets/bartimeus.png')) ?>" width="132" alt="Bartiméus">
        </div>
      </footer>
    </div>
  </div>

  <div class="d-none" aria-hidden="true" hidden>
    <div id="payloadCardContent" class="d-none"></div>
    <pre id="metaBox">{}</pre>
    <pre id="stepInputsBox">{}</pre>
    <pre id="payloadBox">No resolved payload yet.</pre>
    <pre id="logBox">Ready.</pre>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script>
    const STORAGE_SESSION_KEY = 'braillestudio_session_api_active_session';
    const STORAGE_RESOLVED_KEY = 'braillestudio_session_api_last_resolved';
    const BLOCKLY_URL = <?= $jsValue($urlFor($appBase, 'blockly/index.php?embed=session-player&v=20260416-session-player-2')) ?>;
    const STATUS_URL = <?= $jsValue($urlFor($sessionBase, 'status.php')) ?>;
    const WAIT_URL = <?= $jsValue($urlFor($sessionBase, 'wait.php')) ?>;
    const START_URL = <?= $jsValue($urlFor($sessionBase, 'start-session.php')) ?>;
    const RUNTIME_STATE_URL = <?= $jsValue($urlFor($sessionBase, 'runtime-state.php')) ?>;
    const START_PAGE_URL = <?= $jsValue($urlFor($sessionBase, 'start.php')) ?>;
    const BLOCKLY_SCRIPT_LOAD_URL = <?= $jsValue($urlFor($appBase, 'blockly-api/load.php')) ?>;
    const QR_IMAGE_BASE_URL = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=';
    const STEP_START_NOTICE_AUDIO_URL = 'https://www.tastenbraille.com/braillestudio/sounds/ux/dahang.mp3';

    const $ = (id) => document.getElementById(id);
    let sessionPollTimer = null;
    let sessionJoinPollTimer = null;
    let sessionJoinPollInFlight = false;
    let sessionWaitLoopToken = 0;
    let lastJoinedAt = '';
    let lastResolvedSignature = '';
    let lastStartedStepKey = '';
    let lastStartedStepStillActive = false;
    let activeStepRunPromise = null;
    let activeStepCompletionWatchToken = 0;
    let blocklyFrameCustomized = false;
    let blocklyLogBridgeAttached = false;
    let blocklyMonitorSyncAttached = false;
    let blocklyStopBridgeAttached = false;
    let blocklyCustomizationTimer = null;
    let stepStartNoticeAudio = null;
    let audioUnlocked = false;

    function safeJsonParse(raw, fallback = null) {
      try {
        return JSON.parse(raw);
      } catch (err) {
        return fallback;
      }
    }

    function pretty(value) {
      return JSON.stringify(value ?? {}, null, 2);
    }

    function logLine(message, data) {
      const logBox = $('logBox');
      if (logBox) {
        const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
        let line = `[${timestamp}] ${String(message || '').trim()}`;
        if (typeof data !== 'undefined') {
          try {
            line += `\n${JSON.stringify(data, null, 2)}`;
          } catch {
            line += `\n${String(data)}`;
          }
        }
        logBox.textContent = logBox.textContent.trim()
          ? `${logBox.textContent}\n${line}`
          : line;
        logBox.scrollTop = logBox.scrollHeight;
      }
      try {
        const detail = typeof data === 'undefined'
          ? ''
          : ` ${JSON.stringify(data)}`;
        console.log(`[Braille Sessie] ${message}${detail}`);
      } catch {
        console.log(`[Braille Sessie] ${message}`);
      }
    }

    function setResolveStatus(message, type = 'info') {
      const status = $('resolveStatus');
      if (!status) return;
      const variants = {
        info: 'alert alert-info my-4 text-start',
        success: 'alert alert-success my-4 text-start',
        danger: 'alert alert-danger my-4 text-start',
        warning: 'alert alert-warning my-4 text-start'
      };
      status.className = variants[type] || variants.info;
      status.textContent = message || '';
    }

    function renderPlayerVisible(visible = false) {
      const card = $('playerCard');
      if (!card) return;
      card.hidden = !visible;
      card.classList.toggle('d-none', !visible);
    }

    function renderPageHeader(mode = 'setup') {
      const title = $('pageTitle');
      const subtitle = $('pageSubtitle');
      if (mode === 'lesson') {
        if (title) title.textContent = 'Brailleles';
        if (subtitle) {
          subtitle.textContent = '';
          subtitle.hidden = true;
          subtitle.classList.add('d-none');
        }
        return;
      }
      if (title) title.textContent = 'Braille Sessie klaarzetten';
      if (subtitle) {
        subtitle.textContent = '';
        subtitle.hidden = true;
        subtitle.classList.add('d-none');
      }
    }

    function renderSessionSetupVisible(visible = true) {
      const row = $('sessionStageRow');
      const setup = $('sessionSetupColumn');
      const player = $('playerCard');
      if (row) {
        row.classList.toggle('justify-content-center', visible);
      }
      if (setup) {
        setup.hidden = !visible;
        setup.classList.toggle('d-none', !visible);
      }
      if (player) {
        player.classList.toggle('col-lg-8', visible);
        player.classList.toggle('col-lg-12', !visible);
      }
    }

    function renderIncomingStepBadge({ visible = false, text = '' } = {}) {
      const badge = $('incomingStepBadge');
      if (!badge) return;
      badge.hidden = !visible;
      badge.classList.toggle('d-none', !visible);
      const label = badge.querySelector('span:last-child');
      if (label) {
        label.textContent = text || 'Nieuwe stap ontvangen';
      }
    }

    function getActiveSession() {
      return safeJsonParse(localStorage.getItem(STORAGE_SESSION_KEY) || '', null);
    }

    function setActiveSession(session) {
      localStorage.setItem(STORAGE_SESSION_KEY, JSON.stringify(session));
    }

    function clearActiveSession() {
      localStorage.removeItem(STORAGE_SESSION_KEY);
    }

    function getLastResolved() {
      return safeJsonParse(localStorage.getItem(STORAGE_RESOLVED_KEY) || '', null);
    }

    function setLastResolved(payload) {
      localStorage.setItem(STORAGE_RESOLVED_KEY, JSON.stringify(payload));
    }

    function buildSessionStartUrl(sessionId) {
      const url = new URL(START_PAGE_URL, window.location.href);
      if (sessionId) {
        url.searchParams.set('sessionId', sessionId);
      }
      return url.toString();
    }

    async function waitForBlocklyApp(timeoutMs = 15000) {
      const frame = $('builderFrame');
      const startedAt = Date.now();
      while (Date.now() - startedAt < timeoutMs) {
        try {
          const app = frame?.contentWindow?.BrailleBlocklyApp;
          if (app && typeof app.applyResolvedSessionPayload === 'function') {
            attachBlocklyLogBridge(app);
            return app;
          }
        } catch (err) {}
        await new Promise((resolve) => setTimeout(resolve, 250));
      }
      throw new Error('Blockly iframe did not become ready in time');
    }

    function attachBlocklyLogBridge(app) {
      if (!app || typeof app.setLogHandler !== 'function' || blocklyLogBridgeAttached) {
        return;
      }
      app.setLogHandler((line) => {
        logLine('Blockly', { line: String(line || '') });
        syncBlocklyFrameHeight();
      });
      blocklyLogBridgeAttached = true;
      logLine('Blockly log bridge attached.');
    }

    function customizeBlocklyFrameUi() {
      const frame = $('builderFrame');
      try {
        const doc = frame?.contentWindow?.document;
        if (!doc || !doc.body) {
          return false;
        }
        frame.style.background = 'transparent';
        frame.style.display = 'block';
        const setFrameStyle = (element, property, value) => {
          if (element?.style?.setProperty) {
            element.style.setProperty(property, value, 'important');
          }
        };
        const appRoot = doc.getElementById('app');
        setFrameStyle(doc.documentElement, 'height', 'auto');
        setFrameStyle(doc.documentElement, 'min-height', '0');
        setFrameStyle(doc.documentElement, 'background', 'transparent');
        setFrameStyle(doc.body, 'height', 'auto');
        setFrameStyle(doc.body, 'min-height', '0');
        setFrameStyle(doc.body, 'overflow', 'hidden');
        setFrameStyle(doc.body, 'background', 'transparent');
        if (appRoot) {
          setFrameStyle(appRoot, 'height', 'auto');
          setFrameStyle(appRoot, 'min-height', '0');
          setFrameStyle(appRoot, 'background', 'transparent');
        }
        [
          '.topbar-row--main',
          '.topbar-row--scripts'
        ].forEach((selector) => {
          const element = doc.querySelector(selector);
          if (element) {
            element.setAttribute('hidden', '');
          }
        });
        [
          'main',
          'mainDivider',
          'sidebar',
          'gridSnapBtn',
          'monitorToggleBtn',
          'sidebarToggleBtn'
        ].forEach((id) => {
          const element = doc.getElementById(id);
          if (element) {
            element.setAttribute('hidden', '');
          }
        });
        const simRow = doc.querySelector('.topbar-row--sim');
        const topbar = doc.getElementById('topbar');
        const scriptMonitorRow = doc.getElementById('scriptBrailleMonitorRow');
        const runBtn = doc.getElementById('runBtn');
        const stopBtn = doc.getElementById('stopBtn');
        const leftThumbBtn = doc.getElementById('simThumbLeftBtn');
        const leftMiddleBtn = doc.getElementById('simCursor5Btn');
        const rightMiddleBtn = doc.getElementById('simChord1Btn');
        const rightThumbBtn = doc.getElementById('simThumbRightBtn');
        if (topbar) {
          setFrameStyle(topbar, 'border-bottom', '0');
          setFrameStyle(topbar, 'box-shadow', 'none');
          setFrameStyle(topbar, 'background', 'transparent');
          setFrameStyle(topbar, 'padding', '0');
        }
        if (simRow) {
          simRow.classList.remove('justify-content-center');
          simRow.style.display = 'grid';
          simRow.style.gridTemplateColumns = 'minmax(0, 1fr) auto minmax(0, 1fr)';
          simRow.style.alignItems = 'center';
          simRow.style.justifyContent = 'stretch';
          simRow.style.gap = '8px';
          simRow.style.paddingLeft = '0';
          const spacer = simRow.querySelector('.spacer');
          if (spacer) {
            spacer.setAttribute('hidden', '');
          }
          const runStopGroup = doc.getElementById('sessionRunStopGroup') || doc.createElement('div');
          const thumbGroup = doc.getElementById('sessionThumbGroup') || doc.createElement('div');
          const rightBalance = doc.getElementById('sessionToolbarRightBalance') || doc.createElement('div');
          runStopGroup.id = 'sessionRunStopGroup';
          thumbGroup.id = 'sessionThumbGroup';
          rightBalance.id = 'sessionToolbarRightBalance';
          runStopGroup.className = 'd-flex align-items-center gap-2';
          thumbGroup.className = 'd-flex align-items-center gap-2';
          rightBalance.className = 'd-flex';
          runStopGroup.style.justifyContent = 'flex-end';
          thumbGroup.style.justifyContent = 'center';
          rightBalance.style.minWidth = '0';
          [runBtn, stopBtn].forEach((button) => {
            if (button) {
              button.hidden = false;
              button.removeAttribute('hidden');
              runStopGroup.appendChild(button);
            }
          });
          [leftThumbBtn, leftMiddleBtn, rightMiddleBtn, rightThumbBtn].forEach((button) => {
            if (button) {
              button.hidden = false;
              button.removeAttribute('hidden');
              thumbGroup.appendChild(button);
            }
          });
          simRow.replaceChildren(rightBalance, thumbGroup, runStopGroup);
          if (topbar && scriptMonitorRow && simRow.previousElementSibling !== scriptMonitorRow) {
            scriptMonitorRow.insertAdjacentElement('afterend', simRow);
          }
        }
        attachBlocklyStopBridge(stopBtn);
        if (leftMiddleBtn) {
          leftMiddleBtn.setAttribute('aria-label', 'Linker middelduim');
          leftMiddleBtn.setAttribute('title', 'Linker middelduim');
        }
        if (rightMiddleBtn) {
          rightMiddleBtn.setAttribute('aria-label', 'Rechter middelduim');
          rightMiddleBtn.setAttribute('title', 'Rechter middelduim');
        }
        if (leftThumbBtn) {
          leftThumbBtn.setAttribute('aria-label', 'Linker duim');
          leftThumbBtn.setAttribute('title', 'Linker duim');
        }
        if (rightThumbBtn) {
          rightThumbBtn.setAttribute('aria-label', 'Rechter duim');
          rightThumbBtn.setAttribute('title', 'Rechter duim');
        }
        doc.body.dataset.sessionLaptopPlayerReady = '1';
        attachBlocklyMonitorSync();
        syncActiveBlocklyMonitor();
        syncBlocklyFrameHeight();
        blocklyFrameCustomized = true;
        return true;
      } catch (err) {
        return false;
      }
    }

    function scheduleBlocklyFrameCustomization(durationMs = 120000) {
      if (blocklyCustomizationTimer) {
        window.clearTimeout(blocklyCustomizationTimer);
        blocklyCustomizationTimer = null;
      }
      const startedAt = Date.now();
      const poll = () => {
        const app = $('builderFrame')?.contentWindow?.BrailleBlocklyApp;
        if (app) {
          attachBlocklyLogBridge(app);
        }
        customizeBlocklyFrameUi();
        if (Date.now() - startedAt < durationMs) {
          blocklyCustomizationTimer = window.setTimeout(poll, 250);
        } else {
          blocklyCustomizationTimer = null;
        }
      };
      poll();
    }

    function getBlocklyWsConnected() {
      try {
        const app = $('builderFrame')?.contentWindow?.BrailleBlocklyApp;
        const snapshot = typeof app?.getRuntimeSnapshot === 'function' ? app.getRuntimeSnapshot() : null;
        if (snapshot && typeof snapshot.wsConnected !== 'undefined') {
          return Boolean(snapshot.wsConnected);
        }
      } catch (err) {}
      try {
        const doc = $('builderFrame')?.contentWindow?.document;
        return Boolean(doc?.getElementById('bridgeLaunchIndicator')?.classList.contains('is-connected'));
      } catch (err) {
        return false;
      }
    }

    function setMonitorRowVisible(row, visible) {
      if (!row) return;
      row.hidden = !visible;
      row.classList.toggle('is-hidden', !visible);
    }

    function syncActiveBlocklyMonitor() {
      const frame = $('builderFrame');
      try {
        const doc = frame?.contentWindow?.document;
        const bridgeRow = doc?.getElementById('brailleMonitorRow');
        const scriptRow = doc?.getElementById('scriptBrailleMonitorRow');
        if (!bridgeRow && !scriptRow) {
          return false;
        }
        const useBridgeMonitor = getBlocklyWsConnected();
        setMonitorRowVisible(bridgeRow, useBridgeMonitor);
        setMonitorRowVisible(scriptRow, !useBridgeMonitor);
        syncBlocklyFrameHeight();
        return true;
      } catch (err) {
        return false;
      }
    }

    function attachBlocklyMonitorSync() {
      if (blocklyMonitorSyncAttached) {
        return;
      }
      const frame = $('builderFrame');
      try {
        const doc = frame?.contentWindow?.document;
        if (!doc?.body || typeof frame.contentWindow.MutationObserver !== 'function') {
          return;
        }
        const observer = new frame.contentWindow.MutationObserver(() => {
          syncActiveBlocklyMonitor();
        });
        ['brailleMonitorRow', 'scriptBrailleMonitorRow', 'bridgeLaunchIndicator'].forEach((id) => {
          const node = doc.getElementById(id);
          if (node) {
            observer.observe(node, { attributes: true, attributeFilter: ['class', 'style'] });
          }
        });
        frame.contentWindow.setInterval(syncActiveBlocklyMonitor, 500);
        blocklyMonitorSyncAttached = true;
      } catch (err) {}
    }

    function attachBlocklyStopBridge(stopBtn) {
      if (!stopBtn || blocklyStopBridgeAttached) {
        return;
      }
      stopBtn.addEventListener('click', () => {
        window.setTimeout(() => {
          releaseActiveStepAfterStop().catch((err) => {
            logLine('Could not release session after stop button.', { message: err?.message || String(err) });
          });
        }, 100);
      });
      blocklyStopBridgeAttached = true;
    }

    function syncBlocklyFrameHeight() {
      const frame = $('builderFrame');
      try {
        const doc = frame?.contentWindow?.document;
        const topbar = doc?.getElementById('topbar');
        if (!frame || !topbar) {
          return false;
        }
        const nextHeight = Math.max(96, Math.ceil(topbar.scrollHeight));
        frame.setAttribute('height', String(nextHeight));
        return true;
      } catch (err) {
        return false;
      }
    }

    async function applyResolvedToBlockly(payload, { autoOpen = true } = {}) {
      if (!payload || typeof payload !== 'object') {
        throw new Error('No resolved payload available');
      }
      const frame = $('builderFrame');
      if (autoOpen) {
        renderPlayerVisible(true);
      }
      if (autoOpen && !frame.src) {
        openBlockly();
      }
      const app = await waitForBlocklyApp();
      customizeBlocklyFrameUi();
      syncBlocklyFrameHeight();
      const applyResult = await app.applyResolvedSessionPayload(payload, { autoRun: true, force: true });
      let finalSnapshot = typeof app.getRuntimeSnapshot === 'function'
        ? app.getRuntimeSnapshot()
        : null;
      logLine('Blockly runtime snapshot after auto-start.', finalSnapshot);

      const runtimeStillActive = Boolean(
        finalSnapshot &&
        (finalSnapshot.isActive || finalSnapshot.hasActiveAudio || Number(finalSnapshot.activeTimers || 0) > 0)
      );

      return {
        ...applyResult,
        runtimeSnapshot: finalSnapshot,
        runtimeStillActive
      };
    }

    function snapshotShowsActive(snapshot) {
      return Boolean(
        snapshot &&
        (snapshot.isActive || snapshot.hasActiveAudio || Number(snapshot.activeTimers || 0) > 0)
      );
    }

    function renderSession(session = null) {
      const badge = $('sessionBadge');
      const active = session && session.sessionId;
      const joined = active && String(session?.joinedAt || '').trim();
      if (badge) {
        badge.className = active ? 'badge bg-success-lt text-success' : 'badge bg-secondary-lt';
        const dot = badge.querySelector('.status-dot');
        if (dot) {
          dot.className = `status-dot status-dot-animated ${active ? 'bg-success' : 'bg-secondary'} me-2`;
        }
        const label = badge.querySelector('span:last-child');
        if (label) {
          label.textContent = active ? `Actieve les: ${session.sessionId}` : 'Nog geen actieve les';
        }
      }
      $('sessionIdText').textContent = session?.sessionId || '-';
      $('expiresAtText').textContent = session?.expiresAt || '-';
      $('sessionStateText').textContent = joined ? 'Telefoon verbonden' : (active ? 'Wachten op telefoon' : 'Wachten');
      renderQr(session);
    }

    function renderResolved(payload = null) {
      $('resolvedCodeText').textContent = payload?.code || '-';
      $('resolvedScriptIdText').textContent = payload?.scriptId || '-';
      $('resolvedStepIdText').textContent = payload?.stepId || '-';
      $('metaBox').textContent = pretty(payload?.meta || {});
      $('stepInputsBox').textContent = pretty(payload?.stepInputs || {});
      $('payloadBox').textContent = payload ? pretty(payload) : 'No resolved payload yet.';
      renderStepInfo(payload);
    }

    function setOptionalStepText(blockId, textId, value) {
      const block = $(blockId);
      const target = $(textId);
      const text = String(value || '').trim();
      if (!block || !target) return;
      target.textContent = text;
      block.hidden = !text;
      block.classList.toggle('d-none', !text);
    }

    function renderStepInfo(payload = null) {
      const card = $('stepInfoCard');
      const title = $('stepInfoTitle');
      if (!card || !title) return;
      if (!payload || typeof payload !== 'object') {
        card.hidden = true;
        card.classList.add('d-none');
        title.textContent = '-';
        setOptionalStepText('stepInstructionBlock', 'stepInstructionText', '');
        setOptionalStepText('stepDescriptionBlock', 'stepDescriptionText', '');
        return;
      }
      const meta = payload.meta && typeof payload.meta === 'object' ? payload.meta : {};
      const stepTitle = String(
        meta.title
        || meta.scriptTitle
        || payload.code
        || payload.stepId
        || 'Stap'
      ).trim();
      title.textContent = stepTitle || 'Stap';
      setOptionalStepText('stepInstructionBlock', 'stepInstructionText', meta.instruction || '');
      setOptionalStepText('stepDescriptionBlock', 'stepDescriptionText', meta.description || '');
      card.hidden = false;
      card.classList.remove('d-none');
    }

    async function fetchBlocklyScriptMeta(scriptId) {
      const id = String(scriptId || '').trim();
      if (!id) return null;
      const url = new URL(BLOCKLY_SCRIPT_LOAD_URL, window.location.href);
      url.searchParams.set('id', id);
      url.searchParams.set('_', String(Date.now()));
      const res = await fetch(url.toString(), { method: 'GET', cache: 'no-store' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data || typeof data !== 'object') {
        return null;
      }
      const meta = data.meta && typeof data.meta === 'object' ? data.meta : {};
      return {
        title: String(meta.title || data.title || id).trim(),
        description: String(meta.description || '').trim(),
        instruction: String(meta.instruction || '').trim()
      };
    }

    async function enrichStepInfoFromScript(payload) {
      if (!payload || typeof payload !== 'object') return;
      const existingMeta = payload.meta && typeof payload.meta === 'object' ? payload.meta : {};
      if (String(existingMeta.instruction || '').trim() && String(existingMeta.description || '').trim()) {
        return;
      }
      try {
        const scriptMeta = await fetchBlocklyScriptMeta(payload.scriptId);
        if (!scriptMeta) return;
        renderStepInfo({
          ...payload,
          meta: {
            ...scriptMeta,
            ...existingMeta,
            instruction: String(existingMeta.instruction || scriptMeta.instruction || '').trim(),
            description: String(existingMeta.description || scriptMeta.description || '').trim()
          }
        });
      } catch (err) {
        logLine('Could not enrich step info from script metadata.', {
          scriptId: payload.scriptId || '',
          message: err?.message || String(err)
        });
      }
    }

    function renderQr(session = null) {
      const sessionId = String(session?.sessionId || '').trim();
      const link = sessionId ? buildSessionStartUrl(sessionId) : START_PAGE_URL;
      const qrImage = $('qrImage');
      const refreshQrBtn = $('refreshQrBtn');
      qrImage.src = sessionId ? `${QR_IMAGE_BASE_URL}${encodeURIComponent(link)}&t=${Date.now()}` : '';
      qrImage.hidden = !sessionId;
      qrImage.classList.toggle('d-none', !sessionId);
      if (refreshQrBtn) {
        refreshQrBtn.hidden = Boolean(sessionId);
        refreshQrBtn.classList.toggle('d-none', Boolean(sessionId));
      }
    }

    async function postJson(url, payload) {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload || {})
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }
      return data;
    }

    async function playStepStartNotice() {
      if (!stepStartNoticeAudio) {
        stepStartNoticeAudio = new Audio(STEP_START_NOTICE_AUDIO_URL);
        stepStartNoticeAudio.preload = 'auto';
      }
      const audio = stepStartNoticeAudio;
      try {
        audio.pause();
      } catch {}
      try {
        audio.currentTime = 0;
      } catch {}
      await new Promise((resolve, reject) => {
        let settled = false;
        const finish = () => {
          if (settled) return;
          settled = true;
          audio.removeEventListener('ended', onEnded);
          audio.removeEventListener('error', onError);
          resolve();
        };
        const fail = () => {
          if (settled) return;
          settled = true;
          audio.removeEventListener('ended', onEnded);
          audio.removeEventListener('error', onError);
          reject(new Error('Could not play step start notification.'));
        };
        const onEnded = () => finish();
        const onError = () => fail();
        audio.addEventListener('ended', onEnded, { once: true });
        audio.addEventListener('error', onError, { once: true });
        audio.play()
          .then(() => {
            logLine('Notification sound playback started.', { url: STEP_START_NOTICE_AUDIO_URL });
            if (!Number.isFinite(audio.duration) || audio.duration === 0) {
              window.setTimeout(finish, 1500);
            }
          })
          .catch(() => fail());
      });
    }

    async function unlockAudioPlayback() {
      if (!stepStartNoticeAudio) {
        stepStartNoticeAudio = new Audio(STEP_START_NOTICE_AUDIO_URL);
        stepStartNoticeAudio.preload = 'auto';
      }
      const audio = stepStartNoticeAudio;
      audio.muted = true;
      try {
        audio.currentTime = 0;
      } catch {}
      await audio.play();
      audio.pause();
      try {
        audio.currentTime = 0;
      } catch {}
      audio.muted = false;
      audioUnlocked = true;
      logLine('Audio playback unlocked.');
    }

    function resetSessionRuntimeState() {
      stopSessionJoinPolling();
      if (getActiveSession()?.sessionId) {
        clearActiveSession();
      }
      localStorage.removeItem(STORAGE_RESOLVED_KEY);
      lastJoinedAt = '';
      lastResolvedSignature = '';
      lastStartedStepKey = '';
      lastStartedStepStillActive = false;
      activeStepRunPromise = null;
      renderIncomingStepBadge({ visible: false });
      renderPageHeader('setup');
      renderSessionSetupVisible(true);
      renderPlayerVisible(false);
      renderResolved(null);
      renderSession(null);
    }

    async function startSession() {
      const response = await postJson(START_URL, {});

      const session = {
        sessionId: response.sessionId,
        expiresAt: response.expiresAt || '',
        joinedAt: ''
      };
      setActiveSession(session);
      renderSession(session);
      setResolveStatus('Sessiecode is klaar. Scan daarna de step-link code in het boek.', 'success');
      startSessionPolling();
      startSessionJoinPolling();
    }

    async function startFreshSession({ unlockAudio = false } = {}) {
      stopSessionPolling();
      resetSessionRuntimeState();
      if (unlockAudio) {
        try {
          await unlockAudioPlayback();
        } catch (err) {
          logLine('Audio unlock failed during new QR action.', { message: err?.message || String(err) });
        }
      }
      await startSession();
      if (unlockAudio) {
        $('sessionStateText').textContent = audioUnlocked ? 'Audio actief, wacht op stap' : 'Wacht op stap';
      }
    }

    async function fetchSessionStatus(sessionId) {
      return await postJson(STATUS_URL, { sessionId });
    }

    function handleSessionJoined(status) {
      const joinedAt = String(status?.joinedAt || '').trim();
      if (!joinedAt || joinedAt === lastJoinedAt) {
        return false;
      }
      lastJoinedAt = joinedAt;
      const session = getActiveSession();
      if (session?.sessionId) {
        const nextSession = {
          ...session,
          joinedAt,
          expiresAt: status?.expiresAt || session.expiresAt || ''
        };
        setActiveSession(nextSession);
        renderSession(nextSession);
      }
      renderSessionSetupVisible(false);
      renderPlayerVisible(true);
      renderPageHeader('lesson');
      openBlockly();
      setResolveStatus('Telefoon verbonden. Scan nu de step-link code in het boek.', 'success');
      $('sessionStateText').textContent = 'Telefoon verbonden';
      stopSessionJoinPolling();
      logLine('Session QR scanned; braille monitor opened.', {
        sessionId: status?.sessionId || session?.sessionId || '',
        joinedAt
      });
      return true;
    }

    function stopSessionJoinPolling() {
      if (sessionJoinPollTimer) {
        window.clearInterval(sessionJoinPollTimer);
        sessionJoinPollTimer = null;
      }
      sessionJoinPollInFlight = false;
    }

    function startSessionJoinPolling() {
      const session = getActiveSession();
      if (!session?.sessionId || String(session?.joinedAt || '').trim()) {
        return;
      }
      stopSessionJoinPolling();
      const poll = async () => {
        if (sessionJoinPollInFlight) {
          return;
        }
        sessionJoinPollInFlight = true;
        try {
          const status = await fetchSessionStatus(session.sessionId);
          handleSessionJoined(status);
        } catch (err) {
          logLine('Session join check failed.', { message: err?.message || String(err) });
        } finally {
          sessionJoinPollInFlight = false;
        }
      };
      sessionJoinPollTimer = window.setInterval(poll, 1000);
      poll();
    }

    async function waitForSessionStatus(sessionId, since = '') {
      const requestStartedAt = Date.now();
      const status = await postJson(WAIT_URL, { sessionId, since });
      status._clientWaitRoundTripMs = Math.max(0, Date.now() - requestStartedAt);
      return status;
    }

    async function updateSessionRuntimeState(state, payload = null) {
      const sessionId = String(getActiveSession()?.sessionId || '').trim();
      if (!sessionId) {
        return null;
      }
      const body = { sessionId, state };
      if (payload && typeof payload === 'object') {
        body.code = String(payload.code || '').trim();
        body.scriptId = String(payload.scriptId || '').trim();
        body.stepId = String(payload.stepId || '').trim();
      }
      return await postJson(RUNTIME_STATE_URL, body);
    }

    async function releaseActiveStepAfterStop() {
      const previousPayload = getLastResolved();
      activeStepCompletionWatchToken += 1;
      lastStartedStepStillActive = false;
      lastStartedStepKey = '';
      activeStepRunPromise = null;
      try {
        const app = $('builderFrame')?.contentWindow?.BrailleBlocklyApp;
        if (app && typeof app.stopProgram === 'function') {
          await app.stopProgram();
        }
      } catch (err) {
        logLine('Blockly stopProgram call after stop button failed.', { message: err?.message || String(err) });
      }
      await updateSessionRuntimeState('idle');
      setResolveStatus('Stap gestopt. Scan een nieuwe step-link code om verder te gaan.', 'success');
      $('sessionStateText').textContent = 'Wachten op stap';
      renderIncomingStepBadge({ visible: false });
      logLine('Session runtime released after stop button.', {
        code: previousPayload?.code || '',
        scriptId: previousPayload?.scriptId || '',
        stepId: previousPayload?.stepId || ''
      });
    }

    async function monitorActiveStepCompletion(stepKey, payload) {
      const watchToken = ++activeStepCompletionWatchToken;
      logLine('Started active step completion watcher.', {
        code: payload?.code || '',
        scriptId: payload?.scriptId || '',
        stepId: payload?.stepId || ''
      });

      while (watchToken === activeStepCompletionWatchToken && lastStartedStepKey === stepKey && lastStartedStepStillActive) {
        await new Promise((resolve) => window.setTimeout(resolve, 1000));
        if (watchToken !== activeStepCompletionWatchToken || lastStartedStepKey !== stepKey || !lastStartedStepStillActive) {
          return;
        }

        let snapshot = null;
        try {
          const app = $('builderFrame')?.contentWindow?.BrailleBlocklyApp;
          if (!app || typeof app.getRuntimeSnapshot !== 'function') {
            return;
          }
          snapshot = app.getRuntimeSnapshot();
        } catch (err) {
          logLine('Active step completion watcher could not read runtime snapshot.', {
            code: payload?.code || '',
            scriptId: payload?.scriptId || '',
            stepId: payload?.stepId || '',
            message: err?.message || String(err)
          });
          return;
        }

        if (snapshotShowsActive(snapshot)) {
          continue;
        }

        lastStartedStepStillActive = false;
        lastStartedStepKey = '';
        setResolveStatus(`Stap ${payload?.code || ''} is afgerond.`, 'success');
        $('sessionStateText').textContent = 'Stap afgerond';
        logLine('Active step completion watcher detected runtime idle.', {
          code: payload?.code || '',
          scriptId: payload?.scriptId || '',
          stepId: payload?.stepId || '',
          snapshot
        });
        try {
          await updateSessionRuntimeState('idle');
        } catch (err) {
          logLine('Could not mark session runtime idle after watcher completion.', {
            code: payload?.code || '',
            scriptId: payload?.scriptId || '',
            stepId: payload?.stepId || '',
            message: err?.message || String(err)
          });
        }
        return;
      }
    }

    async function handleStartedStep(status) {
      const payload = status?.lastResolved;
      if (!payload || typeof payload !== 'object') {
        return;
      }
      const resolvedAtMs = payload?.resolvedAt ? Date.parse(String(payload.resolvedAt)) : NaN;
      const receiveLatencyMs = Number.isFinite(resolvedAtMs)
        ? Math.max(0, Date.now() - resolvedAtMs)
        : null;
      const noticeSignature = JSON.stringify({
        code: payload.code || '',
        resolvedAt: payload.resolvedAt || '',
        stepId: payload.stepId || '',
        scriptId: payload.scriptId || ''
      });
      if (noticeSignature === lastResolvedSignature) {
        return;
      }
      const stepKey = JSON.stringify({
        code: payload.code || '',
        stepId: payload.stepId || '',
        scriptId: payload.scriptId || ''
      });
      lastResolvedSignature = noticeSignature;
      setLastResolved(payload);
      renderResolved(payload);
      enrichStepInfoFromScript(payload);
      logLine('Incoming step-link received.', {
        code: payload.code || '',
        scriptId: payload.scriptId || '',
        stepId: payload.stepId || '',
        resolvedAt: payload.resolvedAt || '',
        receiveLatencyMs,
        waitResponseMs: Number(status?.waitedMs ?? 0) || 0,
        waitRoundTripMs: Number(status?._clientWaitRoundTripMs ?? 0) || 0
      });
      renderIncomingStepBadge({
        visible: true,
        text: `Nieuwe stap ontvangen: ${payload.code || payload.stepId || 'onbekend'}`
      });
      try {
        await playStepStartNotice();
      } catch (err) {
        logLine('Notification sound blocked or failed.', {
          message: err?.message || String(err),
          url: STEP_START_NOTICE_AUDIO_URL
        });
        console.warn(err);
      }
      if (stepKey === lastStartedStepKey && lastStartedStepStillActive) {
        logLine('Step already active, skipped auto-start.', {
          code: payload.code || '',
          scriptId: payload.scriptId || '',
          stepId: payload.stepId || ''
        });
        setResolveStatus(`Nieuwe stap ontvangen. Stap ${payload.code || ''} is al bezig.`, 'success');
        $('sessionStateText').textContent = 'Stap actief';
        return;
      }
      if (lastStartedStepKey && lastStartedStepStillActive) {
        logLine('New step-link ignored because another step is active.', {
          activeStepKey: lastStartedStepKey,
          incomingCode: payload.code || '',
          incomingStepId: payload.stepId || '',
          incomingScriptId: payload.scriptId || ''
        });
        setResolveStatus('Nieuwe stap ontvangen; de huidige stap blijft bezig.', 'success');
        $('sessionStateText').textContent = 'Stap actief';
        return;
      }
      activeStepCompletionWatchToken += 1;
      try {
        await updateSessionRuntimeState('active', payload);
      } catch (err) {
        logLine('Could not mark session runtime active.', {
          code: payload.code || '',
          scriptId: payload.scriptId || '',
          stepId: payload.stepId || '',
          message: err?.message || String(err)
        });
      }
      lastStartedStepKey = stepKey;
      lastStartedStepStillActive = true;
      setResolveStatus(`Stap ${payload.code || ''} is gestart.`, 'success');
      $('sessionStateText').textContent = 'Stap actief';
      const autoStartStartedAt = Date.now();
      activeStepRunPromise = (async () => {
        try {
          const startResult = await applyResolvedToBlockly(payload, { autoOpen: true });
          const autoStartDurationMs = Math.max(0, Date.now() - autoStartStartedAt);
          logLine(startResult?.runtimeStillActive ? 'Step auto-start succeeded.' : 'Step started and completed immediately.', {
            code: payload.code || '',
            scriptId: payload.scriptId || '',
            stepId: payload.stepId || '',
            autoStartDurationMs,
            runtimeStillActive: Boolean(startResult?.runtimeStillActive)
          });
          lastStartedStepStillActive = Boolean(startResult?.runtimeStillActive);
          lastStartedStepKey = lastStartedStepStillActive ? stepKey : '';
          setResolveStatus(
            startResult?.runtimeStillActive
              ? `Stap ${payload.code || ''} is gestart.`
              : `Stap ${payload.code || ''} is uitgevoerd en direct afgerond.`,
            'success'
          );
          $('sessionStateText').textContent = startResult?.runtimeStillActive ? 'Stap actief' : 'Stap afgerond';
          if (!startResult?.runtimeStillActive) {
            try {
              await updateSessionRuntimeState('idle');
            } catch (err) {
              logLine('Could not mark session runtime idle after completion.', {
                code: payload.code || '',
                scriptId: payload.scriptId || '',
                stepId: payload.stepId || '',
                message: err?.message || String(err)
              });
            }
          } else {
            monitorActiveStepCompletion(stepKey, payload).catch((err) => {
              logLine('Active step completion watcher failed.', {
                code: payload.code || '',
                scriptId: payload.scriptId || '',
                stepId: payload.stepId || '',
                message: err?.message || String(err)
              });
            });
          }
        } catch (err) {
          activeStepCompletionWatchToken += 1;
          lastStartedStepStillActive = false;
          lastStartedStepKey = '';
          setResolveStatus(err.message || String(err), 'danger');
          $('sessionStateText').textContent = 'Start mislukt';
          try {
            await updateSessionRuntimeState('idle');
          } catch (idleErr) {
            logLine('Could not mark session runtime idle after failure.', {
              code: payload.code || '',
              scriptId: payload.scriptId || '',
              stepId: payload.stepId || '',
              message: idleErr?.message || String(idleErr)
            });
          }
          logLine('Background step start failed.', {
            code: payload.code || '',
            scriptId: payload.scriptId || '',
            stepId: payload.stepId || '',
            message: err?.message || String(err)
          });
        } finally {
          activeStepRunPromise = null;
        }
      })();
    }

    function stopSessionPolling() {
      sessionWaitLoopToken += 1;
      sessionPollTimer = null;
    }

    function startSessionPolling() {
      const session = getActiveSession();
      if (!session?.sessionId) {
        return;
      }
      stopSessionPolling();
      const loopToken = sessionWaitLoopToken;
      const loop = async () => {
        let since = String(getLastResolved()?.resolvedAt || '').trim();
        if (!since) {
          try {
            const initial = await fetchSessionStatus(session.sessionId);
            if (loopToken !== sessionWaitLoopToken) return;
            since = String(initial?.lastResolvedAt || '').trim();
            handleSessionJoined(initial);
            if (initial?.lastResolvedAt) {
              await handleStartedStep(initial);
            }
          } catch (err) {
            if (loopToken !== sessionWaitLoopToken) return;
            setResolveStatus(err.message || String(err), 'danger');
            logLine('Initial session status failed.', { message: err?.message || String(err) });
          }
        }
        while (loopToken === sessionWaitLoopToken) {
          try {
            const status = await waitForSessionStatus(session.sessionId, since);
            if (loopToken !== sessionWaitLoopToken) return;
            handleSessionJoined(status);
            if (status?.changed && status?.lastResolvedAt) {
              logLine('Session wait returned a new step-link.', {
                sessionId: session.sessionId,
                waitedMs: Number(status?.waitedMs ?? 0) || 0,
                roundTripMs: Number(status?._clientWaitRoundTripMs ?? 0) || 0,
                lastResolvedAt: status?.lastResolvedAt || ''
              });
              since = String(status.lastResolvedAt || '').trim();
              await handleStartedStep(status);
              continue;
            }
            logLine('Session wait returned without changes.', {
              sessionId: session.sessionId,
              waitedMs: Number(status?.waitedMs ?? 0) || 0,
              roundTripMs: Number(status?._clientWaitRoundTripMs ?? 0) || 0
            });
            if (status?.lastResolvedAt) {
              since = String(status.lastResolvedAt || '').trim();
            }
          } catch (err) {
            if (loopToken !== sessionWaitLoopToken) return;
            setResolveStatus(err.message || String(err), 'danger');
            logLine('Session wait failed; retrying.', { message: err?.message || String(err) });
            await new Promise((resolve) => window.setTimeout(resolve, 800));
          }
        }
      };
      sessionPollTimer = true;
      loop().catch((err) => {
        if (loopToken !== sessionWaitLoopToken) return;
        setResolveStatus(err.message || String(err), 'danger');
        logLine('Session wait loop stopped with error.', { message: err?.message || String(err) });
      });
    }

    function openBlockly() {
      const frame = $('builderFrame');
      if (frame.src === BLOCKLY_URL) {
        customizeBlocklyFrameUi();
        scheduleBlocklyFrameCustomization(5000);
        return;
      }
      frame.src = BLOCKLY_URL;
      scheduleBlocklyFrameCustomization();
    }

    function bootstrap() {
      stopSessionJoinPolling();
      resetSessionRuntimeState();
      setResolveStatus('Klaar voor de les. Start eerst een sessie.', 'info');
      $('refreshQrBtn').addEventListener('click', async () => {
        try {
          await startFreshSession({ unlockAudio: true });
        } catch (err) {
          logLine('New QR action failed.', { message: err?.message || String(err) });
          setResolveStatus(err.message || String(err), 'danger');
        }
      });
      $('showSessionInfoBtn').addEventListener('click', () => {
        const card = $('sessionInfoCard');
        const button = $('showSessionInfoBtn');
        if (!card || !button) return;
        const shouldShow = card.classList.contains('d-none');
        card.hidden = !shouldShow;
        card.classList.toggle('d-none', !shouldShow);
        button.textContent = shouldShow ? 'Details verbergen' : 'Technische details';
      });
      $('builderFrame').addEventListener('load', () => {
        blocklyFrameCustomized = false;
        blocklyLogBridgeAttached = false;
        blocklyMonitorSyncAttached = false;
        blocklyStopBridgeAttached = false;
        scheduleBlocklyFrameCustomization();
      });

      window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_SESSION_KEY) {
          renderSession(getActiveSession());
          startSessionPolling();
          startSessionJoinPolling();
        }
        if (event.key === STORAGE_RESOLVED_KEY) {
          renderResolved(getLastResolved());
        }
      });
    }

    bootstrap();
  </script>
</body>
</html>
