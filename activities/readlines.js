// /activities/readlines.js
(function () {
  "use strict";

  function log(msg, data) {
    const line = data ? `${msg} ${safeJson(data)}` : msg;
    if (typeof window.logMessage === "function") window.logMessage(line);
    else console.log(line);
  }

  function safeJson(x) {
    try { return JSON.stringify(x); } catch { return String(x); }
  }

  // Use the existing helper that words.js exposes to activities.
  // This keeps braille output consistent with the rest of your runner.
  function setLine(text, meta) {
    if (window.BrailleUI && typeof window.BrailleUI.setLine === "function") {
      window.BrailleUI.setLine(String(text ?? ""), meta || { reason: "readlines" });
      return;
    }
    // Fallback: log only (should not happen in your setup)
    log("[readlines] BrailleUI.setLine missing", { text });
  }

  function clear(meta) {
    if (window.BrailleUI && typeof window.BrailleUI.clear === "function") {
      window.BrailleUI.clear(meta || { reason: "readlines-clear" });
      return;
    }
    setLine("", meta);
  }

  // ---------------------------------------------------------------------------
  // Module state (because words.js expects a singleton module with start/stop)
  // ---------------------------------------------------------------------------
  let running = false;

  let donePromise = null;
  let doneResolve = null;

  let ctx = null;     // context passed from words.js startSelectedActivity()
  let lines = [];     // ctx.record.text[]
  let index = 0;

  function isRunning() {
    return running;
  }

  function resolveDone(payload) {
    const r = doneResolve;
    doneResolve = null;
    if (typeof r === "function") r(payload || { reason: "done" });
  }

  function clampIndex(i) {
    if (!lines.length) return 0;
    return Math.max(0, Math.min(lines.length - 1, i));
  }

  function currentText() {
    if (!lines.length) return "";
    return String(lines[index] ?? "");
  }

  function render(reason) {
    const text = currentText();
    setLine(text, { reason: reason || "render", index, total: lines.length });
    log("[readlines] render", { index, total: lines.length, text });
  }

  // Right thumb: next line (finish on last)
  function nextLine() {
    if (!running) return;

    if (!lines.length) {
      stop({ reason: "no-text" });
      return;
    }

    if (index < lines.length - 1) {
      index++;
      render("next");
    } else {
      // End reached -> finish activity so auto-run can advance
      stop({ reason: "done" });
    }
  }

  // Left thumb: previous line
  function prevLine() {
    if (!running) return;
    if (!lines.length) return;

    if (index > 0) index--;
    render("prev");
  }

  // ---------------------------------------------------------------------------
  // REQUIRED by words.js getActivityModule(): start() and stop()
  // ---------------------------------------------------------------------------
  function start(startCtx) {
    // stop any previous run
    stop({ reason: "restart" });

    ctx = startCtx || null;
    const record = ctx?.record || {};

    lines = Array.isArray(record.text) ? record.text.slice() : [];
    index = clampIndex(0);

    running = true;
    donePromise = new Promise((resolve) => (doneResolve = resolve));

    log("[readlines] start", { recordId: record?.id, word: record?.word, lines: lines.length });

    // Show first line immediately
    render("start");

    return donePromise;
  }

  function stop(payload) {
    if (!running && !doneResolve) return;

    running = false;

    // Optional: decide whether you want to clear display on stop
    // clear({ reason: "stop" });

    log("[readlines] stop", payload || { reason: "stop" });
    resolveDone(payload || { reason: "stop" });
  }

  // ---------------------------------------------------------------------------
  // Thumb forwarding API:
  // words.js will call these when the user presses thumb keys while running.
  // ---------------------------------------------------------------------------
  function onRightThumb() {
    nextLine();
  }

  function onLeftThumb() {
    prevLine();
  }

  // Register module for words.js
  window.Activities = window.Activities || {};
  window.Activities.readlines = {
    start,
    stop,
    isRunning,
    onRightThumb,
    onLeftThumb
  };

  log("[readlines] readlines.js loaded");
})();