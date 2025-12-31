/* /components/feedback/feedback.js
   Uses the CURRENT EventLog class exactly as you posted:
   - window.EventLog is a class
   - we create ONE instance at window.eventLog (if not already present)
   - feedback logs through window.eventLog.log(...)
   Config: /config/feedback.json
   Audio:  /audio/...
*/
(function () {
  "use strict";

  if (window.Feedback) {
    console.warn("[Feedback] already loaded");
    return;
  }

  const DEFAULT_FEEDBACK_MS = 300;
  let config = null;

  function safeJson(x) {
    try { return JSON.stringify(x); } catch { return String(x); }
  }

  function ensureEventLog() {
    // If the page already created an instance, use it.
    if (window.eventLog?.log) return window.eventLog;

    // If an element exists, we can auto-mount (demo convenience).
    const el = document.getElementById("eventLog");
    if (el && typeof window.EventLog === "function") {
      window.eventLog = new window.EventLog(el, { maxEntries: 500 });
      return window.eventLog;
    }

    return null;
  }

  function log(message, type = "system", data) {
    const full = data ? `${message} ${safeJson(data)}` : message;

    const logger = ensureEventLog();
    if (logger?.log) logger.log(`[feedback] ${full}`, type);
    else console.log(`[feedback:${type}] ${full}`);
  }

  async function loadConfig() {
    if (config) return config;

    log("load-config", "info", { url: "/config/feedback.json" });

    const res = await fetch("/config/feedback.json", { cache: "no-store" });
    if (!res.ok) {
      log("load-config failed", "error", { status: res.status });
      throw new Error(`[Feedback] Cannot load /config/feedback.json (HTTP ${res.status})`);
    }

    config = await res.json();
    log("config-loaded", "info", { keys: Object.keys(config) });

    return config;
  }

  async function show(type, opts = {}) {
    log("show", "system", { type, opts });

    const cfg = await loadConfig();
    const entry = cfg[type];

    if (!entry) {
      log("unknown-type", "warn", { type });
      return;
    }

    const duration = opts.duration ?? entry.duration ?? DEFAULT_FEEDBACK_MS;

    // Audio
    if (entry.sound) {
      log("audio-play", "info", { src: entry.sound });
      window.AudioPlayer?.play(entry.sound);
    }

    // Braille
    if (entry.braille !== undefined) {
      log("braille-set", "info", { text: entry.braille });
      window.BrailleUI?.setLine(entry.braille, { source: "feedback", type });
    }

    // Auto clear
    if (duration > 0) {
      setTimeout(() => {
        log("braille-clear", "info", { afterMs: duration });
        window.BrailleUI?.clear({ source: "feedback" });
      }, duration);
    }
  }

  window.Feedback = { show };
})();