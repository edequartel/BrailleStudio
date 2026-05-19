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
  <title>BrailleBridge tool</title>
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
              <a class="btn btn-outline-secondary" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>"><i class="ti ti-home me-2" aria-hidden="true"></i>Home</a>
              <a class="btn btn-outline-secondary" href="<?= $htmlUrl($urlFor($scriptDir, 'tables.php')) ?>"><i class="ti ti-table me-2" aria-hidden="true"></i>Tables</a>
              <button class="btn btn-outline-secondary" id="showBrailleBridgeBtn" type="button"><i class="ti ti-window me-2" aria-hidden="true"></i>Show BrailleBridge</button>
              <a class="btn btn-primary" href="braillebridge://"><i class="ti ti-plug-connected me-2" aria-hidden="true"></i>Open BrailleBridge</a>
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
              <h1 class="page-title">BrailleBridge tool</h1>
              <div class="text-secondary mt-2">HTTP and WebSocket controls for the local BrailleBridge runtime.</div>
            </div>
            <div class="col-auto">
              <span class="badge bg-secondary-lt">localhost:5000</span>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="row row-cards">
            <div class="col-12">
              <section class="card">
                <div class="card-header">
                  <h2 class="card-title">BrailleMonitor</h2>
                  <div class="card-actions">
                    <span class="badge bg-danger-lt" id="brailleMonitorWsDot">offline</span>
                  </div>
                </div>
                <div class="card-body">
                  <div id="brailleMonitorComponent" class="form-control font-monospace min-vh-25"></div>
                </div>
              </section>
            </div>

            <div class="col-12 col-xl-5">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="card-title">API</h2>
                  <div class="card-actions">
                    <span class="badge bg-danger-lt" id="httpDot">offline</span>
                    <span class="text-secondary ms-2" id="httpStatusText">unknown</span>
                  </div>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label" for="httpBase">HTTP Base URL</label>
                    <input id="httpBase" class="form-control font-monospace" type="text" value="http://localhost:5000" aria-label="HTTP Base URL">
                  </div>
                  <div class="btn-list mb-2">
                    <button class="btn btn-outline-secondary" id="getPathsBtn" type="button">GET /paths</button>
                    <button class="btn btn-outline-secondary" id="getPingBtn" type="button">GET /ping</button>
                    <button class="btn btn-outline-secondary" id="getClearBtn" type="button">GET /clear</button>
                  </div>
                  <div class="btn-list mb-2">
                    <button class="btn btn-outline-secondary" id="getDevicesBtn" type="button">GET /devices</button>
                    <button class="btn btn-outline-secondary" id="getActiveDeviceBtn" type="button">GET /devices/active</button>
                  </div>
                  <div class="btn-list">
                    <button class="btn btn-outline-secondary" id="getTablesBtn" type="button">GET /tables</button>
                  </div>
                </div>
              </section>
            </div>

            <div class="col-12 col-xl-7">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="card-title">WebSocket</h2>
                  <div class="card-actions">
                    <span class="badge bg-danger-lt" id="wsDot">offline</span>
                    <span class="text-secondary ms-2" id="wsStatusText">unknown</span>
                    <span class="badge bg-warning-lt ms-2" id="editorModeDot">unknown</span>
                    <span class="text-secondary ms-2" id="editorModeText">unknown</span>
                    <span class="badge bg-warning-lt ms-2" id="insertModeDot">unknown</span>
                  </div>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label" for="wsBase">WebSocket URL</label>
                    <input id="wsBase" class="form-control font-monospace" type="text" value="ws://localhost:5000/ws" aria-label="WebSocket URL">
                  </div>
                  <div class="btn-list mb-2">
                    <button class="btn btn-primary" id="wsConnectBtn" type="button">Connect</button>
                    <button class="btn btn-outline-danger" id="wsDisconnectBtn" type="button">Disconnect</button>
                  </div>
                  <div class="btn-list mb-2">
                    <button class="btn btn-outline-secondary" id="wsSendModeBtn" type="button">Enable editorMode</button>
                    <button class="btn btn-outline-danger" id="wsSendModeDisableBtn" type="button">Disable editorMode</button>
                  </div>
                  <div class="btn-list mb-3">
                    <button class="btn btn-outline-secondary" id="wsSetInsertModeOnBtn" type="button">Insert mode ON</button>
                    <button class="btn btn-outline-danger" id="wsSetInsertModeOffBtn" type="button">Insert mode OFF</button>
                    <button class="btn btn-outline-secondary" id="wsGetLineBtn" type="button">getBrailleLine</button>
                  </div>

                  <div class="row g-3">
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="wsInputKind">Input kind</label>
                      <select id="wsInputKind" class="form-select">
                        <option value="text">text</option>
                        <option value="braille">braille</option>
                        <option value="key">key</option>
                      </select>
                    </div>
                    <div class="col-12 col-md-6" id="wsTextField">
                      <label class="form-label" for="wsTextValue">Text</label>
                      <input id="wsTextValue" class="form-control font-monospace" type="text" value="aap Aap AAP 123 ,.?">
                    </div>
                    <div class="col-12 col-md-6" id="wsBrailleField" hidden>
                      <label class="form-label" for="wsBrailleValue">Braille unicode</label>
                      <input id="wsBrailleValue" class="form-control font-monospace" type="text" value="&#x2801;">
                    </div>
                    <div class="col-12 col-md-6" id="wsKeyField" hidden>
                      <label class="form-label" for="wsKeyValue">Key</label>
                      <select id="wsKeyValue" class="form-select">
                        <option>Backspace</option><option>Delete</option><option>DEL</option><option>Space</option><option>Enter</option><option>ArrowLeft</option><option>ArrowRight</option><option>ArrowUp</option><option>ArrowDown</option>
                      </select>
                    </div>
                  </div>
                  <div class="btn-list mt-3 mb-3">
                    <button class="btn btn-outline-secondary" id="wsSendInputBtn" type="button">Send editorInput</button>
                  </div>
                  <div class="row g-3">
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="wsCaretTextIndex">setCaret textIndex</label>
                      <input id="wsCaretTextIndex" class="form-control font-monospace" type="text" value="0">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="wsCaretCellIndex">cellIndex</label>
                      <input id="wsCaretCellIndex" class="form-control font-monospace" type="text" value="0">
                    </div>
                  </div>
                  <div class="btn-list mt-3 mb-2">
                    <button class="btn btn-outline-secondary" id="wsSetCaretBtn" type="button">setCaret</button>
                    <button class="btn btn-outline-secondary" id="wsMoveCaretCharLeftBtn" type="button">moveCaret -1 char</button>
                    <button class="btn btn-outline-secondary" id="wsMoveCaretCharRightBtn" type="button">moveCaret +1 char</button>
                    <button class="btn btn-outline-secondary" id="wsMoveCaretCellRightBtn" type="button">moveCaret +1 cell</button>
                  </div>
                  <div class="btn-list mb-2">
                    <button class="btn btn-outline-secondary" id="wsSetCaretFromCellBtn" type="button">setCaretFromCell</button>
                    <button class="btn btn-outline-secondary" id="wsCursorRoutingBtn" type="button">cursorRouting</button>
                    <button class="btn btn-outline-secondary" id="wsCaretToBeginBtn" type="button">setCaretToBegin</button>
                    <button class="btn btn-outline-secondary" id="wsCaretToEndBtn" type="button">setCaretToEnd</button>
                  </div>
                  <div class="btn-list">
                    <button class="btn btn-outline-secondary" id="wsCaretVisibleOnBtn" type="button">caret visible ON</button>
                    <button class="btn btn-outline-danger" id="wsCaretVisibleOffBtn" type="button">caret visible OFF</button>
                  </div>
                </div>
              </section>
            </div>

            <div class="col-12 col-xl-6">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="card-title">Brailleline</h2>
                  <div class="card-actions"><span class="badge bg-success-lt">brailleLine</span></div>
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label" for="brailleUnicode">Braille UnicodeText</label>
                      <textarea id="brailleUnicode" class="form-control font-monospace" rows="4" readonly></textarea>
                    </div>
                    <div class="col-12 col-md-6"><label class="form-label" for="brailleTable">Table</label><input id="brailleTable" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="brailleTimestamp">TimestampUtc</label><input id="brailleTimestamp" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="brailleCaretTextPosition">CaretTextPosition</label><input id="brailleCaretTextPosition" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="brailleCaretCellPosition">CaretCellPosition</label><input id="brailleCaretCellPosition" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="brailleLineLength">LineLength</label><input id="brailleLineLength" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="brailleStatusModes">Status (editor/insert)</label><input id="brailleStatusModes" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12"><label class="form-label" for="brailleSourceText">SourceText</label><textarea id="brailleSourceText" class="form-control font-monospace" rows="4" readonly></textarea></div>
                  </div>
                </div>
              </section>
            </div>

            <div class="col-12 col-xl-6">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="card-title">Contexts</h2>
                  <div class="card-actions"><span class="badge bg-secondary-lt" id="latestContextTypePill">context</span></div>
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextType">Latest Type</label><input id="latestContextType" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextSamMsgType">Latest Sam.MsgType</label><input id="latestContextSamMsgType" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextOk">Ok</label><input id="latestContextOk" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextTimestamp">TimestampUtc</label><input id="latestContextTimestamp" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextSamUnitId">Latest Sam.UnitId</label><input id="latestContextSamUnitId" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextSamStrip">Latest Sam.Strip</label><input id="latestContextSamStrip" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextSamParam">Latest Sam.Param</label><input id="latestContextSamParam" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextPayloadPress">Payload Press</label><input id="latestContextPayloadPress" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextPayloadName">Payload Name</label><input id="latestContextPayloadName" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="latestContextPayloadKey">Payload Key</label><input id="latestContextPayloadKey" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="cursorCellIndex">Cursor CellIndex</label><input id="cursorCellIndex" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="cursorTextIndex">Cursor TextIndex</label><input id="cursorTextIndex" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="cursorCharacter">Character</label><input id="cursorCharacter" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="cursorWord">Word</label><input id="cursorWord" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="cursorCellChar">Braille CellChar</label><input id="cursorCellChar" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="cursorCellCodePoint">CellCodePoint</label><input id="cursorCellCodePoint" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="cursorTable">Table</label><input id="cursorTable" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="cursorTimestamp">TimestampUtc</label><input id="cursorTimestamp" class="form-control font-monospace" type="text" readonly></div>
                    <div class="col-12"><label class="form-label" for="cursorSourceText">SourceText</label><textarea id="cursorSourceText" class="form-control font-monospace" rows="4" readonly></textarea></div>
                  </div>
                </div>
              </section>
            </div>

            <div class="col-12">
              <section class="card">
                <div class="card-header">
                  <h2 class="card-title">Log</h2>
                  <div class="card-actions">
                    <div class="btn-list">
                      <button class="btn btn-outline-secondary btn-sm" id="toggleLogBtn" type="button">Show log</button>
                      <button class="btn btn-outline-secondary btn-sm" id="copyLogBtn" type="button">Copy log</button>
                      <button class="btn btn-outline-danger btn-sm" id="clearLogBtn" type="button">Clear log</button>
                      <span class="badge bg-success-lt">active</span>
                    </div>
                  </div>
                </div>
                <div class="card-body" id="logCardBody" hidden>
                  <div class="form-control font-monospace overflow-auto" id="log"></div>
                </div>
              </section>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script src="<?= $htmlUrl($urlFor($appBase, 'components/braille-monitor/braillemonitor.js')) ?>"></script>
  <script>
    (function () {
      const el = (id) => document.getElementById(id);

      // Elements
      const httpBase = el("httpBase");
      const wsBase = el("wsBase");
      const httpBaseLabel = el("httpBaseLabel");
      const wsBaseLabel = el("wsBaseLabel");

      const getPathsBtn = el("getPathsBtn");
      const getPingBtn = el("getPingBtn");
      const getClearBtn = el("getClearBtn");
      const getDevicesBtn = el("getDevicesBtn");
      const getActiveDeviceBtn = el("getActiveDeviceBtn");
      const getTablesBtn = el("getTablesBtn");

      const httpDot = el("httpDot");
      const httpStatusText = el("httpStatusText");
      const wsDot = el("wsDot");
      const brailleMonitorWsDot = el("brailleMonitorWsDot");
      const wsStatusText = el("wsStatusText");
      const wsConnectBtn = el("wsConnectBtn");
      const wsDisconnectBtn = el("wsDisconnectBtn");
      const wsSendInputBtn = el("wsSendInputBtn");
      const wsInputKind = el("wsInputKind");
      const wsTextField = el("wsTextField");
      const wsBrailleField = el("wsBrailleField");
      const wsKeyField = el("wsKeyField");
      const wsTextValue = el("wsTextValue");
      const wsBrailleValue = el("wsBrailleValue");
      const wsKeyValue = el("wsKeyValue");
      const wsSendModeBtn = el("wsSendModeBtn");
      const wsSendModeDisableBtn = el("wsSendModeDisableBtn");
      const wsSetInsertModeOnBtn = el("wsSetInsertModeOnBtn");
      const wsSetInsertModeOffBtn = el("wsSetInsertModeOffBtn");
      const wsGetLineBtn = el("wsGetLineBtn");
      const wsSetCaretBtn = el("wsSetCaretBtn");
      const wsMoveCaretCharLeftBtn = el("wsMoveCaretCharLeftBtn");
      const wsMoveCaretCharRightBtn = el("wsMoveCaretCharRightBtn");
      const wsMoveCaretCellRightBtn = el("wsMoveCaretCellRightBtn");
      const wsSetCaretFromCellBtn = el("wsSetCaretFromCellBtn");
      const wsCursorRoutingBtn = el("wsCursorRoutingBtn");
      const wsCaretToBeginBtn = el("wsCaretToBeginBtn");
      const wsCaretToEndBtn = el("wsCaretToEndBtn");
      const wsCaretVisibleOnBtn = el("wsCaretVisibleOnBtn");
      const wsCaretVisibleOffBtn = el("wsCaretVisibleOffBtn");
      const wsCaretTextIndex = el("wsCaretTextIndex");
      const wsCaretCellIndex = el("wsCaretCellIndex");
      const editorModeDot = el("editorModeDot");
      const editorModeText = el("editorModeText");
      const insertModeDot = el("insertModeDot");

      const brailleUnicode = el("brailleUnicode");
      const brailleTable = el("brailleTable");
      const brailleTimestamp = el("brailleTimestamp");
      const brailleCaretTextPosition = el("brailleCaretTextPosition");
      const brailleCaretCellPosition = el("brailleCaretCellPosition");
      const brailleLineLength = el("brailleLineLength");
      const brailleStatusModes = el("brailleStatusModes");
      const brailleSourceText = el("brailleSourceText");

      const cursorCellIndex = el("cursorCellIndex");
      const cursorTextIndex = el("cursorTextIndex");
      const cursorCharacter = el("cursorCharacter");
      const cursorWord = el("cursorWord");
      const cursorCellChar = el("cursorCellChar");
      const cursorCellCodePoint = el("cursorCellCodePoint");
      const cursorTable = el("cursorTable");
      const cursorTimestamp = el("cursorTimestamp");
      const cursorSourceText = el("cursorSourceText");
      const latestContextType = el("latestContextType");
      const latestContextSamMsgType = el("latestContextSamMsgType");
      const latestContextOk = el("latestContextOk");
      const latestContextTimestamp = el("latestContextTimestamp");
      const latestContextSamUnitId = el("latestContextSamUnitId");
      const latestContextSamStrip = el("latestContextSamStrip");
      const latestContextSamParam = el("latestContextSamParam");
      const latestContextPayloadPress = el("latestContextPayloadPress");
      const latestContextPayloadName = el("latestContextPayloadName");
      const latestContextPayloadKey = el("latestContextPayloadKey");
      const latestContextTypePill = el("latestContextTypePill");

      const log = el("log");
      const logCardBody = el("logCardBody");
      const toggleLogBtn = el("toggleLogBtn");
      const showBrailleBridgeBtn = el("showBrailleBridgeBtn");
      const copyLogBtn = el("copyLogBtn");
      const clearLogBtn = el("clearLogBtn");
      const monitor = window.BrailleMonitor && typeof window.BrailleMonitor.init === "function"
        ? window.BrailleMonitor.init({ containerId: "brailleMonitorComponent", showInfo: false })
        : null;
      if (monitor && typeof monitor.setBrailleUnicode === "function") {
        monitor.setBrailleUnicode("", "");
      }

      // Theme follows index.html
      const THEME_KEY = "bs_theme";
      function applyTheme(theme) {
        document.documentElement.setAttribute("data-theme", theme);
      }
      function getInitialTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === "light" || saved === "dark") return saved;
        return window.matchMedia &&
          window.matchMedia("(prefers-color-scheme: dark)").matches
          ? "dark"
          : "light";
      }
      applyTheme(getInitialTheme());
      window.addEventListener("storage", (ev) => {
        if (ev.key !== THEME_KEY) return;
        if (ev.newValue === "light" || ev.newValue === "dark") {
          applyTheme(ev.newValue);
        }
      });

      // Log helpers
      function nowTs() {
        const d = new Date();
        const yy = String(d.getFullYear()).slice(-2);
        const mm = String(d.getMonth() + 1).padStart(2, "0");
        const dd = String(d.getDate()).padStart(2, "0");
        const hh = String(d.getHours()).padStart(2, "0");
        const mi = String(d.getMinutes()).padStart(2, "0");
        const ss = String(d.getSeconds()).padStart(2, "0");
        return `${yy}.${mm}.${dd} ${hh}:${mi}:${ss}`;
      }
      function formatUtcTimestamp(value) {
        if (!value) return "";
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        const yy = String(d.getUTCFullYear()).slice(-2);
        const mm = String(d.getUTCMonth() + 1).padStart(2, "0");
        const dd = String(d.getUTCDate()).padStart(2, "0");
        const hh = String(d.getUTCHours()).padStart(2, "0");
        const mi = String(d.getUTCMinutes()).padStart(2, "0");
        const ss = String(d.getUTCSeconds()).padStart(2, "0");
        return `${yy}.${mm}.${dd} ${hh}:${mi}:${ss}`;
      }
      function appendLog(line) {
        const maxLines = 1200; // cap to avoid runaway memory
        const row = document.createElement("div");
        row.className = "mb-1 text-break";
        const ts = document.createElement("span");
        ts.className = "text-secondary";
        ts.textContent = `[${nowTs()}] `;
        const msg = document.createElement("span");
        msg.className = "text-body";
        msg.textContent = line;
        row.append(ts, msg);
        if (log.firstChild) log.insertBefore(row, log.firstChild);
        else log.append(row);
        while (log.childNodes.length > maxLines) {
          log.removeChild(log.lastChild);
        }
        log.scrollTop = 0;
      }
      function setLogVisible(visible) {
        if (logCardBody) logCardBody.hidden = !visible;
        if (toggleLogBtn) toggleLogBtn.textContent = visible ? "Hide log" : "Show log";
      }
      function setHttpStatus(ok, text) {
        if (httpDot) {
          httpDot.className = ok ? "badge bg-success-lt" : "badge bg-danger-lt";
          httpDot.textContent = ok ? "online" : "offline";
        }
        httpStatusText.textContent = text;
      }
      function setWsStatus(ok, text) {
        if (wsDot) {
          wsDot.className = ok ? "badge bg-success-lt" : "badge bg-danger-lt";
          wsDot.textContent = ok ? "connected" : "offline";
        }
        if (brailleMonitorWsDot) {
          brailleMonitorWsDot.className = ok ? "badge bg-success-lt" : "badge bg-danger-lt";
          brailleMonitorWsDot.textContent = ok ? "connected" : "offline";
        }
        if (wsStatusText) wsStatusText.textContent = text;
      }
      function setEditorModeStatus(enabled) {
        if (editorModeDot) {
          editorModeDot.className = enabled === true ? "badge bg-success-lt" : enabled === false ? "badge bg-secondary-lt" : "badge bg-warning-lt";
          editorModeDot.textContent = enabled === true ? "enabled" : enabled === false ? "disabled" : "unknown";
        }
        if (editorModeText) {
          editorModeText.textContent = enabled === true ? "enabled" : enabled === false ? "disabled" : "unknown";
        }
      }
      function setInsertModeStatus(enabled) {
        if (!insertModeDot) return;
        insertModeDot.className = enabled === true ? "badge bg-success-lt" : enabled === false ? "badge bg-secondary-lt" : "badge bg-warning-lt";
        insertModeDot.textContent = enabled === true ? "on" : enabled === false ? "off" : "unknown";
      }
      // Copy/Clear log
      copyLogBtn.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(log.textContent || "");
          appendLog("UI: log copied to clipboard");
        } catch (e) {
          appendLog(`UI: copy failed: ${String(e)}`);
        }
      });
      clearLogBtn.addEventListener("click", () => {
        log.textContent = "";
        appendLog("UI: log cleared");
      });
      if (toggleLogBtn) {
        toggleLogBtn.addEventListener("click", () => {
          const visible = !!(logCardBody && !logCardBody.hidden);
          setLogVisible(!visible);
        });
      }
      setLogVisible(false);

      // Base URL labels
      function refreshBaseLabels() {
        if (httpBaseLabel) httpBaseLabel.textContent = httpBase.value.trim();
        if (wsBaseLabel) wsBaseLabel.textContent = wsBase.value.trim();
      }
      httpBase.addEventListener("input", refreshBaseLabels);
      wsBase.addEventListener("input", refreshBaseLabels);
      refreshBaseLabels();

      // HTTP fetch wrappers
      async function httpGet(path) {
        const base = httpBase.value.trim().replace(/\/$/, "");
        const url = base + path;
        appendLog(`HTTP → GET ${url}`);
        try {
          const resp = await fetch(url, { method: "GET" });
          const ct = resp.headers.get("content-type") || "";
          let body;
          if (ct.includes("application/json")) body = await resp.json();
          else body = await resp.text();

          setHttpStatus(resp.ok, resp.ok ? "ok" : `error ${resp.status}`);
          appendLog(`HTTP ← ${resp.status} ${resp.statusText} :: ${typeof body === "string" ? body : JSON.stringify(body)}`);
          return body;
        } catch (e) {
          setHttpStatus(false, "network error");
          appendLog(`HTTP !! error :: ${String(e)}`);
          throw e;
        }
      }


      async function httpPostJson(path, bodyObj) {
        const base = httpBase.value.trim().replace(/\/$/, "");
        const url = base + path;
        appendLog(`HTTP → POST ${url} (application/json)`);
        try {
          const resp = await fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json; charset=utf-8" },
            body: JSON.stringify(bodyObj)
          });

          const body = await resp.text();
          setHttpStatus(resp.ok, resp.ok ? "ok" : `error ${resp.status}`);
          appendLog(`HTTP ← ${resp.status} ${resp.statusText} :: ${body}`);
          return body;
        } catch (e) {
          setHttpStatus(false, "network error");
          appendLog(`HTTP !! error :: ${String(e)}`);
          throw e;
        }
      }

      async function httpPostText(path, bodyText, contentType) {
        const base = httpBase.value.trim().replace(/\/$/, "");
        const url = base + path;
        const ct = contentType || "application/json";
        appendLog(`HTTP â†’ POST ${url} (${ct})`);
        try {
          const resp = await fetch(url, {
            method: "POST",
            headers: { "Content-Type": ct },
            body: bodyText ?? ""
          });

          const body = await resp.text();
          setHttpStatus(resp.ok, resp.ok ? "ok" : `error ${resp.status}`);
          appendLog(`HTTP â† ${resp.status} ${resp.statusText} :: ${body}`);
          return body;
        } catch (e) {
          setHttpStatus(false, "network error");
          appendLog(`HTTP !! error :: ${String(e)}`);
          throw e;
        }
      }

      // Hook up HTTP buttons
      getPathsBtn.addEventListener("click", () => httpGet("/paths"));
      getPingBtn.addEventListener("click", () => httpGet("/ping"));
      getClearBtn.addEventListener("click", () => httpGet("/clear"));
      getDevicesBtn.addEventListener("click", () => httpGet("/devices"));
      getActiveDeviceBtn.addEventListener("click", () => httpGet("/devices/active"));
      getTablesBtn.addEventListener("click", () => httpGet("/tables"));
      showBrailleBridgeBtn.addEventListener("click", () => httpGet("/show"));

      // WebSocket
      let ws = null;
      let lastWsJsonLog = null;
      let lastSsocLineId = null;
      let lastSsocCaretCellPosition = null;
      let lastSsocTextCaretPosition = null;
      let lastSsocCaretVisible = null;
      const VALID_EDITOR_KEYS = [
        "Backspace",
        "Delete",
        "DEL",
        "Space",
        "Enter",
        "ArrowLeft",
        "ArrowRight",
        "ArrowUp",
        "ArrowDown"
      ];

      function wsIsOpen() {
        return !!ws && ws.readyState === WebSocket.OPEN;
      }

      function eventTypeOf(msg) {
        return msg?.Type ?? msg?.type;
      }

      function eventOkOf(msg) {
        return msg?.ok ?? msg?.Ok;
      }

      // Only this function is allowed to update BrailleMonitor content.
      function applySsocBrailleLine(msg) {
        if (!monitor || typeof monitor.setBrailleUnicode !== "function") return false;
        if (eventTypeOf(msg) !== "brailleLine") return false;
        if (eventOkOf(msg) !== true) return false;

        const unicodeText = typeof msg?.braille?.unicodeText === "string" ? msg.braille.unicodeText : null;
        if (unicodeText == null) return false;

        const sourceText = typeof msg?.sourceText === "string" ? msg.sourceText : "";
        const textCaretPosition = Number.isInteger(msg?.meta?.caretTextPosition) ? msg.meta.caretTextPosition : null;
        const caretPosition = Number.isInteger(msg?.meta?.caretCellPosition)
          ? msg.meta.caretCellPosition
          : (Number.isInteger(msg?.caret?.cellIndex) ? msg.caret.cellIndex : null);
        const caretVisible = typeof msg?.caretVisible === "boolean" ? msg.caretVisible : true;
        const lineId = msg?.meta?.lineId;

        if (
          typeof lineId === "number" &&
          lineId === lastSsocLineId &&
          caretPosition === lastSsocCaretCellPosition &&
          textCaretPosition === lastSsocTextCaretPosition &&
          caretVisible === lastSsocCaretVisible
        ) {
          return true;
        }

        monitor.setBrailleUnicode(unicodeText, sourceText, { caretPosition, textCaretPosition, caretVisible });
        if (typeof lineId === "number") lastSsocLineId = lineId;
        lastSsocCaretCellPosition = caretPosition;
        lastSsocTextCaretPosition = textCaretPosition;
        lastSsocCaretVisible = caretVisible;
        return true;
      }

      function renderWsInputKind() {
        const kind = wsInputKind ? wsInputKind.value : "text";
        if (wsTextField) wsTextField.hidden = kind !== "text";
        if (wsBrailleField) wsBrailleField.hidden = kind !== "braille";
        if (wsKeyField) wsKeyField.hidden = kind !== "key";
      }

      function wsSendJson(payload, label) {
        if (!wsIsOpen()) {
          appendLog(`WS: not connected (${label})`);
          return false;
        }
        ws.send(JSON.stringify(payload));
        appendLog(`WS -> ${label}: ${JSON.stringify(payload)}`);
        return true;
      }

      function wsConnect() {
        const url = wsBase.value.trim();
        if (!url) return;

        if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) {
          appendLog("WS: already connected/connecting");
          return;
        }

        appendLog(`WS → connect ${url}`);
        setWsStatus(false, "connecting");
        ws = new WebSocket(url);

        ws.addEventListener("open", () => {
          setWsStatus(true, "connected");
          appendLog("WS: open");
        });

        ws.addEventListener("close", (ev) => {
          setWsStatus(false, `closed (${ev.code})`);
          appendLog(`WS: close code=${ev.code} reason=${ev.reason || "(none)"}`);
        });

        ws.addEventListener("error", () => {
          setWsStatus(false, "error");
          appendLog("WS: error");
        });

        ws.addEventListener("message", async (ev) => {
          const data = ev.data;
          if (typeof data === "string") {
            handleWsText(data);
            return;
          }
          if (data instanceof Blob) {
            const text = await data.text();
            handleWsText(text);
            return;
          }
          if (data instanceof ArrayBuffer) {
            const text = new TextDecoder().decode(data);
            handleWsText(text);
            return;
          }
          appendLog("WS <- (non-text message)");
        });
      }

      function wsDisconnect() {
        if (!ws) {
          appendLog("WS: no connection to close");
          return;
        }
        appendLog("WS → close");
        try { ws.close(1000, "client closed"); } catch {}
        lastSsocLineId = null;
        lastSsocCaretCellPosition = null;
        lastSsocTextCaretPosition = null;
        lastSsocCaretVisible = null;
        if (monitor && typeof monitor.setBrailleUnicode === "function") {
          monitor.setBrailleUnicode("", "");
        }
      }

      function wsSendEditorInput() {
        const kind = wsInputKind ? wsInputKind.value : "text";
        let input;

        if (kind === "text") {
          input = { kind: "text", text: wsTextValue ? wsTextValue.value : "" };
        } else if (kind === "braille") {
          input = { kind: "braille", unicode: wsBrailleValue ? wsBrailleValue.value : "" };
        } else if (kind === "key") {
          const key = wsKeyValue ? wsKeyValue.value : "";
          if (!VALID_EDITOR_KEYS.includes(key)) {
            appendLog(`WS: invalid editorInput key "${key}"`);
            return;
          }
          input = { kind: "key", key };
        } else {
          appendLog(`WS: invalid editorInput kind "${kind}"`);
          return;
        }

        wsSendJson({ type: "command", command: "editorInput", input }, "editorInput");
      }

      function wsSendEditorMode() {
        wsSendJson({ type: "command", command: "setEditorMode", enabled: true }, "setEditorMode");
        setEditorModeStatus(true);
      }

      function wsSendEditorModeDisable() {
        wsSendJson({ type: "command", command: "setEditorMode", enabled: false }, "setEditorMode");
        setEditorModeStatus(false);
      }

      function wsSendInsertMode(enabled) {
        wsSendJson({ type: "command", command: "setEditorInsertMode", enabled: !!enabled }, "setEditorInsertMode");
        setInsertModeStatus(!!enabled);
      }

      function toNonNegativeInt(value, fallback = 0) {
        const n = Number(value);
        if (!Number.isFinite(n)) return fallback;
        return Math.max(0, Math.floor(n));
      }

      function handleWsText(text) {
        // Try parse JSON
        try {
          const obj = JSON.parse(text);
          applySsocBrailleLine(obj);
          // Update UI fields via prettyIncomingMessage, but log full JSON payload.
          prettyIncomingMessage(obj);
          const pretty = JSON.stringify(obj, null, 2);
          if (pretty === lastWsJsonLog) return; // de-dupe identical consecutive messages
          lastWsJsonLog = pretty;
          appendLog("WS <- JSON :: " + pretty);
        } catch {
          appendLog("WS <- TEXT " + text);
        }
      }

      function prettyIncomingMessage(o) {
        if (!o || typeof o !== "object") return String(o);

        const type = o.Type || o.type;
        const ok = typeof o.Ok !== "undefined" ? o.Ok : o.ok;
        const ts = o.TimestampUtc || o.timestampUtc || "";
        const sourceText = typeof o.SourceText !== "undefined" ? o.SourceText : (o.sourceText ?? "");
        const braille = o.Braille || o.braille || {};
        const meta = o.Meta || o.meta || {};
        const cursor = o.Cursor || o.cursor || {};
        const sam = o.Sam || o.sam || {};
        const payload = o.Payload || o.payload || {};

        function getSamValues() {
          const msgType = typeof sam.MsgType !== "undefined" ? sam.MsgType : (typeof sam.msgType !== "undefined" ? sam.msgType : o.MsgType);
          const unitId = typeof sam.UnitId !== "undefined" ? sam.UnitId : (typeof sam.unitId !== "undefined" ? sam.unitId : o.UnitId);
          const strip = typeof sam.Strip !== "undefined" ? sam.Strip : (typeof sam.strip !== "undefined" ? sam.strip : o.Strip);
          const param = typeof sam.Param !== "undefined" ? sam.Param : (typeof sam.param !== "undefined" ? sam.param : o.Param);
          return { msgType, unitId, strip, param };
        }

        function setLatestContextMeta(kind) {
          const samValues = getSamValues();
          if (latestContextType) latestContextType.value = kind || "";
          if (latestContextTypePill) latestContextTypePill.textContent = kind || "context";
          if (latestContextSamMsgType) latestContextSamMsgType.value = typeof samValues.msgType !== "undefined" ? String(samValues.msgType) : "";
          if (latestContextOk) latestContextOk.value = typeof ok !== "undefined" ? String(ok) : "";
          if (latestContextTimestamp) latestContextTimestamp.value = formatUtcTimestamp(ts);
          if (latestContextSamUnitId) latestContextSamUnitId.value = typeof samValues.unitId !== "undefined" ? String(samValues.unitId) : "";
          if (latestContextSamStrip) latestContextSamStrip.value = typeof samValues.strip !== "undefined" ? String(samValues.strip) : "";
          if (latestContextSamParam) latestContextSamParam.value = typeof samValues.param !== "undefined" ? String(samValues.param) : "";
        }

        function clearPayloadFields() {
          if (latestContextPayloadPress) latestContextPayloadPress.value = "";
          if (latestContextPayloadName) latestContextPayloadName.value = "";
          if (latestContextPayloadKey) latestContextPayloadKey.value = "";
        }

        function clearCursorBrailleFields() {
          if (cursorCellIndex) cursorCellIndex.value = "";
          if (cursorTextIndex) cursorTextIndex.value = "";
          if (cursorCharacter) cursorCharacter.value = "";
          if (cursorWord) cursorWord.value = "";
          if (cursorCellChar) cursorCellChar.value = "";
          if (cursorCellCodePoint) cursorCellCodePoint.value = "";
          if (cursorTable) cursorTable.value = "";
          if (cursorTimestamp) cursorTimestamp.value = "";
          if (cursorSourceText) cursorSourceText.value = "";
        }

        function renderLatestContextFields() {
          if (cursorCellIndex) cursorCellIndex.value = typeof cursor.CellIndex !== "undefined" ? String(cursor.CellIndex) : (typeof cursor.cellIndex !== "undefined" ? String(cursor.cellIndex) : "");
          if (cursorTextIndex) cursorTextIndex.value = typeof cursor.TextIndex !== "undefined" ? String(cursor.TextIndex) : (typeof cursor.textIndex !== "undefined" ? String(cursor.textIndex) : "");
          if (cursorCharacter) cursorCharacter.value = cursor.Character || cursor.character || "";
          if (cursorWord) cursorWord.value = cursor.Word || cursor.word || "";
          if (cursorCellChar) cursorCellChar.value = braille.CellChar || braille.cellChar || "";
          if (cursorCellCodePoint) cursorCellCodePoint.value = braille.CellCodePoint || braille.cellCodePoint || "";
          if (cursorTable) cursorTable.value = o.Table || o.table || "";
          if (cursorTimestamp) cursorTimestamp.value = formatUtcTimestamp(ts);
          if (cursorSourceText) cursorSourceText.value = sourceText;
        }

        if (type === "brailleLine") {
          const unicodeText = braille.UnicodeText || braille.unicodeText || "";
          if (brailleUnicode) brailleUnicode.value = unicodeText;
          const activeTable = meta.ActiveTable || meta.activeTable || braille.Table || braille.table || "";
          const createdUtc = meta.CreatedUtc || meta.createdUtc || ts;
          const caretTextPosition =
            typeof meta.CaretTextPosition !== "undefined" ? meta.CaretTextPosition
            : (typeof meta.caretTextPosition !== "undefined" ? meta.caretTextPosition
            : (typeof o?.caret?.textIndex !== "undefined" ? o.caret.textIndex : ""));
          const caretCellPosition =
            typeof meta.CaretCellPosition !== "undefined" ? meta.CaretCellPosition
            : (typeof meta.caretCellPosition !== "undefined" ? meta.caretCellPosition
            : (typeof o?.caret?.cellIndex !== "undefined" ? o.caret.cellIndex : ""));
          const lineLength = typeof meta.LineLength !== "undefined" ? meta.LineLength : meta.lineLength;
          const status = o.Status || o.status || {};
          const editorMode = typeof status.EditorMode !== "undefined" ? status.EditorMode : status.editorMode;
          const insertMode = typeof status.InsertMode !== "undefined" ? status.InsertMode : status.insertMode;
          if (typeof editorMode === "string") setEditorModeStatus(editorMode.toLowerCase() === "on");
          if (typeof insertMode === "string") setInsertModeStatus(insertMode.toLowerCase() === "on");
          if (brailleTable) brailleTable.value = activeTable;
          if (brailleTimestamp) brailleTimestamp.value = formatUtcTimestamp(createdUtc);
          if (brailleCaretTextPosition) brailleCaretTextPosition.value = typeof caretTextPosition !== "undefined" ? String(caretTextPosition) : "";
          if (brailleCaretCellPosition) brailleCaretCellPosition.value = typeof caretCellPosition !== "undefined" ? String(caretCellPosition) : "";
          if (brailleLineLength) brailleLineLength.value = typeof lineLength !== "undefined" ? String(lineLength) : "";
          if (brailleStatusModes) brailleStatusModes.value = `${editorMode || "?"} / ${insertMode || "?"}`;
          if (brailleSourceText) brailleSourceText.value = sourceText;
          const brailleText = unicodeText || "(no braille)";
          const table = activeTable || "(no table)";
          return `brailleLine ok=${ok} table=${table} text=${brailleText}`;
        }
        if (type === "cursor") {
          setLatestContextMeta("cursor");
          clearPayloadFields();
          renderLatestContextFields();
          const cell = typeof cursor.CellIndex !== "undefined" ? cursor.CellIndex : (typeof cursor.cellIndex !== "undefined" ? cursor.cellIndex : "?");
          const textIndex = typeof cursor.TextIndex !== "undefined" ? cursor.TextIndex : (typeof cursor.textIndex !== "undefined" ? cursor.textIndex : "?");
          const ch = cursor.Character || cursor.character || "?";
          return `cursor ok=${ok} cell=${cell} textIndex=${textIndex} char=${ch}`;
        }
        if (type === "chord") {
          setLatestContextMeta("chord");
          clearPayloadFields();
          renderLatestContextFields();
          const cell = typeof cursor.CellIndex !== "undefined" ? cursor.CellIndex : (typeof cursor.cellIndex !== "undefined" ? cursor.cellIndex : "?");
          const textIndex = typeof cursor.TextIndex !== "undefined" ? cursor.TextIndex : (typeof cursor.textIndex !== "undefined" ? cursor.textIndex : "?");
          const ch = cursor.Character || cursor.character || "?";
          return `chord ok=${ok} cell=${cell} textIndex=${textIndex} char=${ch}`;
        }
        if (type === "thumbKey") {
          setLatestContextMeta("thumbKey");
          clearCursorBrailleFields();
          if (latestContextPayloadName) latestContextPayloadName.value = payload.Name || payload.name || "";
          if (latestContextPayloadKey) latestContextPayloadKey.value = "";
          const press = typeof payload.Press !== "undefined" ? payload.Press : payload.press;
          if (latestContextPayloadPress) latestContextPayloadPress.value = typeof press !== "undefined" ? String(press) : "";
          return `thumbKey ok=${ok} name=${payload.Name || payload.name || ""} press=${typeof press !== "undefined" ? String(press) : ""}`;
        }
        if (type === "editorKey") {
          setLatestContextMeta("editorKey");
          clearCursorBrailleFields();
          if (latestContextPayloadName) latestContextPayloadName.value = "";
          if (latestContextPayloadKey) latestContextPayloadKey.value = payload.Key || payload.key || "";
          const press = typeof payload.Press !== "undefined" ? payload.Press : payload.press;
          if (latestContextPayloadPress) latestContextPayloadPress.value = typeof press !== "undefined" ? String(press) : "";
          return `editorKey ok=${ok} key=${payload.Key || payload.key || ""} press=${typeof press !== "undefined" ? String(press) : ""}`;
        }
        if (typeof o.MsgType !== "undefined") {
          setLatestContextMeta("rawKey");
          clearCursorBrailleFields();
          if (latestContextPayloadName) latestContextPayloadName.value = o.Name ?? "";
          if (latestContextPayloadKey) latestContextPayloadKey.value = o.EditorCommand ?? "";
          if (latestContextPayloadPress) latestContextPayloadPress.value = typeof o.IsPress !== "undefined" ? String(o.IsPress) : "";
          return `keyEvent MsgType=${o.MsgType} UnitId=${o.UnitId} ButtonIndex=${o.ButtonIndex} IsPress=${o.IsPress} Name=${o.Name ?? ""} DotsMask=${o.DotsMask ?? ""}`;
        }
        return JSON.stringify(o);
      }

      // Hook up WS buttons
      wsConnectBtn.addEventListener("click", wsConnect);
      wsDisconnectBtn.addEventListener("click", wsDisconnect);
      wsSendInputBtn.addEventListener("click", wsSendEditorInput);
      if (wsInputKind) wsInputKind.addEventListener("change", renderWsInputKind);
      wsSendModeBtn.addEventListener("click", wsSendEditorMode);
      wsSendModeDisableBtn.addEventListener("click", wsSendEditorModeDisable);
      wsSetInsertModeOnBtn.addEventListener("click", () => wsSendInsertMode(true));
      wsSetInsertModeOffBtn.addEventListener("click", () => wsSendInsertMode(false));
      wsGetLineBtn.addEventListener("click", () => wsSendJson({ type: "getBrailleLine" }, "getBrailleLine"));
      wsSetCaretBtn.addEventListener("click", () => {
        wsSendJson({ type: "setCaret", textIndex: toNonNegativeInt(wsCaretTextIndex?.value, 0) }, "setCaret");
      });
      wsMoveCaretCharLeftBtn.addEventListener("click", () => {
        wsSendJson({ type: "moveCaret", by: -1, unit: "character" }, "moveCaret");
      });
      wsMoveCaretCharRightBtn.addEventListener("click", () => {
        wsSendJson({ type: "moveCaret", by: 1, unit: "character" }, "moveCaret");
      });
      wsMoveCaretCellRightBtn.addEventListener("click", () => {
        wsSendJson({ type: "moveCaret", by: 1, unit: "cell" }, "moveCaret");
      });
      wsSetCaretFromCellBtn.addEventListener("click", () => {
        wsSendJson({ type: "setCaretFromCell", cellIndex: toNonNegativeInt(wsCaretCellIndex?.value, 0) }, "setCaretFromCell");
      });
      wsCursorRoutingBtn.addEventListener("click", () => {
        wsSendJson({ type: "cursorRouting", cellIndex: toNonNegativeInt(wsCaretCellIndex?.value, 0) }, "cursorRouting");
      });
      wsCaretToBeginBtn.addEventListener("click", () => wsSendJson({ type: "setCaretToBegin" }, "setCaretToBegin"));
      wsCaretToEndBtn.addEventListener("click", () => wsSendJson({ type: "setCaretToEnd" }, "setCaretToEnd"));
      wsCaretVisibleOnBtn.addEventListener("click", () => wsSendJson({ type: "setCaretVisibility", visible: true }, "setCaretVisibility"));
      wsCaretVisibleOffBtn.addEventListener("click", () => wsSendJson({ type: "setCaretVisibility", visible: false }, "setCaretVisibility"));
      renderWsInputKind();
      setInsertModeStatus(undefined);

      // On load: show initial instruction in log
      appendLog("UI ready. Use HTTP buttons or connect WebSocket.");
      appendLog("Spec: GET /ping, GET /clear, GET /devices, GET /tables, GET /paths.");
      appendLog("Spec: WS thumbKey/editorKey/cursor/chord/brailleLine in (+ optional raw key).");
      appendLog("Spec: out setEditorMode/setEditorInsertMode/editorInput and caret/query/cursorRouting commands.");
    })();
  </script>
</body>
</html>
