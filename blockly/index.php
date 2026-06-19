<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

$isSessionPlayerEmbed = (string)($_GET['embed'] ?? '') === 'session-player';
if ($isSessionPlayerEmbed) {
    $redirectParams = $_GET;
    unset($redirectParams['embed']);
    $redirectTarget = './session-player.php';
    if ($redirectParams !== []) {
        $redirectTarget .= '?' . http_build_query($redirectParams);
    }
    header('Location: ' . $redirectTarget, true, 302);
    exit;
}

$authUser = $isSessionPlayerEmbed
    ? ['display' => 'Sessie', 'role' => 'session']
    : bs_auth_require_login(['admin', 'docent']);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/blockly$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$jsValue = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/braillestudio/apple-touch-icon.png">
  <link rel="manifest" href="/braillestudio/site.webmanifest">
  <meta charset="utf-8">
  <title>Braille Activity Builder</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <script>
    window.BrailleBlocklyAuth = {
      authApiBasePath: <?= $jsValue($urlFor($appBase, 'authentication-api/')) ?>,
      authLoginPageUrl: <?= $jsValue($urlFor($appBase, 'authentication.php')) ?>,
      authBridgePageUrl: <?= $jsValue($urlFor($appBase, 'authentication.php?mode=bridge')) ?>,
      homepageOrigin: 'https://www.tastenbraille.com'
    };

    window.BrailleBlocklyMode = {
      embedMode: <?= $jsValue($isSessionPlayerEmbed ? 'session-player' : '') ?>,
      isSessionPlayerEmbed: <?= $isSessionPlayerEmbed ? 'true' : 'false' ?>
    };

    try {
      const requestedEmbedMode = new URLSearchParams(window.location.search).get('embed') || window.BrailleBlocklyMode.embedMode || '';
      if (requestedEmbedMode === 'session-player') {
        window.sessionStorage.setItem('brailleBlocklyEmbedMode', 'session-player');
      }
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
      window.dispatchEvent(new CustomEvent('braille-blockly-boot-stage', {
        detail: window.BrailleBlocklyBoot
      }));
    };

    window.addEventListener('error', function (event) {
      const message = event?.error?.message || event?.message || 'Unknown boot error';
      window.__setBrailleBlocklyBootStage('error', {
        error: String(message),
        source: event?.filename || '',
        line: event?.lineno || 0,
        column: event?.colno || 0
      });
    });

    window.addEventListener('unhandledrejection', function (event) {
      const reason = event?.reason;
      const message = reason && reason.message ? reason.message : String(reason || 'Unknown promise rejection');
      window.__setBrailleBlocklyBootStage('error', {
        error: message
      });
    });
  </script>

  <script src="./vendor/blockly-12.5.1/blockly_compressed.js"></script>
  <script src="./vendor/blockly-12.5.1/blocks_compressed.js"></script>
  <script src="./vendor/blockly-12.5.1/javascript_compressed.js"></script>
  <script src="./vendor/blockly-12.5.1/en.js"></script>
  <link rel="stylesheet" href="../tabler/core/dist/css/tabler.min.css">
  <link rel="stylesheet" href="../tabler/icons-webfont/dist/tabler-icons.min.css">
  <link rel="stylesheet" href="../components/braille-monitor/braillemonitor.css?v=20260529-mode-label-1">
  <link rel="stylesheet" href="../components/braillebridge-status/braillebridge-status.css?v=20260526-popup-3">

  <style>
    :root {
      --app-zoom: 90%;
      --bg: var(--tblr-body-bg);
      --panel: var(--tblr-bg-surface);
      --panel-soft: var(--tblr-bg-surface);
      --border: var(--tblr-border-color);
      --text: var(--tblr-body-color);
      --muted: var(--tblr-muted);
      --green: var(--tblr-success);
      --red: var(--tblr-danger);
      --blue: var(--tblr-primary);
      --amber: var(--tblr-warning);
    }

    * { box-sizing: border-box; }

    html, body {
      height: 100%;
      margin: 0;
      font-family: var(--tblr-font-sans-serif);
      background: var(--bg);
      color: var(--text);
      overflow: hidden;
    }

    #app {
      display: flex;
      flex-direction: column;
      width: 111.111vw;
      height: 111.111vh;
      min-height: 0;
      zoom: var(--app-zoom);
    }

    #topbar {
      position: relative;
      z-index: 30;
      flex: 0 0 auto;
      background: var(--panel);
      border-bottom: 1px solid var(--border);
      padding: 12px 16px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      box-shadow: var(--tblr-box-shadow-sm);
    }

    .topbar-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }

    .topbar-row--main {
      flex-wrap: nowrap;
    }

    .topbar-row--sim,
    .topbar-row--thumbs {
      padding-left: 0;
    }

    .topbar-row--thumbs {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
      align-items: center;
      justify-content: stretch;
    }

    .thumb-controls {
      display: inline-flex;
      grid-column: 2;
      gap: 8px;
      justify-content: center;
    }

    .thumb-controls-status {
      grid-column: 3;
      justify-self: end;
    }

    .topbar-row--thumbs.is-hidden {
      display: none;
    }

    .topbar-row--monitor {
      align-items: stretch;
    }

    .topbar-row--monitor.is-hidden {
      display: none;
    }

    .topbar-row--scripts {
      align-items: flex-start;
      justify-content: space-between;
    }

    .topbar-script-group {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      min-width: 0;
    }

    .topbar-script-group--fields {
      flex: 1 1 540px;
    }

    .topbar-script-picker {
      display: flex;
      flex: 1 1 560px;
      flex-wrap: nowrap;
      gap: 8px;
      align-items: center;
      min-width: min(100%, 560px);
    }

    .topbar-script-picker #onlineScriptsSelect {
      flex: 1 1 320px;
      min-width: 240px;
    }

    .topbar-script-picker #onlineScriptStatusInput {
      flex: 0 0 220px;
    }

    .topbar-script-picker #onlineRefreshBtn {
      flex: 0 0 auto;
      white-space: nowrap;
    }

    .topbar-script-group--actions {
      flex: 0 1 auto;
      justify-content: flex-end;
    }

    @media (max-width: 760px) {
      .topbar-script-picker {
        flex-wrap: wrap;
        min-width: 100%;
      }

      .topbar-script-picker #onlineScriptsSelect,
      .topbar-script-picker #onlineScriptStatusInput,
      .topbar-script-picker #onlineRefreshBtn {
        flex: 1 1 100%;
      }
    }

    .braille-monitor-host {
      flex: 1 1 420px;
      min-width: 280px;
      width: auto;
      border-radius: 5px;
      overflow: hidden;
    }

    .braille-monitor-host .braille-monitor-component,
    .braille-monitor-host .mono-box.braille-monitor-cells {
      border-radius: 5px;
    }

    #title {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 20px;
      font-weight: 600;
      margin-right: 10px;
    }

    .spacer {
      flex: 1 1 auto;
    }

    #instructionTtsVoiceSelect,
    #instructionTtsSpacePauseSelect {
      min-height: 40px;
      height: 40px;
      padding: 8px 12px;
      box-sizing: border-box;
    }

    .topbar-bridge-status {
      flex: 0 0 auto;
      min-width: 0;
      max-width: 420px;
    }

    .topbar-bridge-status .braillebridge-status__toggle {
      width: 2.25rem;
      height: 2.25rem;
    }

    .topbar-bridge-status .braillebridge-status__toggle-dot {
      margin-top: -1.1rem;
      margin-left: 1.1rem;
    }

    .topbar-bridge-status [data-role="test"] {
      display: none;
    }

    .topbar-bridge-status .braillebridge-status__body {
      align-items: flex-start;
      flex-direction: column;
    }

    .topbar-bridge-status .braillebridge-status__meta {
      justify-content: flex-start;
    }

    @media (max-width: 760px) {
      .topbar-row--thumbs {
        grid-template-columns: minmax(0, 1fr) auto;
      }

      .thumb-controls {
        grid-column: 1;
        justify-self: center;
      }

      .thumb-controls-status {
        grid-column: 2;
      }

      .topbar-bridge-status {
        flex: 0 0 auto;
        max-width: none;
      }
    }

    .status-card-script-name {
      display: flex;
      align-items: center;
      gap: .5rem;
      min-height: 36px;
      margin-bottom: 8px;
      padding: 8px 10px;
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-bg-surface-secondary);
      color: var(--tblr-body-color);
      font-size: .875rem;
      font-weight: 600;
    }

    .status-card-script-name i {
      color: var(--tblr-muted);
      font-size: 1rem;
    }

    .status-card-script-name span {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: bold;
      background: #fee2e2;
      color: #991b1b;
    }

    .badge.connected {
      background: #dcfce7;
      color: #166534;
    }

    .badge.running {
      background: #dcfce7;
      color: #166534;
      box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
    }

    .badge.stopped {
      background: #fee2e2;
      color: #991b1b;
    }

    .run-status {
      display: flex;
      align-items: center;
      gap: 10px;
      min-height: 42px;
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-bg-surface-secondary);
      color: #334155;
    }

    .run-status-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 90px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid var(--tblr-border-color);
      background: var(--tblr-bg-surface);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: #475569;
    }

    .run-status-text {
      font-size: 13px;
      line-height: 1.4;
      color: #334155;
    }

    .run-status.is-running {
      border-color: #bfdbfe;
      background: #eff6ff;
    }

    .run-status.is-running .run-status-badge {
      border-color: #93c5fd;
      background: #dbeafe;
      color: #1d4ed8;
    }

    .run-status.is-stopping {
      border-color: #fde68a;
      background: #fffbeb;
    }

    .run-status.is-stopping .run-status-badge {
      border-color: #fcd34d;
      background: #fef3c7;
      color: #92400e;
    }

    .run-status.is-completed {
      border-color: #86efac;
      background: #f0fdf4;
    }

    .run-status.is-completed .run-status-badge {
      border-color: #86efac;
      background: #dcfce7;
      color: #166534;
    }

    .run-status.is-stopped {
      border-color: #cbd5e1;
      background: #f8fafc;
    }

    .run-status.is-stopped .run-status-badge {
      border-color: #cbd5e1;
      background: white;
      color: #475569;
    }

    .run-status.is-failed {
      border-color: #fecaca;
      background: #fef2f2;
    }

    .run-status.is-failed .run-status-badge {
      border-color: #fca5a5;
      background: #fee2e2;
      color: #b91c1c;
    }

    #fileStateBadge {
      width: 56px;
      justify-content: center;
      padding: 8px 8px;
      gap: 0;
    }

    #fileStateBadge #fileStateText {
      display: none;
    }

    #main {
      position: relative;
      z-index: 1;
      flex: 1 1 auto;
      display: grid;
      grid-template-columns: minmax(0, 1fr) 18px var(--sidebar-width, 780px);
      height: 100%;
      min-height: 0;
      overflow: hidden;
    }

    #main.is-sidebar-hidden {
      grid-template-columns: 1fr;
    }

    #main.is-sidebar-hidden #sidebar {
      display: none;
    }

    #workspaceWrap {
      position: relative;
      min-width: 0;
      min-height: 0;
      height: 100%;
      padding: 10px;
      overflow: hidden;
    }

    .main-divider {
      position: relative;
      min-height: 0;
      cursor: col-resize;
      background:
        linear-gradient(180deg, rgba(255,255,255,0.45), rgba(255,255,255,0)),
        linear-gradient(180deg, rgba(37, 99, 235, 0.10), rgba(148, 163, 184, 0.18), rgba(37, 99, 235, 0.10));
    }

    .main-divider:hover,
    .main-divider.is-dragging {
      background:
        linear-gradient(180deg, rgba(255,255,255,0.60), rgba(255,255,255,0.08)),
        linear-gradient(180deg, rgba(37, 99, 235, 0.16), rgba(96, 165, 250, 0.28), rgba(37, 99, 235, 0.16));
    }

    .main-divider::before {
      content: "";
      position: absolute;
      top: 14px;
      bottom: 14px;
      left: 50%;
      width: 3px;
      transform: translateX(-50%);
      border-radius: 999px;
      background: linear-gradient(180deg, rgba(37, 99, 235, 0), rgba(37, 99, 235, 0.38), rgba(148, 163, 184, 0.52), rgba(37, 99, 235, 0.38), rgba(37, 99, 235, 0));
      box-shadow: 0 0 0 1px rgba(255,255,255,0.38);
    }

    .main-divider::after {
      content: "";
      position: absolute;
      left: 50%;
      top: 50%;
      width: 12px;
      height: 12px;
      transform: translate(-50%, -50%);
      border-radius: 999px;
      background: radial-gradient(circle, rgba(37, 99, 235, 0.65) 0%, rgba(37, 99, 235, 0.20) 45%, rgba(37, 99, 235, 0) 72%);
      box-shadow: 0 0 22px rgba(37, 99, 235, 0.18);
    }

    #main.is-sidebar-hidden .main-divider {
      display: none;
    }

    #blocklyDiv {
      position: relative;
      z-index: 1;
      width: 100%;
      height: 100%;
      border: 1px solid var(--border);
      border-radius: var(--tblr-border-radius-lg);
      overflow: hidden;
      background: var(--tblr-bg-surface);
      box-shadow: var(--tblr-box-shadow-sm);
    }

    .blocklyToolboxDiv,
    .blocklyFlyout,
    .blocklyFlyoutBackground {
      background: #f8faff !important;
    }

    .blocklyTreeRow:focus,
    .blocklyTreeRow:focus-visible,
    .blocklyToolboxCategory:focus,
    .blocklyToolboxCategory:focus-visible,
    .blocklyToolboxContents [tabindex]:focus,
    .blocklyToolboxContents [tabindex]:focus-visible,
    .blocklyToolboxContents [role="treeitem"]:focus,
    .blocklyToolboxContents [role="treeitem"]:focus-visible {
      outline: none !important;
      box-shadow: none !important;
      -webkit-focus-ring-color: transparent !important;
    }

    .blocklyTreeRow {
      border: none !important;
    }

    .blocklyToolboxContents,
    .blocklyToolboxContents * {
      caret-color: transparent;
      user-select: none;
      -webkit-user-select: none;
    }

    .blocklyToolboxContents [aria-selected="true"] {
      outline: none !important;
      box-shadow: none !important;
    }

    .blocklyTreeSeparator {
      border-color: transparent !important;
    }

    #sidebar {
      border-left: 1px solid var(--border);
      background: var(--tblr-bg-surface-secondary);
      padding: 10px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      overflow: auto;
    }

    .card {
      background: var(--panel-soft);
      border: 1px solid var(--border);
      border-radius: var(--tblr-border-radius-lg);
      padding: 0;
      box-shadow: var(--tblr-box-shadow-sm);
    }

    .card h3 {
      margin: 0;
      font-size: 15px;
    }

    .card-body {
      padding: 10px;
    }

    .mono {
      font-family: Consolas, Menlo, monospace;
      font-size: 12px;
      line-height: 1.45;
      white-space: pre;
      overflow-x: auto;
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--tblr-border-radius);
      padding: 8px;
      min-height: 110px;
    }

    textarea.mono {
      width: 100%;
      resize: vertical;
      min-height: 170px;
    }

    #logBox {
      min-height: 340px;
      height: 340px;
    }

    .small {
      font-size: 12px;
      color: var(--muted);
      line-height: 1.4;
    }

    .small.is-error {
      color: #b91c1c;
    }

    .instruction-tts-controls {
      display: grid;
      grid-template-columns: minmax(0, 0.8fr) auto minmax(0, 1.4fr) auto;
      gap: 8px;
      margin-bottom: 8px;
      align-items: center;
    }

    .auth-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: 8px;
      margin-bottom: 8px;
    }

    .auth-actions {
      display: flex;
      gap: 8px;
      margin-bottom: 8px;
    }

    .meta-input,
    .meta-textarea {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-bg-surface);
      color: var(--text);
      font-family: Consolas, Menlo, monospace;
      font-size: 12px;
      line-height: 1.45;
      padding: 8px 10px;
    }

    .meta-input--compact {
      min-height: 32px;
      height: 32px;
      padding: 5px 8px;
      font-size: 11px;
      line-height: 1.2;
    }

    .meta-textarea--compact {
      min-height: 48px;
      max-height: calc(10 * 1.2em + 16px);
      padding: 6px 8px;
      font-size: 11px;
      line-height: 1.2;
      resize: vertical;
    }

    .meta-textarea {
      min-height: 90px;
      resize: vertical;
    }

    #scriptMetaDescription,
    #scriptTextInput,
    #scriptMemoInput {
      min-height: 144px;
      max-height: calc(30 * 1.2em + 16px);
    }

    .variable-list {
      display: grid;
      gap: 6px;
    }

    .variable-item {
      display: grid;
      gap: 4px;
      padding: 8px;
      border: 1px solid var(--border);
      border-left-width: 4px;
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-bg-surface);
    }

    .variable-item--external {
      border-left-color: #2563EB;
    }

    .variable-item--internal {
      border-left-color: #7C3AED;
    }

    .variable-item__top {
      display: flex;
      align-items: center;
      gap: 6px;
      min-width: 0;
    }

    .variable-item__name {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-weight: 600;
    }

    .variable-item__meta,
    .variable-item__description {
      font-size: 11px;
      color: var(--muted);
      line-height: 1.25;
      overflow-wrap: anywhere;
    }

    .variable-badge {
      display: inline-flex;
      align-items: center;
      min-height: 18px;
      padding: 2px 6px;
      border-radius: 999px;
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0;
      white-space: nowrap;
    }

    .variable-badge--external {
      background: #dbeafe;
      color: #1d4ed8;
    }

    .variable-badge--internal {
      background: #ede9fe;
      color: #6d28d9;
    }

    .variable-context-menu {
      position: fixed;
      z-index: 1080;
      min-width: 190px;
      display: none;
      padding: 4px;
      border: 1px solid var(--border);
      border-radius: var(--tblr-border-radius);
      background: var(--panel);
      box-shadow: var(--tblr-box-shadow-lg);
    }

    .variable-context-menu.is-open {
      display: grid;
    }

    .variable-context-menu button {
      width: 100%;
      justify-content: flex-start;
    }

    .row {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }

    .file-state {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      min-height: 40px;
      padding: 8px 12px;
      border-radius: var(--tblr-border-radius);
      border: 1px solid #c7d7ff;
      background: #eef4ff;
      color: #1d4ed8;
      font-size: 14px;
      font-weight: 600;
      min-width: 0;
    }

    .with-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .icon-14 {
      width: 14px;
      height: 14px;
      display: inline-block;
      flex: 0 0 auto;
    }

    .icon-14 svg {
      width: 14px;
      height: 14px;
      display: block;
    }

    .file-state.is-dirty {
      border-color: #fecaca;
      background: #fef2f2;
      color: #991b1b;
    }

    #onlineSaveBtn.is-dirty {
      border-color: var(--tblr-primary);
      background: var(--tblr-primary);
      color: var(--tblr-primary-fg);
      box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--tblr-primary) 18%, transparent);
    }

    #onlineSaveBtn.is-dirty:hover,
    #onlineSaveBtn.is-dirty:focus {
      border-color: color-mix(in srgb, var(--tblr-primary) 86%, #000);
      background: color-mix(in srgb, var(--tblr-primary) 92%, #000);
      color: var(--tblr-primary-fg);
    }

    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.45);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      z-index: 1050;
    }

    .modal-backdrop.is-open {
      display: flex;
    }

    .modal-card {
      width: min(480px, 100%);
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--tblr-border-radius-lg);
      box-shadow: var(--tblr-box-shadow-lg);
      padding: 1rem;
    }

    .modal-card h3 {
      margin: 0 0 8px;
      font-size: 18px;
    }

    .modal-card p {
      margin: 0 0 14px;
      line-height: 1.5;
    }

    .modal-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
    }

    #fileInput {
      display: none;
    }
  </style>
</head>
<body class="bg-body">
<div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 z-3 d-flex align-items-center justify-content-center bg-body p-3" role="status" aria-live="polite" aria-atomic="true">
  <div class="container-tight">
    <div class="card card-md shadow-lg">
      <div class="card-body">
        <div class="badge bg-primary-lt mb-3">
          <span class="spinner-grow spinner-grow-sm me-2" aria-hidden="true"></span>
          Blockly Loading
        </div>
        <h1 class="h2 mb-2">Blockly met data wordt geladen</h1>
        <p id="loadingSubtitle" class="text-secondary mb-3">De editor en bijbehorende gegevens worden voorbereid. Dit duurt soms een paar seconden.</p>
        <div id="loadingStage" class="alert alert-info mb-0 py-2">Initialiseren…</div>
      </div>
    </div>
  </div>
</div>
<div id="app">
  <div id="topbar">
    <div class="topbar-row topbar-row--main">
      <div id="title">
        <span class="avatar avatar-sm bg-primary-lt text-primary">
          <i class="ti ti-puzzle" aria-hidden="true"></i>
        </span>
        <span>Braille Activity Builder</span>
      </div>

      <input id="fileInput" type="file" accept=".blockly">

      <div class="btn-list ms-auto flex-nowrap">
        <?php if (!$isSessionPlayerEmbed): ?>
        <span class="navbar-text text-secondary d-none d-lg-inline">
          Ingelogd als <?= $html($authUser['display']) ?> (<?= $html($authUser['role']) ?>)
        </span>
        <form method="post" action="<?= $html($urlFor($appBase, 'authentication.php')) ?>" class="mb-0">
          <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
          <input type="hidden" name="action" value="logout">
          <input type="hidden" name="returnTo" value="<?= $html($urlFor($appBase, 'index.php')) ?>">
          <button class="btn btn-outline-secondary" type="submit">
            <i class="ti ti-logout me-2" aria-hidden="true"></i>
            Uitloggen
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="topbar-row topbar-row--sim">
      <button id="runBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Start" title="Start">
        <i class="ti ti-player-play" aria-hidden="true"></i>
      </button>
      <button id="stopBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Stop" title="Stop">
        <i class="ti ti-player-stop" aria-hidden="true"></i>
      </button>
      <div class="spacer"></div>
      <button id="gridSnapBtn" class="btn btn-outline-secondary btn-icon btn-lg active" type="button" aria-pressed="true" aria-label="Disable grid snap" title="Disable grid snap">
        <i class="ti ti-grid-dots" aria-hidden="true"></i>
      </button>
      <button id="monitorToggleBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-pressed="false" aria-label="Show monitor" title="Show monitor">
        <i class="ti ti-device-desktop" aria-hidden="true"></i>
      </button>
      <button id="sidebarToggleBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-pressed="true" aria-label="Hide status panel" title="Hide status panel">
        <i class="ti ti-layout-sidebar-right" aria-hidden="true"></i>
      </button>
    </div>

    <div id="brailleMonitorRow" class="topbar-row topbar-row--monitor is-hidden">
      <div id="brailleMonitorComponent" class="braille-monitor-host"></div>
    </div>

    <div id="scriptBrailleMonitorRow" class="topbar-row topbar-row--monitor is-hidden">
      <div id="scriptBrailleMonitorComponent" class="braille-monitor-host"></div>
    </div>

    <div id="thumbControlsRow" class="topbar-row topbar-row--thumbs is-hidden">
      <div class="thumb-controls">
        <button id="simThumbLeftBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Left thumb" title="Left thumb">
          <i class="ti ti-chevrons-left" aria-hidden="true"></i>
        </button>
        <button id="simCursor5Btn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Left middle thumb" title="Left middle thumb">
          <i class="ti ti-chevron-left" aria-hidden="true"></i>
        </button>
        <button id="simChord1Btn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Right middle thumb" title="Right middle thumb">
          <i class="ti ti-chevron-right" aria-hidden="true"></i>
        </button>
        <button id="simThumbRightBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Right thumb" title="Right thumb">
          <i class="ti ti-chevrons-right" aria-hidden="true"></i>
        </button>
      </div>
      <section
        class="topbar-bridge-status thumb-controls-status"
        data-braillebridge-status
        data-expanded="false"
        data-popup="true"
        data-ws-url="ws://localhost:5000/ws"
        data-launch-url="braillebridge://"
        data-auto-launch="true"
        aria-label="BrailleBridge status"
      ></section>
    </div>

    <div class="topbar-row topbar-row--scripts">
      <div class="topbar-script-group topbar-script-group--fields">
        <input id="onlineScriptIdInput" type="hidden">
        <input id="onlineScriptTitleInput" class="form-control form-control-lg w-auto" type="text" size="18" placeholder="Script title" required>
        <div class="topbar-script-picker">
          <select id="onlineScriptsSelect" class="form-select form-select-lg">
            <option value="">-- Select online script --</option>
          </select>
          <select id="onlineScriptStatusInput" class="form-select form-select-lg">
            <option value="draft">⚪ draft</option>
            <option value="started">🟡 started</option>
            <option value="in review">🔵 in review</option>
            <option value="approved">🟢 approved</option>
          </select>
          <button id="onlineRefreshBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Refresh online scripts" title="Refresh online scripts">
            <i class="ti ti-refresh" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="topbar-script-group topbar-script-group--actions">
        <button id="newBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="New script" title="New script">
          <i class="ti ti-file-plus" aria-hidden="true"></i>
        </button>
        <button id="copyJsonBtn" class="btn btn-outline-secondary btn-lg" type="button" aria-label="Export script JSON" title="Export script JSON to clipboard">
          <i class="ti ti-download me-2" aria-hidden="true"></i>
          Export JSON
        </button>
        <button id="importJsonBtn" class="btn btn-outline-secondary btn-lg" type="button" aria-label="Import script JSON" title="Import script JSON from clipboard">
          <i class="ti ti-upload me-2" aria-hidden="true"></i>
          Import JSON
        </button>
        <button id="onlineSaveBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Save script" title="Save script">
          <i class="ti ti-device-floppy" aria-hidden="true"></i>
        </button>
        <button id="onlineSaveAsBtn" class="btn btn-outline-primary btn-lg" type="button" aria-label="Save script as new copy" title="Save script as new copy">
          <i class="ti ti-copy-plus me-2" aria-hidden="true"></i>
          Save as
        </button>
        <button id="onlineDeleteBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Delete script" title="Delete script">
          <i class="ti ti-trash" aria-hidden="true"></i>
        </button>
        <button id="clearBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Clear workspace" title="Clear workspace">
          <i class="ti ti-eraser" aria-hidden="true"></i>
        </button>
        <button id="arrangeBtn" class="btn btn-outline-secondary btn-icon btn-lg" type="button" aria-label="Arrange blocks" title="Arrange blocks">
          <i class="ti ti-arrows-sort" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </div>

  <div id="main">
    <div id="workspaceWrap">
      <div id="blocklyDiv"></div>
    </div>

    <div id="mainDivider" class="main-divider" aria-hidden="true" title="Resize status panel"></div>

    <div id="sidebar">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Status</h3>
          <div class="card-actions">
            <button
              id="runtimeStatusToggleBtn"
              class="btn btn-outline-secondary btn-icon"
              type="button"
              aria-controls="statusBox"
              aria-expanded="false"
              aria-label="Toon technische status"
              title="Toon technische status"
            >
              <i class="ti ti-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>
        <div class="card-body">
        <div class="status-card-script-name" title="Script-id">
          <i class="ti ti-file-code" aria-hidden="true"></i>
          <span id="statusScriptName">Geen script geopend</span>
        </div>
        <textarea id="scriptMetaDescription" class="form-control meta-textarea meta-textarea--compact" placeholder="Description / notes" style="margin-bottom:8px;"></textarea>
        <textarea id="scriptTextInput" class="form-control meta-textarea meta-textarea--compact" placeholder="Instructie — alleen opslaan bij het script" aria-label="Instructie" style="margin-bottom:8px;"></textarea>
        <textarea id="scriptMemoInput" class="form-control meta-textarea meta-textarea--compact" placeholder="Memo — één lijst- en audio-item per regel" aria-label="Memo voor lijst en audio" style="margin-bottom:8px;"></textarea>
        <button
          id="insertInstructionTextListBtn"
          class="btn btn-outline-primary btn-icon"
          type="button"
          aria-label="Maak lijst"
          title="Maak lijst van de memoregels"
          style="margin-bottom:8px;"
        >
          <i class="ti ti-list" aria-hidden="true"></i>
        </button>
        <div class="instruction-tts-controls">
          <select id="instructionTtsVoiceSelect" class="form-select" style="min-width:0;">
            <option value="yO6w2xlECAQRFP6pX7Hw">Ruth (NL)</option>
            <option value="UNBIyLbtFB9k7FKW8wJv">Serge (NL)</option>
          </select>
          <button
            id="instructionTtsVoiceInfoBtn"
            class="btn btn-outline-secondary btn-icon"
            type="button"
            aria-label="Information about selected ElevenLabs voice"
            title="Voice information from ElevenLabs"
          >?</button>
          <select
            id="instructionTtsSpacePauseSelect"
            class="form-select"
            aria-label="Pause at spaces"
            title="Replace each space with an ElevenLabs pause"
            style="min-width:0;"
          >
            <option value="">No pause at spaces</option>
            <option value="[short pause]">Short pause at spaces</option>
            <option value="[long pause]">Long pause at spaces</option>
          </select>
          <button id="saveInstructionTtsBtn" class="btn btn-primary" type="button" disabled>Maak audio</button>
        </div>
        <div class="small" style="margin-top:-4px; margin-bottom:4px;">ElevenLabs model: Eleven v3 · language: Dutch (nl)</div>
        <div id="instructionTtsStatus" class="small" style="margin-bottom:8px;">Open een online script om audio van de memo te maken.</div>
        <div id="statusBox" class="mono" hidden></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Log</h3>
          <div class="card-actions">
            <button id="copyLogBtn" class="btn btn-outline-secondary btn-icon" type="button" aria-label="Copy log" title="Copy log">
              <i class="ti ti-copy" aria-hidden="true"></i>
            </button>
            <button id="clearLogBtn" class="btn btn-outline-secondary btn-icon" type="button" aria-label="Clear log" title="Clear log">
              <i class="ti ti-eraser" aria-hidden="true"></i>
            </button>
          </div>
        </div>
        <div class="card-body">
          <textarea id="logBox" class="mono" aria-label="Log"></textarea>
        </div>
      </div>

    </div>
  </div>
</div>

<div id="variableModal" class="modal-backdrop" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="variableModalTitle">
    <h3 id="variableModalTitle">Variable</h3>
    <input id="variableEditId" type="hidden">
    <input id="variableScopeExternal" type="hidden" value="external">
    <input id="variableNameInput" class="form-control" type="text" placeholder="Name" style="margin-bottom:8px;">
    <select id="variableTypeInput" class="form-select" style="margin-bottom:8px;">
      <option value="string">string</option>
      <option value="number">number</option>
      <option value="boolean">boolean</option>
      <option value="array">array</option>
      <option value="object">object</option>
    </select>
    <textarea id="variableDefaultInput" class="form-control meta-textarea--compact" placeholder="Default value" style="margin-bottom:8px;"></textarea>
    <textarea id="variableDescriptionInput" class="form-control meta-textarea--compact" placeholder="Description / comment" style="margin-bottom:8px;"></textarea>
    <div class="modal-actions">
      <button id="variableModalDelete" class="btn btn-outline-danger me-auto" type="button">Delete</button>
      <button id="variableModalCancel" class="btn btn-outline-secondary" type="button">Cancel</button>
      <button id="variableModalSave" class="btn btn-primary" type="button">Save</button>
    </div>
  </div>
</div>

<div id="variableContextMenu" class="variable-context-menu" role="menu" aria-hidden="true">
  <button id="variableContextEdit" class="btn btn-ghost-secondary btn-sm" type="button" role="menuitem">
    <i class="ti ti-pencil me-2" aria-hidden="true"></i>
    Edit variable
  </button>
  <button id="variableContextDelete" class="btn btn-ghost-danger btn-sm" type="button" role="menuitem">
    <i class="ti ti-trash me-2" aria-hidden="true"></i>
    Delete variable
  </button>
</div>

<div id="confirmModal" class="modal-backdrop" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <h3 id="confirmModalTitle">Unsaved changes</h3>
    <p id="confirmModalMessage">You have unsaved changes.</p>
    <div class="modal-actions">
      <button id="confirmModalCancel" class="btn btn-outline-secondary" type="button">Cancel</button>
      <button id="confirmModalDiscard" class="btn btn-outline-danger" type="button">Don't Save</button>
      <button id="confirmModalSave" class="btn btn-primary" type="button">Save</button>
    </div>
  </div>
</div>

<div id="instructionTtsVoiceInfoModal" class="modal-backdrop" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="instructionTtsVoiceInfoTitle">
    <h3 id="instructionTtsVoiceInfoTitle">ElevenLabs voice information</h3>
    <div id="instructionTtsVoiceInfoContent" class="small">Loading voice information...</div>
    <audio id="instructionTtsVoicePreview" controls style="display:none; width:100%; margin-top:12px;"></audio>
    <div class="modal-actions">
      <button id="instructionTtsVoiceInfoClose" class="btn btn-primary" type="button">Close</button>
    </div>
  </div>
</div>

<xml id="toolbox" style="display:none">
  <category name="My Blocks" custom="MY_BLOCKS_LIBRARY" colour="#0F766E"></category>
  <category name="Events" colour="#F59E0B">
    <block type="event_when_started"></block>
    <block type="event_when_program_ended"></block>
    <block type="event_end_program"></block>
    <block type="event_when_timer"></block>
    <block type="event_when_thumb_key"></block>
    <block type="event_when_any_thumb_key"></block>
    <block type="event_when_cursor_routing"></block>
    <block type="event_when_cursor_position_changed"></block>
    <block type="event_when_chord"></block>
    <block type="event_when_editor_key"></block>
    <block type="event_when_key_name"></block>
  </category>

  <category name="BrailleBridge" colour="#2563EB">
    <block type="bb_set_text">
      <value name="TEXT">
        <shadow type="text">
          <field name="TEXT">bal</field>
        </shadow>
      </value>
    </block>
    <block type="bb_append_text">
      <value name="TEXT">
        <shadow type="text">
          <field name="TEXT">bal</field>
        </shadow>
      </value>
    </block>
    <block type="bb_send_key"></block>
    <block type="bb_move_caret">
      <value name="DELTA">
        <shadow type="math_number">
          <field name="NUM">1</field>
        </shadow>
      </value>
    </block>
    <block type="bb_set_caret">
      <value name="INDEX">
        <shadow type="math_number">
          <field name="NUM">0</field>
        </shadow>
      </value>
    </block>
    <block type="bb_set_caret_from_cell">
      <value name="INDEX">
        <shadow type="math_number">
          <field name="NUM">0</field>
        </shadow>
      </value>
    </block>
    <block type="bb_cursor_routing">
      <value name="INDEX">
        <shadow type="math_number">
          <field name="NUM">0</field>
        </shadow>
      </value>
    </block>
    <block type="bb_set_editor_mode"></block>
    <block type="bb_set_insert_mode"></block>
    <block type="bb_set_caret_visibility"></block>
    <block type="bb_get_braille_line"></block>
    <block type="bb_current_text"></block>
    <block type="bb_current_braille_unicode"></block>
    <block type="bb_letter_under_cursor"></block>
    <block type="bb_word_under_cursor"></block>
    <block type="bb_set_caret_to_begin"></block>
    <block type="bb_set_caret_to_end"></block>
  </category>

  <category name="Timers" colour="#0EA5E9">
    <block type="timer_start">
      <value name="SECONDS">
        <shadow type="math_number">
          <field name="NUM">1</field>
        </shadow>
      </value>
    </block>
    <block type="timer_stop"></block>
    <block type="timer_stop_all"></block>
    <block type="bb_wait">
      <value name="SECONDS">
        <shadow type="math_number">
          <field name="NUM">1</field>
        </shadow>
      </value>
    </block>
    <block type="bb_wait_ms">
      <value name="MILLISECONDS">
        <shadow type="math_number">
          <field name="NUM">250</field>
        </shadow>
      </value>
    </block>
    <block type="state_last_timer_name"></block>
    <block type="state_last_timer_tick"></block>
  </category>

  <category name="Play" colour="#10B981">
    <block type="sound_play_folder_file">
      <value name="FILE">
        <shadow type="text">
          <field name="TEXT">bal</field>
        </shadow>
      </value>
    </block>
    <block type="sound_play_url">
      <value name="URL">
        <shadow type="text">
          <field name="TEXT">https://www.tastenbraille.com/braillestudio-data/sounds/nl/letters/b.mp3</field>
        </shadow>
      </value>
    </block>
    <block type="sound_play_sounds_relative">
      <value name="PATH">
        <shadow type="text">
          <field name="TEXT">../nl/instructions/voorbeeld.mp3</field>
        </shadow>
      </value>
    </block>
    <block type="sound_play_ux_file">
      <value name="FILE">
        <shadow type="text">
          <field name="TEXT">bounce</field>
        </shadow>
      </value>
    </block>
    <block type="sound_play_ux_success"></block>
    <block type="sound_play_ux_failure"></block>
    <block type="sound_pause"></block>
    <block type="sound_resume"></block>
    <block type="sound_stop"></block>
    <block type="sound_wait_stopped"></block>
    <block type="sound_set_volume">
      <value name="VOLUME">
        <shadow type="math_number">
          <field name="NUM">100</field>
        </shadow>
      </value>
    </block>
    <block type="state_last_sound"></block>
  </category>

  <category name="Library" colour="#0EA5E9">
    <block type="klanken_get_speech_audio_by_onlyletters">
      <value name="ONLYLETTERS">
        <shadow type="text">
          <field name="TEXT">b,a,l</field>
        </shadow>
      </value>
    </block>
    <block type="audio_get_speech_audio_by_letters_klanken">
      <value name="LETTERS">
        <shadow type="text">
          <field name="TEXT">a,b,k,l,r,d</field>
        </shadow>
      </value>
      <value name="KLANKEN">
        <shadow type="text">
          <field name="TEXT">aa</field>
        </shadow>
      </value>
    </block>
    <block type="audio_get_speech_audio_by_onlyletters_klanken_length">
      <value name="ONLYLETTERS">
        <shadow type="text">
          <field name="TEXT">p,a,r,d,i,e,l,b,r</field>
        </shadow>
      </value>
      <value name="KLANKEN">
        <shadow type="text">
          <field name="TEXT">aa</field>
        </shadow>
      </value>
      <value name="LENGTH">
        <shadow type="math_number">
          <field name="NUM">5</field>
        </shadow>
      </value>
    </block>
    <block type="audio_get_speech_audio_by_onlyletters_length">
      <value name="ONLYLETTERS">
        <shadow type="text">
          <field name="TEXT">p,a,r,d,i,e,l,b,r</field>
        </shadow>
      </value>
      <value name="LENGTH">
        <shadow type="math_number">
          <field name="NUM">5</field>
        </shadow>
      </value>
    </block>
    <block type="list_pick_random"></block>
    <block type="audio_item_get_word">
      <value name="ITEM">
        <shadow type="text">
          <field name="TEXT">bal</field>
        </shadow>
      </value>
    </block>
    <block type="audio_item_get_url">
      <value name="ITEM">
        <shadow type="text">
          <field name="TEXT">bal</field>
        </shadow>
      </value>
    </block>
  </category>

  <category name="Lesson" colour="#14B8A6">
    <block type="lesson_track_progress"></block>
    <block type="lesson_progress_data"></block>
  </category>

  <category name="Phonemes" colour="#14B8A6">
    <block type="klanken_get_aanvankelijklijst"></block>
    <block type="klanken_split_word_phonemes_nl">
      <value name="WORD">
        <shadow type="text">
          <field name="TEXT">bal</field>
        </shadow>
      </value>
    </block>
    <block type="klanken_split_text_phonemes_nl">
      <value name="TEXT">
        <shadow type="text">
          <field name="TEXT">bal en raam</field>
        </shadow>
      </value>
    </block>
    <block type="klanken_play_word_phonemes_nl">
      <value name="WORD">
        <shadow type="text">
          <field name="TEXT">bal</field>
        </shadow>
      </value>
    </block>
    <block type="klanken_play_word_phonemes_nl_with_pause">
      <value name="WORD">
        <shadow type="text">
          <field name="TEXT">bal</field>
        </shadow>
      </value>
      <value name="SECONDS">
        <shadow type="math_number">
          <field name="NUM">0.5</field>
        </shadow>
      </value>
    </block>
  </category>

  <category name="Logic" colour="#16A34A">
    <block type="controls_if"></block>
    <block type="controls_if">
      <mutation else="1"></mutation>
    </block>
    <block type="logic_compare"></block>
    <block type="logic_operation"></block>
    <block type="logic_negate"></block>
    <block type="logic_boolean"></block>
    <block type="logic_random_boolean"></block>
  </category>

  <category name="Loops" colour="#D97706">
    <block type="controls_repeat_ext">
      <value name="TIMES">
        <shadow type="math_number">
          <field name="NUM">3</field>
        </shadow>
      </value>
    </block>
    <block type="controls_while_do"></block>
    <block type="controls_do_while"></block>
    <block type="list_for_each_item"></block>
  </category>

  <category name="Procedures" custom="PROCEDURE" colour="#BE185D"></category>

  <category name="Lists" colour="#F97316">
    <block type="list_make"></block>
    <block type="list_empty"></block>
    <block type="list_from_text_items"></block>
    <block type="list_filter_text_length">
      <value name="MIN">
        <shadow type="math_number">
          <field name="NUM">1</field>
        </shadow>
      </value>
    </block>
    <block type="list_filter_phoneme_category"></block>
    <block type="list_filter_phoneme_categories"></block>
    <block type="list_shuffle"></block>
    <block type="list_sort"></block>
    <block type="list_sort_by_length"></block>
    <block type="list_get_item"></block>
    <block type="list_set_item"></block>
    <block type="list_nrof_items"></block>
    <block type="list_random_item"></block>
    <block type="list_next_item"></block>
    <block type="list_contains_item"></block>
    <block type="list_add_item"></block>
    <block type="list_remove_item"></block>
    <block type="list_random_other_item"></block>
  </category>

  <category name="State" colour="#7C3AED">
    <block type="state_text_caret"></block>
    <block type="state_cell_caret"></block>
    <block type="state_last_thumb_key"></block>
    <block type="state_last_cursor_cell"></block>
    <block type="state_last_chord"></block>
    <block type="state_last_editor_key"></block>
    <block type="state_editor_mode"></block>
    <block type="state_insert_mode"></block>
  </category>

  <category name="Math" colour="#C026D3">
    <block type="math_number"></block>
    <block type="math_arithmetic"></block>
    <block type="math_random_10">
      <value name="MAX">
        <shadow type="math_number">
          <field name="NUM">10</field>
        </shadow>
      </value>
    </block>
    <block type="math_inc_var"></block>
    <block type="math_dec_var"></block>
    <block type="math_inc_nrof"></block>
    <block type="control_wait_until_nrof"></block>
  </category>

  <category name="Logging" colour="#64748B">
    <block type="log_value"></block>
    <block type="log_variable"></block>
    <block type="log_clear"></block>
  </category>

  <category name="Text" colour="#0891B2">
    <block type="text"></block>
    <block type="text_join"></block>
    <block type="text_concat"></block>
    <block type="text_contains">
      <value name="FIND">
        <shadow type="text">
          <field name="TEXT">l</field>
        </shadow>
      </value>
    </block>
    <block type="text_from_list">
      <value name="SEPARATOR">
        <shadow type="text">
          <field name="TEXT"> </field>
        </shadow>
      </value>
    </block>
    <block type="text_first_letter"></block>
    <block type="text_last_letter"></block>
    <block type="text_lowercase"></block>
    <block type="text_uppercase"></block>
  </category>

  <category name="Variables" custom="VARIABLE" colour="#EA580C"></category>
  <category name="Global Variables" colour="#7C3AED">
    <label text="Global values are shared within this browser session."></label>
    <block type="global_student_code_get"></block>
    <block type="global_student_code_set">
      <value name="VALUE">
        <shadow type="text">
          <field name="TEXT"></field>
        </shadow>
      </value>
    </block>
  </category>
  <category name="External Variables" colour="#2563EB">
    <button text="Add external variable" callbackKey="ADD_EXTERNAL_VARIABLE"></button>
    <label text="External variables are runtime input/context."></label>
    <block type="external_variable_get"></block>
    <block type="external_variable_set">
      <value name="VALUE">
        <shadow type="text">
          <field name="TEXT"></field>
        </shadow>
      </value>
    </block>
  </category>
</xml>

<script src="./runtime.js?v=20260609-static-sounds-1"></script>
<script src="./instructions-catalog.js?v=20260416-session-player-3"></script>
<script src="./preload-instructions.js?v=20260416-session-player-3"></script>
<script src="../tabler/core/dist/js/tabler.min.js"></script>
<script>
  (function () {
    const overlay = document.getElementById('loadingOverlay');
    const subtitle = document.getElementById('loadingSubtitle');
    const stage = document.getElementById('loadingStage');
    if (!overlay || !subtitle || !stage) return;

    const stageLabels = {
      'index-html': 'Startscherm wordt opgebouwd…',
      'app-script-start': 'Blockly scripts worden gestart…',
      'before-workspace-inject': 'Werkruimte wordt opgebouwd…',
      'workspace-injected': 'Blockly interface is geplaatst…',
      'workspace-ready': 'Basisdata wordt geladen…',
      'api-ready': 'Alles staat klaar.'
    };

    function applyBootState(state) {
      const current = state || window.BrailleBlocklyBoot || {};
      const currentStage = String(current.stage || 'index-html');
      const error = String(current.error || '').trim();
      const appReady = Boolean(window.BrailleBlocklyApp) && currentStage === 'api-ready' && !error;
      const label = stageLabels[currentStage] || `Laden: ${currentStage}`;
      stage.textContent = error ? `Fout: ${error}` : label;
      subtitle.textContent = error
        ? 'Blockly kon niet volledig laden. Controleer de foutmelding hieronder of ververs de pagina.'
        : 'De editor en bijbehorende gegevens worden voorbereid. Dit duurt soms een paar seconden.';
      stage.className = error ? 'alert alert-danger mb-0 py-2' : 'alert alert-info mb-0 py-2';
      overlay.classList.toggle('d-none', appReady);
    }

    window.addEventListener('braille-blockly-boot-stage', (event) => {
      applyBootState(event.detail || {});
    });

    applyBootState(window.BrailleBlocklyBoot || {});

    const readyPoll = window.setInterval(() => {
      const current = window.BrailleBlocklyBoot || {};
      const currentStage = String(current.stage || '');
      const error = String(current.error || '').trim();
      if (error) {
        applyBootState(current);
        window.clearInterval(readyPoll);
        return;
      }
      if (window.BrailleBlocklyApp && currentStage === 'api-ready') {
        applyBootState(current);
        window.clearInterval(readyPoll);
      }
    }, 150);
  })();
</script>
<script>
  (async function () {
    const assetVersion = '20260608-ws-only-1';

    async function loadScript(src) {
      await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        const separator = src.includes('?') ? '&' : '?';
        script.src = `${src}${separator}v=${assetVersion}`;
        script.onload = resolve;
        script.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.body.appendChild(script);
      });
    }

    async function loadScriptCandidates(candidates, { required = true } = {}) {
      let lastError = null;
      for (const src of candidates) {
        try {
          await loadScript(src);
          return src;
        } catch (err) {
          lastError = err;
        }
      }
      if (required) {
        throw lastError || new Error(`Failed to load script candidates: ${candidates.join(', ')}`);
      }
      console.warn('Optional script could not be loaded', {
        candidates,
        error: lastError?.message || String(lastError || 'unknown error')
      });
      return null;
    }

    if (window.BrailleStudioInstructionCatalogReady) {
      await window.BrailleStudioInstructionCatalogReady;
    }

    await loadScript('./blocks.js?v=20260612-global-student-code-1');
    await loadScript('./generators.js?v=20260612-global-student-code-1');
    await loadScriptCandidates([
      '../components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1',
      '/braillestudio/components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1',
      'https://www.tastenbraille.com/braillestudio/components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1'
    ], { required: false });
    await loadScriptCandidates([
        '../components/braillebridge-status/braillebridge-status.js?v=20260612-runtime-status-4',
        '/braillestudio/components/braillebridge-status/braillebridge-status.js?v=20260612-runtime-status-4',
        'https://www.tastenbraille.com/braillestudio/components/braillebridge-status/braillebridge-status.js?v=20260612-runtime-status-4'
    ], { required: false });
    await loadScript('./app.js?v=20260619-textarea-clipboard-shortcuts-1');
  })().catch((err) => {
    console.error('Blockly bootstrap failed', err);
    if (typeof window.__setBrailleBlocklyBootStage === 'function') {
      window.__setBrailleBlocklyBootStage('error', { error: err.message || String(err) });
    }
  });
</script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
