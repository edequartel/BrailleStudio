<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/language.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$baseUrl = preg_replace('~/demo$~', '', $scriptDir) ?? '';
$assetBase = ($baseUrl === '' ? '..' : $baseUrl);

function demo_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function demo_j(string $key, array $params = []): string
{
    return json_encode(t($key, $params), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
}
?>
<!doctype html>
<html <?= bs_language_html_attrs() ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= demo_h(t('demo.braillebridge.page_title')) ?></title>
  <link rel="icon" href="<?= demo_h($assetBase) ?>/favicon.ico" sizes="any">
  <link rel="stylesheet" href="<?= demo_h($assetBase) ?>/tabler/core/dist/css/tabler.min.css">
  <link rel="stylesheet" href="<?= demo_h($assetBase) ?>/tabler/icons-webfont/dist/tabler-icons.min.css">
  <link rel="stylesheet" href="<?= demo_h($assetBase) ?>/components/braillebridge-status/braillebridge-status.css?v=20260706-popup-text-2">
  <link rel="stylesheet" href="<?= demo_h($assetBase) ?>/components/braille-monitor/braillemonitor.css">
  <style>
    .demo-flow {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 1rem;
    }
    .demo-flow-step {
      border: 1px solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      padding: 1rem;
      background: var(--tblr-bg-surface);
      min-height: 8rem;
    }
    .demo-flow-icon {
      width: 2.5rem;
      height: 2.5rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
    }
    .demo-log {
      min-height: 18rem;
      max-height: 28rem;
      overflow: auto;
      white-space: pre-wrap;
      font-size: .82rem;
    }
    .demo-json {
      min-height: 12rem;
      max-height: 24rem;
      overflow: auto;
      white-space: pre-wrap;
      font-size: .82rem;
    }
    .demo-status-actions {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      align-items: end;
    }
    .demo-status-card-body {
      padding-top: 1.25rem;
    }
    .demo-status-grid {
      display: grid;
      grid-template-columns: minmax(18rem, 1fr) auto;
      gap: .75rem;
      align-items: end;
      max-width: 76rem;
    }
    .demo-connection-toolbar {
      display: flex;
      gap: .5rem;
      align-items: stretch;
    }
    .demo-bridge-popup {
      flex: 0 0 auto;
      width: 3rem;
      min-height: 2.5rem;
    }
    .demo-status-badges {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      margin-top: .75rem;
    }
    .demo-interactive-mode-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
      align-items: stretch;
    }
    .demo-caret-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
      align-items: stretch;
    }
    .demo-command-box {
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-bg-surface-secondary);
      padding: 1rem;
      height: 100%;
    }
    .demo-command-title {
      margin: 0 0 .35rem;
      font-size: .95rem;
      font-weight: 700;
    }
    .demo-command-help {
      min-height: 2.75rem;
      margin-bottom: .75rem;
      color: var(--tblr-muted);
      font-size: .875rem;
    }
    .demo-caret-actions {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      align-items: center;
    }
    .demo-routing-help {
      margin-top: .75rem;
    }
    @media (max-width: 991.98px) {
      .demo-flow {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .demo-caret-grid {
        grid-template-columns: 1fr;
      }
      .demo-interactive-mode-grid {
        grid-template-columns: 1fr;
      }
      .demo-status-grid {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 575.98px) {
      .demo-flow {
        grid-template-columns: 1fr;
      }
      .demo-mode-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<div class="page">
  <header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
      <a class="navbar-brand navbar-brand-autodark" href="<?= demo_h($assetBase) ?>/index.php">
        <img src="<?= demo_h($assetBase) ?>/style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="<?= demo_h($assetBase) ?>/style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
      </a>
      <div class="navbar-nav flex-row align-items-center ms-auto">
        <?= language_switcher('me-2') ?>
        <a class="btn btn-outline-secondary" href="<?= demo_h($assetBase) ?>/index.php">
          <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
          <?= demo_h(t('common.back_home')) ?>
        </a>
      </div>
    </div>
  </header>

  <main class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row g-3 align-items-center">
          <div class="col">
            <div class="page-pretitle"><?= demo_h(t('demo.braillebridge.pretitle')) ?></div>
            <h1 class="page-title"><?= demo_h(t('demo.braillebridge.title')) ?></h1>
            <div class="text-secondary mt-2"><?= demo_h(t('demo.braillebridge.subtitle')) ?></div>
          </div>
          <div class="col-auto">
            <div class="btn-list">
              <a class="btn btn-primary" href="braillebridge://">
                <i class="ti ti-plug-connected me-2" aria-hidden="true"></i>
                <?= demo_h(t('demo.braillebridge.open_bridge')) ?>
              </a>
              <a class="btn btn-outline-secondary" href="<?= demo_h($assetBase) ?>/demo/braillebridge-handleiding.php">
                <i class="ti ti-book me-2" aria-hidden="true"></i>
                <?= demo_h(t('demo.braillebridge.manual_button')) ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <section class="card mb-3">
          <div class="card-body">
            <div class="demo-flow" aria-label="<?= demo_h(t('demo.braillebridge.flow_title')) ?>">
              <div class="demo-flow-step">
                <span class="demo-flow-icon rounded bg-primary-lt text-primary mb-3"><i class="ti ti-browser" aria-hidden="true"></i></span>
                <h2 class="h3"><?= demo_h(t('demo.braillebridge.flow.browser.title')) ?></h2>
                <p class="text-secondary mb-0"><?= demo_h(t('demo.braillebridge.flow.browser.text')) ?></p>
              </div>
              <div class="demo-flow-step">
                <span class="demo-flow-icon rounded bg-orange-lt text-orange mb-3"><i class="ti ti-plug-connected" aria-hidden="true"></i></span>
                <h2 class="h3"><?= demo_h(t('demo.braillebridge.flow.bridge.title')) ?></h2>
                <p class="text-secondary mb-0"><?= demo_h(t('demo.braillebridge.flow.bridge.text')) ?></p>
              </div>
              <div class="demo-flow-step">
                <span class="demo-flow-icon rounded bg-green-lt text-green mb-3"><i class="ti ti-exchange" aria-hidden="true"></i></span>
                <h2 class="h3"><?= demo_h(t('demo.braillebridge.flow.sam.title')) ?></h2>
                <p class="text-secondary mb-0"><?= demo_h(t('demo.braillebridge.flow.sam.text')) ?></p>
              </div>
              <div class="demo-flow-step">
                <span class="demo-flow-icon rounded bg-purple-lt text-purple mb-3"><i class="ti ti-device-desktop" aria-hidden="true"></i></span>
                <h2 class="h3"><?= demo_h(t('demo.braillebridge.flow.display.title')) ?></h2>
                <p class="text-secondary mb-0"><?= demo_h(t('demo.braillebridge.flow.display.text')) ?></p>
              </div>
            </div>
          </div>
        </section>

        <div class="row row-cards">
          <div class="col-12">
            <section class="card h-100">
              <div class="card-header">
                <h2 class="card-title"><?= demo_h(t('demo.braillebridge.status.title')) ?></h2>
              </div>
              <div class="card-body demo-status-card-body">
                <div class="demo-status-grid">
                  <div>
                    <label class="form-label" for="wsUrl"><?= demo_h(t('demo.braillebridge.ws_url')) ?></label>
                    <div class="demo-connection-toolbar">
                      <div class="flex-fill">
                        <input id="wsUrl" class="form-control font-monospace" type="text" value="ws://localhost:5000/ws">
                      </div>
                    </div>
                    <div class="form-hint mt-2"><?= demo_h(t('demo.braillebridge.auto_connection_hint')) ?></div>
                  </div>
                  <div
                    class="demo-bridge-popup"
                    data-braillebridge-status
                    data-expanded="false"
                    data-popup="true"
                    data-ws-url="ws://localhost:5000/ws"
                    data-launch-url="braillebridge://"
                    data-auto-launch="true"
                    aria-label="BrailleBridge status"
                  ></div>
                </div>
                <div class="demo-status-badges" aria-label="<?= demo_h(t('demo.braillebridge.status_badges')) ?>">
                  <span id="wsBadge" class="badge bg-danger-lt"><?= demo_h(t('demo.braillebridge.offline')) ?></span>
                  <span id="editorBadge" class="badge bg-warning-lt"><?= demo_h(t('demo.braillebridge.editor_unknown')) ?></span>
                  <span id="insertBadge" class="badge bg-warning-lt"><?= demo_h(t('demo.braillebridge.insert_unknown')) ?></span>
                </div>
              </div>
            </section>
          </div>

          <div class="col-12">
            <section class="card h-100">
              <div class="card-header">
                <h2 class="card-title"><?= demo_h(t('demo.braillebridge.monitor.title')) ?></h2>
                <div class="card-actions">
                  <button id="getLineBtn" class="btn btn-outline-secondary btn-sm" type="button"><?= demo_h(t('demo.braillebridge.get_line')) ?></button>
                </div>
              </div>
              <div class="card-body">
                <div id="brailleMonitor"></div>
              </div>
            </section>
          </div>

          <div class="col-12">
            <section class="card">
              <div class="card-header">
                <h2 class="card-title"><?= demo_h(t('demo.braillebridge.controls.title')) ?></h2>
              </div>
              <div class="card-body">
                <div class="demo-interactive-mode-grid mb-3">
                  <div class="demo-command-box">
                    <h3 class="demo-command-title"><?= demo_h(t('demo.braillebridge.controls.editor_mode')) ?></h3>
                    <div class="demo-caret-actions">
                      <button id="editorOnBtn" class="btn btn-outline-primary" type="button"><?= demo_h(t('demo.braillebridge.editor_on')) ?></button>
                      <button id="editorOffBtn" class="btn btn-outline-secondary" type="button"><?= demo_h(t('demo.braillebridge.editor_off')) ?></button>
                    </div>
                  </div>
                  <div class="demo-command-box">
                    <h3 class="demo-command-title"><?= demo_h(t('demo.braillebridge.controls.insert_mode')) ?></h3>
                    <div class="demo-caret-actions">
                      <button id="insertOnBtn" class="btn btn-outline-primary" type="button"><?= demo_h(t('demo.braillebridge.insert_on')) ?></button>
                      <button id="insertOffBtn" class="btn btn-outline-secondary" type="button"><?= demo_h(t('demo.braillebridge.insert_off')) ?></button>
                    </div>
                  </div>
                  <div class="demo-command-box">
                    <h3 class="demo-command-title"><?= demo_h(t('demo.braillebridge.controls.caret_visibility')) ?></h3>
                    <div class="demo-caret-actions">
                      <button id="caretOnBtn" class="btn btn-outline-primary" type="button"><?= demo_h(t('demo.braillebridge.caret_on')) ?></button>
                      <button id="caretOffBtn" class="btn btn-outline-secondary" type="button"><?= demo_h(t('demo.braillebridge.caret_off')) ?></button>
                    </div>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-12 col-lg-6">
                    <label class="form-label" for="textInput"><?= demo_h(t('demo.braillebridge.text_to_send')) ?></label>
                    <input id="textInput" class="form-control" type="text" value="<?= demo_h(t('demo.braillebridge.sample_text')) ?>">
                    <div class="btn-list mt-2">
                      <button id="sendTextBtn" class="btn btn-primary" type="button"><?= demo_h(t('demo.braillebridge.send_text')) ?></button>
                      <button id="clearBtn" class="btn btn-outline-secondary" type="button"><?= demo_h(t('demo.braillebridge.clear_display')) ?></button>
                    </div>
                  </div>
                  <div class="col-12 col-lg-6">
                    <label class="form-label" for="keyInput"><?= demo_h(t('demo.braillebridge.key_to_send')) ?></label>
                    <select id="keyInput" class="form-select">
                      <option>Backspace</option>
                      <option>Delete</option>
                      <option>Space</option>
                      <option>Enter</option>
                      <option>ArrowLeft</option>
                      <option>ArrowRight</option>
                      <option>ArrowUp</option>
                      <option>ArrowDown</option>
                    </select>
                    <button id="sendKeyBtn" class="btn btn-outline-primary mt-2" type="button"><?= demo_h(t('demo.braillebridge.send_key')) ?></button>
                  </div>
                </div>

                <hr>

                <h3 class="h4 mb-3"><?= demo_h(t('demo.braillebridge.controls.caret')) ?></h3>
                <div class="demo-caret-grid">
                  <div class="demo-command-box">
                    <h4 class="demo-command-title"><?= demo_h(t('demo.braillebridge.text_position_title')) ?></h4>
                    <div class="demo-command-help"><?= demo_h(t('demo.braillebridge.text_position_help')) ?></div>
                    <label class="form-label" for="caretTextIndex"><?= demo_h(t('demo.braillebridge.caret_text_index')) ?></label>
                    <input id="caretTextIndex" class="form-control font-monospace" type="number" min="0" value="0" aria-describedby="caretTextIndexHelp">
                    <div id="caretTextIndexHelp" class="form-hint"><?= demo_h(t('demo.braillebridge.caret_text_index_help')) ?></div>
                    <div class="demo-caret-actions mt-2">
                      <button id="setCaretBtn" class="btn btn-outline-primary" type="button"><?= demo_h(t('demo.braillebridge.set_caret')) ?></button>
                      <button id="moveLeftBtn" class="btn btn-outline-secondary" type="button"><?= demo_h(t('demo.braillebridge.move_left')) ?></button>
                      <button id="moveRightBtn" class="btn btn-outline-secondary" type="button"><?= demo_h(t('demo.braillebridge.move_right')) ?></button>
                      <button id="beginBtn" class="btn btn-outline-secondary" type="button"><?= demo_h(t('demo.braillebridge.begin')) ?></button>
                      <button id="endBtn" class="btn btn-outline-secondary" type="button"><?= demo_h(t('demo.braillebridge.end')) ?></button>
                    </div>
                  </div>
                  <div class="demo-command-box">
                    <h4 class="demo-command-title"><?= demo_h(t('demo.braillebridge.cell_position_title')) ?></h4>
                    <div class="demo-command-help"><?= demo_h(t('demo.braillebridge.cell_position_help')) ?></div>
                    <label class="form-label" for="caretCellIndex"><?= demo_h(t('demo.braillebridge.caret_cell_index')) ?></label>
                    <input id="caretCellIndex" class="form-control font-monospace" type="number" min="0" value="0" aria-describedby="caretCellIndexHelp">
                    <div id="caretCellIndexHelp" class="form-hint"><?= demo_h(t('demo.braillebridge.caret_cell_index_help')) ?></div>
                    <div class="demo-caret-actions mt-2">
                      <button id="fromCellBtn" class="btn btn-outline-primary" type="button"><?= demo_h(t('demo.braillebridge.from_cell')) ?></button>
                      <button id="routingBtn" class="btn btn-outline-secondary" type="button" aria-describedby="cursorRoutingHelp"><?= demo_h(t('demo.braillebridge.cursor_routing')) ?></button>
                    </div>
                    <div id="cursorRoutingHelp" class="form-hint demo-routing-help"><?= demo_h(t('demo.braillebridge.cursor_routing_help')) ?></div>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div class="col-12 col-xl-6">
            <section class="card h-100">
              <div class="card-header">
                <h2 class="card-title"><?= demo_h(t('demo.braillebridge.incoming.title')) ?></h2>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-6"><label class="form-label" for="eventType"><?= demo_h(t('demo.braillebridge.event_type')) ?></label><input id="eventType" class="form-control font-monospace" readonly></div>
                  <div class="col-6"><label class="form-label" for="eventOk"><?= demo_h(t('demo.braillebridge.event_ok')) ?></label><input id="eventOk" class="form-control font-monospace" readonly></div>
                  <div class="col-6"><label class="form-label" for="eventCursorCell"><?= demo_h(t('demo.braillebridge.caret_cell_index')) ?></label><input id="eventCursorCell" class="form-control font-monospace" readonly></div>
                  <div class="col-6"><label class="form-label" for="eventCursorText"><?= demo_h(t('demo.braillebridge.caret_text_index')) ?></label><input id="eventCursorText" class="form-control font-monospace" readonly></div>
                  <div class="col-12"><label class="form-label" for="eventPayload"><?= demo_h(t('demo.braillebridge.payload')) ?></label><pre id="eventPayload" class="demo-json bg-dark text-light p-3 rounded mb-0" tabindex="0"></pre></div>
                </div>
              </div>
            </section>
          </div>

          <div class="col-12 col-xl-6">
            <section class="card h-100">
              <div class="card-header">
                <h2 class="card-title"><?= demo_h(t('demo.braillebridge.log.title')) ?></h2>
                <div class="card-actions">
                  <button id="copyLogBtn" class="btn btn-outline-secondary btn-sm" type="button"><?= demo_h(t('demo.braillebridge.copy_log')) ?></button>
                  <button id="clearLogBtn" class="btn btn-outline-secondary btn-sm" type="button"><?= demo_h(t('demo.braillebridge.clear_log')) ?></button>
                </div>
              </div>
              <div class="card-body">
                <pre id="eventLog" class="demo-log bg-dark text-light p-3 rounded mb-0" tabindex="0"></pre>
              </div>
            </section>
          </div>

        </div>
      </div>
    </div>
  </main>
</div>

<script src="<?= demo_h($assetBase) ?>/tabler/core/dist/js/tabler.min.js"></script>
<script src="<?= demo_h($assetBase) ?>/components/braillebridge-status/braillebridge-status.js?v=20260706-popup-text-2"></script>
<script src="<?= demo_h($assetBase) ?>/components/braille-monitor/braillemonitor.js"></script>
<script>
(() => {
  const labels = {
    connected: <?= demo_j('demo.braillebridge.connected') ?>,
    offline: <?= demo_j('demo.braillebridge.offline') ?>,
    connecting: <?= demo_j('demo.braillebridge.connecting') ?>,
    editorOn: <?= demo_j('demo.braillebridge.editor_on_status') ?>,
    editorOff: <?= demo_j('demo.braillebridge.editor_off_status') ?>,
    editorUnknown: <?= demo_j('demo.braillebridge.editor_unknown') ?>,
    insertOn: <?= demo_j('demo.braillebridge.insert_on_status') ?>,
    insertOff: <?= demo_j('demo.braillebridge.insert_off_status') ?>,
    insertUnknown: <?= demo_j('demo.braillebridge.insert_unknown') ?>,
    notConnected: <?= demo_j('demo.braillebridge.not_connected') ?>,
    copied: <?= demo_j('demo.braillebridge.log_copied') ?>,
    ready: <?= demo_j('demo.braillebridge.ready') ?>
  };

  const $ = (id) => document.getElementById(id);
  const wsUrl = $('wsUrl');
  const wsBadge = $('wsBadge');
  const editorBadge = $('editorBadge');
  const insertBadge = $('insertBadge');
  const statusRoot = document.querySelector('[data-braillebridge-status]');
  const logEl = $('eventLog');
  const monitor = window.BrailleMonitor?.init
    ? window.BrailleMonitor.init({ containerId: 'brailleMonitor', showInfo: false })
    : null;
  let ws = null;
  let reconnect = false;

  function appendLog(message, data = null) {
    const stamp = new Date().toLocaleTimeString();
    const suffix = data == null ? '' : ' ' + JSON.stringify(data);
    logEl.textContent = `[${stamp}] ${message}${suffix}\n` + logEl.textContent;
  }

  function setWsState(ok, text) {
    wsBadge.className = ok ? 'badge bg-success-lt' : 'badge bg-danger-lt';
    wsBadge.textContent = text;
  }

  function setEditorState(value) {
    editorBadge.className = value === true ? 'badge bg-success-lt ms-2' : value === false ? 'badge bg-secondary-lt ms-2' : 'badge bg-warning-lt ms-2';
    editorBadge.textContent = value === true ? labels.editorOn : value === false ? labels.editorOff : labels.editorUnknown;
  }

  function setInsertState(value) {
    insertBadge.className = value === true ? 'badge bg-success-lt ms-2' : value === false ? 'badge bg-secondary-lt ms-2' : 'badge bg-warning-lt ms-2';
    insertBadge.textContent = value === true ? labels.insertOn : value === false ? labels.insertOff : labels.insertUnknown;
  }

  function open() {
    if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
    reconnect = true;
    syncStatusUrl();
    setWsState(false, labels.connecting);
    appendLog('WS connect', { url: wsUrl.value });
    try {
      ws = new WebSocket(wsUrl.value.trim());
    } catch (error) {
      setWsState(false, labels.offline);
      appendLog('WS error', { error: String(error) });
      return;
    }
    ws.addEventListener('open', () => {
      setWsState(true, labels.connected);
      appendLog('WS open');
      send({ type: 'getBrailleLine' }, 'getBrailleLine');
    });
    ws.addEventListener('close', (event) => {
      setWsState(false, labels.offline);
      appendLog('WS close', { code: event.code, reason: event.reason || '' });
      ws = null;
      if (reconnect) window.setTimeout(open, 2500);
    });
    ws.addEventListener('error', () => {
      setWsState(false, labels.offline);
      appendLog('WS error');
    });
    ws.addEventListener('message', async (event) => {
      const text = typeof event.data === 'string'
        ? event.data
        : event.data instanceof Blob
          ? await event.data.text()
          : new TextDecoder().decode(event.data);
      handleMessage(text);
    });
  }

  function send(payload, label = payload.type || payload.command || 'message') {
    if (!ws || ws.readyState !== WebSocket.OPEN) {
      appendLog(labels.notConnected, payload);
      return false;
    }
    ws.send(JSON.stringify(payload));
    appendLog('WS -> ' + label, payload);
    return true;
  }

  function syncStatusUrl() {
    const nextUrl = wsUrl.value.trim() || 'ws://localhost:5000/ws';
    if (!statusRoot) return;
    statusRoot.dataset.wsUrl = nextUrl;
    if (statusRoot.__brailleBridgeStatus?.options) {
      statusRoot.__brailleBridgeStatus.options.wsUrl = nextUrl;
    }
  }

  function intValue(id) {
    const n = Number($(id)?.value || 0);
    return Number.isFinite(n) ? Math.max(0, Math.floor(n)) : 0;
  }

  function handleMessage(text) {
    let message;
    try {
      message = JSON.parse(text);
    } catch {
      appendLog('WS <- text', text);
      return;
    }
    appendLog('WS <- JSON', message);
    renderIncoming(message);
  }

  function valueFrom(...values) {
    return values.find((value) => value !== undefined && value !== null && value !== '') ?? '';
  }

  function renderIncoming(message) {
    const type = valueFrom(message.type, message.Type);
    const ok = valueFrom(message.ok, message.Ok);
    const braille = valueFrom(message.braille, message.Braille, {});
    const meta = valueFrom(message.meta, message.Meta, {});
    const caret = valueFrom(message.caret, message.Caret, {});
    const status = valueFrom(message.status, message.Status, {});
    $('eventType').value = String(type || '');
    $('eventOk').value = ok === '' ? '' : String(ok);
    $('eventCursorCell').value = String(valueFrom(caret.cellIndex, caret.CellIndex, meta.caretCellPosition, meta.CaretCellPosition));
    $('eventCursorText').value = String(valueFrom(caret.textIndex, caret.TextIndex, meta.caretTextPosition, meta.CaretTextPosition));
    $('eventPayload').textContent = JSON.stringify(message, null, 2);

    const editorMode = String(valueFrom(status.editorMode, status.EditorMode)).toLowerCase();
    const insertMode = String(valueFrom(status.insertMode, status.InsertMode)).toLowerCase();
    if (editorMode === 'on') setEditorState(true);
    if (editorMode === 'off') setEditorState(false);
    if (insertMode === 'on') setInsertState(true);
    if (insertMode === 'off') setInsertState(false);

    if (String(type).toLowerCase() === 'brailleline') {
      const unicode = valueFrom(braille.unicodeText, braille.UnicodeText);
      const source = valueFrom(message.sourceText, message.SourceText);
      monitor?.setBrailleUnicode?.(String(unicode || ''), String(source || ''), {
        caretPosition: Number.isInteger(caret.cellIndex) ? caret.cellIndex : meta.caretCellPosition,
        textCaretPosition: Number.isInteger(caret.textIndex) ? caret.textIndex : meta.caretTextPosition,
        caretVisible: typeof message.caretVisible === 'boolean' ? message.caretVisible : true
      });
    }
  }

  wsUrl.addEventListener('change', syncStatusUrl);
  wsUrl.addEventListener('blur', syncStatusUrl);
  $('getLineBtn').addEventListener('click', () => send({ type: 'getBrailleLine' }, 'getBrailleLine'));
  $('editorOnBtn').addEventListener('click', () => { setEditorState(true); send({ type: 'command', command: 'setEditorMode', enabled: true }, 'setEditorMode'); });
  $('editorOffBtn').addEventListener('click', () => { setEditorState(false); send({ type: 'command', command: 'setEditorMode', enabled: false }, 'setEditorMode'); });
  $('insertOnBtn').addEventListener('click', () => { setInsertState(true); send({ type: 'command', command: 'setEditorInsertMode', enabled: true }, 'setEditorInsertMode'); });
  $('insertOffBtn').addEventListener('click', () => { setInsertState(false); send({ type: 'command', command: 'setEditorInsertMode', enabled: false }, 'setEditorInsertMode'); });
  $('sendTextBtn').addEventListener('click', () => send({ type: 'command', command: 'editorInput', input: { kind: 'text', text: $('textInput').value } }, 'editorInput text'));
  $('clearBtn').addEventListener('click', () => send({ type: 'command', command: 'editorInput', input: { kind: 'text', text: '', replace: true } }, 'clear'));
  $('sendKeyBtn').addEventListener('click', () => send({ type: 'command', command: 'editorInput', input: { kind: 'key', key: $('keyInput').value } }, 'editorInput key'));
  $('setCaretBtn').addEventListener('click', () => send({ type: 'setCaret', textIndex: intValue('caretTextIndex') }, 'setCaret'));
  $('fromCellBtn').addEventListener('click', () => send({ type: 'setCaretFromCell', cellIndex: intValue('caretCellIndex') }, 'setCaretFromCell'));
  $('moveLeftBtn').addEventListener('click', () => send({ type: 'moveCaret', by: -1, unit: 'character' }, 'moveCaret'));
  $('moveRightBtn').addEventListener('click', () => send({ type: 'moveCaret', by: 1, unit: 'character' }, 'moveCaret'));
  $('beginBtn').addEventListener('click', () => send({ type: 'setCaretToBegin' }, 'setCaretToBegin'));
  $('endBtn').addEventListener('click', () => send({ type: 'setCaretToEnd' }, 'setCaretToEnd'));
  $('caretOnBtn').addEventListener('click', () => send({ type: 'setCaretVisibility', visible: true }, 'setCaretVisibility'));
  $('caretOffBtn').addEventListener('click', () => send({ type: 'setCaretVisibility', visible: false }, 'setCaretVisibility'));
  $('routingBtn').addEventListener('click', () => send({ type: 'cursorRouting', cellIndex: intValue('caretCellIndex') }, 'cursorRouting'));
  $('copyLogBtn').addEventListener('click', async () => {
    await navigator.clipboard?.writeText(logEl.textContent || '');
    appendLog(labels.copied);
  });
  $('clearLogBtn').addEventListener('click', () => { logEl.textContent = ''; });

  setWsState(false, labels.offline);
  setEditorState(undefined);
  setInsertState(undefined);
  appendLog(labels.ready);
  open();
})();
</script>
<script src="<?= demo_h($assetBase) ?>/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
