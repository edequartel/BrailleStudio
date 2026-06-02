<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/blockly$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$jsValue = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>BrailleStudio Session Player</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../components/braille-monitor/braillemonitor.css?v=20260529-mode-label-1">
  <style>
    html,
    body {
      height: 100%;
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #fff;
      color: #111827;
    }

    body {
      display: flex;
      flex-direction: column;
      min-height: 100%;
      overflow: hidden;
    }

    .session-player {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-height: 100%;
      padding: 10px;
    }

    .session-player__status {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      min-height: 28px;
      font-size: 13px;
      color: #4b5563;
    }

    #brailleMonitorComponent {
      min-height: 72px;
    }

    #logBox,
    #statusBox,
    #brailleLineBox,
    .headless-field {
      position: absolute;
      width: 1px;
      height: 1px;
      overflow: hidden;
      clip: rect(0 0 0 0);
      white-space: nowrap;
      border: 0;
      padding: 0;
      margin: -1px;
    }
  </style>
</head>
<body>
  <main class="session-player">
    <div class="session-player__status">
      <span id="loadingStage">Session player laden...</span>
      <span id="bridgeLaunchIndicator" aria-hidden="true"></span>
    </div>
    <div id="brailleMonitorComponent"></div>
  </main>

  <textarea id="logBox" aria-hidden="true"></textarea>
  <pre id="statusBox" aria-hidden="true"></pre>
  <pre id="brailleLineBox" aria-hidden="true"></pre>

  <input id="fileNameInput" class="headless-field" value="session-player.blockly">
  <input id="onlineScriptIdInput" class="headless-field">
  <input id="onlineScriptTitleInput" class="headless-field">
  <select id="onlineScriptStatusInput" class="headless-field"><option value="draft">draft</option></select>
  <select id="onlineScriptsSelect" class="headless-field"><option value=""></option></select>
  <button id="onlineRefreshBtn" class="headless-field" type="button"></button>
  <button id="onlineSaveBtn" class="headless-field" type="button"></button>
  <button id="onlineSaveAsBtn" class="headless-field" type="button"></button>
  <button id="onlineDeleteBtn" class="headless-field" type="button"></button>
  <input id="soundBaseUrlBox" class="headless-field">

  <script>
    window.BrailleBlocklyHeadless = true;
    window.BrailleBlocklyAuth = {
      authApiBasePath: <?= $jsValue($urlFor($appBase, 'authentication-api/')) ?>,
      authLoginPageUrl: <?= $jsValue($urlFor($appBase, 'authentication.php')) ?>,
      authBridgePageUrl: <?= $jsValue($urlFor($appBase, 'authentication.php?mode=bridge')) ?>,
      homepageOrigin: 'https://www.tastenbraille.com'
    };
    window.BrailleBlocklyMode = {
      embedMode: 'session-player',
      isSessionPlayerEmbed: true
    };
    try {
      window.sessionStorage.setItem('brailleBlocklyEmbedMode', 'session-player');
    } catch {}
    window.BrailleBlocklyBoot = {
      stage: 'index-html',
      error: '',
      updatedAt: Date.now()
    };
    window.__setBrailleBlocklyBootStage = function (stage, extra = {}) {
      window.BrailleBlocklyBoot = {
        ...(window.BrailleBlocklyBoot || {}),
        ...extra,
        stage,
        updatedAt: Date.now()
      };
      const stageEl = document.getElementById('loadingStage');
      if (stageEl) {
        stageEl.textContent = stage === 'api-ready'
          ? 'Session player klaar'
          : (extra && extra.error ? `Fout: ${extra.error}` : `Laden: ${stage}`);
      }
      window.dispatchEvent(new CustomEvent('braille-blockly-boot-stage', {
        detail: window.BrailleBlocklyBoot
      }));
    };
    window.addEventListener('error', function (event) {
      window.__setBrailleBlocklyBootStage('error', {
        error: String(event?.error?.message || event?.message || 'Onbekende fout')
      });
    });
    window.addEventListener('unhandledrejection', function (event) {
      const reason = event?.reason;
      window.__setBrailleBlocklyBootStage('error', {
        error: String(reason?.message || reason || 'Onbekende promise-fout')
      });
    });
  </script>
  <script src="./vendor/blockly-12.5.1/blockly_compressed.js"></script>
  <script src="./vendor/blockly-12.5.1/blocks_compressed.js"></script>
  <script src="./vendor/blockly-12.5.1/javascript_compressed.js"></script>
  <script src="./vendor/blockly-12.5.1/en.js"></script>
  <script src="./runtime.js?v=20260416-session-player-3"></script>
  <script src="./instructions-catalog.js?v=20260416-session-player-3"></script>
  <script src="./preload-instructions.js?v=20260416-session-player-3"></script>
  <script src="./blocks.js?v=20260529-external-debug-2"></script>
  <script src="./generators.js?v=20260529-external-debug-2"></script>
  <script src="../components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1"></script>
  <script src="../components/braillebridge-status/braillebridge-status.js?v=20260526-popup-3"></script>
  <script src="./app.js?v=20260602-headless-highlight-1"></script>
</body>
</html>
