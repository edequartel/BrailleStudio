// /js/words.js
(function () {
  "use strict";

  // ------------------------------------------------------------
  // Logging helper: uses logging.js (logMessage) if available.
  // Falls back to console.log.
  // ------------------------------------------------------------
  function log(msg, data) {
    const line = data ? `${msg} ${safeJson(data)}` : msg;
    if (typeof window.logMessage === "function") {
      window.logMessage(line);
    } else {
      console.log(line);
    }
  }

  function safeJson(x) {
    try {
      return JSON.stringify(x);
    } catch {
      return String(x);
    }
  }

  log("[words] words.js loaded");

  // ------------------------------------------------------------
  // Config
  // ------------------------------------------------------------
  const DATA_URL = "../config/words.json";

  // ------------------------------------------------------------
  // State
  // ------------------------------------------------------------
  let records = [];
  let currentIndex = 0;

  // ------------------------------------------------------------
  // DOM helper
  // ------------------------------------------------------------
  function $(id) {
    const el = document.getElementById(id);
    if (!el) log(`[words] Missing element #${id}`);
    return el;
  }

  function setStatus(text) {
    const el = $("data-status");
    if (el) el.textContent = "Data: " + text;
  }

  // ------------------------------------------------------------
  // Render
  // ------------------------------------------------------------
  function render() {
    if (!records.length) {
      log("[words] render() called with no records");
      setStatus("geen records");
      return;
    }

    const item = records[currentIndex];
    log("[words] Rendering record", { index: currentIndex, id: item.id });

    const idEl = $("item-id");
    const indexEl = $("item-index");
    const wordEl = $("field-word");

    // Critical elements must exist
    if (!idEl || !indexEl || !wordEl) {
      log("[words] Critical DOM elements missing; cannot render.");
      setStatus("HTML mist ids");
      return;
    }

    idEl.textContent = "ID: " + (item.id ?? "–");
    indexEl.textContent = `${currentIndex + 1} / ${records.length}`;

    wordEl.textContent = item.word || "–";

    const lettersEl = $("field-letters");
    if (lettersEl) {
      lettersEl.textContent = Array.isArray(item.letters) && item.letters.length
        ? item.letters.join(" ")
        : "–";
    }

    const wordsEl = $("field-words");
    if (wordsEl) {
      wordsEl.textContent = Array.isArray(item.words) && item.words.length
        ? item.words.join(", ")
        : "–";
    }

    const storyEl = $("field-story");
    if (storyEl) {
      storyEl.textContent = Array.isArray(item.story) && item.story.length
        ? item.story.join(", ")
        : "–";
    }

    const soundsEl = $("field-sounds");
    if (soundsEl) {
      soundsEl.textContent = Array.isArray(item.sounds) && item.sounds.length
        ? item.sounds.join(", ")
        : "–";
    }

    const iconEl = $("field-icon");
    if (iconEl) {
      iconEl.textContent = item.icon || "–";
    }

    const shortFlagEl = $("short-flag");
    if (shortFlagEl) {
      shortFlagEl.style.display = item.short ? "inline-flex" : "none";
    }

    setStatus(`geladen (${records.length})`);
  }

  // ------------------------------------------------------------
  // Navigation
  // ------------------------------------------------------------
  function next() {
    if (!records.length) return;
    currentIndex = (currentIndex + 1) % records.length;
    log("[words] Next pressed", { currentIndex });
    render();
  }

  function prev() {
    if (!records.length) return;
    currentIndex = (currentIndex - 1 + records.length) % records.length;
    log("[words] Prev pressed", { currentIndex });
    render();
  }

  // ------------------------------------------------------------
  // Load JSON
  // ------------------------------------------------------------
  async function loadData() {
    log("[words] Loading JSON", { url: DATA_URL });
    setStatus("laden...");

    try {
      const res = await fetch(DATA_URL, { cache: "no-store" });
      log("[words] Fetch response", { status: res.status, ok: res.ok });

      if (!res.ok) {
        setStatus(`fout HTTP ${res.status}`);
        throw new Error(`HTTP ${res.status}`);
      }

      const json = await res.json();

      if (!Array.isArray(json)) {
        setStatus("fout: geen array");
        throw new Error("words.json is not an array");
      }

      // Basic validation/logging
      records = json;
      currentIndex = 0;

      log("[words] JSON parsed", { count: records.length, firstId: records[0]?.id });
      setStatus(`geladen (${records.length})`);

      render();
    } catch (err) {
      log("[words] ERROR loading JSON", { message: err.message });
      // Helpful hint for the common case (opening via file://)
      setStatus("laden mislukt (zie log/console)");
    }
  }

  // ------------------------------------------------------------
  // Init
  // ------------------------------------------------------------
  document.addEventListener("DOMContentLoaded", () => {
    log("[words] DOMContentLoaded");

    const nextBtn = $("next-btn");
    const prevBtn = $("prev-btn");

    if (nextBtn) nextBtn.addEventListener("click", next);
    if (prevBtn) prevBtn.addEventListener("click", prev);

    loadData();
  });
})();
