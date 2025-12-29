// /activities/pairletters.js
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

  const DEFAULT_ROUNDS = 5;
  const DEFAULT_LINE_LEN = 18; // number of CELLS (not string chars)
  const FLASH_MS = 450;

  function create() {
    let running = false;

    let donePromise = null;
    let doneResolve = null;

    // state per run
    let round = 0;
    let totalRounds = DEFAULT_ROUNDS;
    let lineLenCells = DEFAULT_LINE_LEN;

    // We keep TWO representations:
    // - cells[]: array of letters per cell index (length = effectiveLen)
    // - lineText: the string that is sent to the display (spaces between letters)
    let cells = [];
    let lineText = "";

    let target = "";      // letter that appears twice
    let hits = new Set(); // store CELL indices (not string indices)

    let known = [];
    let fresh = [];

    let playToken = 0;
    let currentCtx = null;

    let roundDoneResolve = null;

    function ensureDonePromise() {
      if (donePromise) return donePromise;
      donePromise = new Promise((resolve) => { doneResolve = resolve; });
      return donePromise;
    }

    function resolveDone(payload) {
      if (!doneResolve) return;
      const r = doneResolve;
      doneResolve = null;
      donePromise = null;
      r(payload);
    }

    function uniqLetters(arr) {
      const out = [];
      const seen = new Set();
      for (const x of (Array.isArray(arr) ? arr : [])) {
        const s = String(x || "").trim().toLowerCase();
        if (!s) continue;
        if (s.length !== 1) continue;
        if (seen.has(s)) continue;
        seen.add(s);
        out.push(s);
      }
      return out;
    }

    function computePools(record) {
      const knownLetters = uniqLetters(record?.knownLetters);
      const letters = uniqLetters(record?.letters);
      const knownSet = new Set(knownLetters);
      const freshLetters = letters.filter(ch => !knownSet.has(ch));
      return { knownLetters, freshLetters };
    }

    function clampInt(x, min, max, fallback) {
      const n = Number(x);
      if (!Number.isFinite(n)) return fallback;
      const i = Math.floor(n);
      return Math.max(min, Math.min(max, i));
    }

    function readConfigFromCtx(ctx) {
      const a = ctx?.activity || {};

      totalRounds = clampInt(
        a.nrof ?? a.nrOf ?? a.nRounds,
        1, 200,
        DEFAULT_ROUNDS
      );

      lineLenCells = clampInt(
        a.lineLen ?? a.lineLength ?? a.len,
        2, 40,
        DEFAULT_LINE_LEN
      );

      log("[pairletters] config", { totalRounds, lineLen: lineLenCells });
    }

    function pickTarget() {
      // target must be from fresh/known only
      if (fresh.length) return fresh[Math.floor(Math.random() * fresh.length)];
      if (known.length) return known[Math.floor(Math.random() * known.length)];
      // last resort (should not happen if JSON is correct)
      return "a";
    }

    // Enforces: ONLY letters from known+fresh are used.
    // Enforces: target appears twice; all other letters are unique.
    function buildCells(targetLetter, requestedLenCells) {
      // Allowed pool = known + fresh only
      const poolAll = uniqLetters([...(known || []), ...(fresh || [])]);

      // Candidates for other positions (must not be target)
      const candidates = poolAll.filter(ch => ch && ch !== targetLetter);

      // We need (len - 2) unique other letters.
      // So: candidates.length >= len - 2  =>  len <= candidates.length + 2
      const maxLenPossible = candidates.length + 2;

      let len = clampInt(requestedLenCells, 2, 40, DEFAULT_LINE_LEN);
      if (len > maxLenPossible) {
        log("[pairletters] warning: lineLen reduced (not enough unique letters in pool)", {
          requested: len,
          maxPossible: maxLenPossible,
          poolAll: poolAll.join(""),
          candidates: candidates.join(""),
          target: targetLetter
        });
        len = maxLenPossible;
      }

      // If still too small to be meaningful, force at least 2
      len = Math.max(2, len);

      // positions for the two target letters
      const pos1 = Math.floor(Math.random() * len);
      let pos2 = Math.floor(Math.random() * len);
      while (pos2 === pos1) pos2 = Math.floor(Math.random() * len);

      const arr = new Array(len).fill("x");
      arr[pos1] = targetLetter;
      arr[pos2] = targetLetter;

      // choose unique letters for the remaining positions
      // (we do NOT allow duplicates among non-target letters)
      const available = candidates.slice();
      // shuffle available
      for (let i = available.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        const tmp = available[i];
        available[i] = available[j];
        available[j] = tmp;
      }

      let takeIdx = 0;
      for (let i = 0; i < len; i++) {
        if (i === pos1 || i === pos2) continue;

        const chosen = available[takeIdx++];
        // If JSON is consistent, chosen exists. If not, use target (but that would violate uniqueness)
        // Better: use "x" (still violates "only from pool"). So we just guard:
        arr[i] = chosen || targetLetter;
      }

      return arr;
    }

    function cellsToDisplayText(arr) {
      return (Array.isArray(arr) ? arr : []).join(" ");
    }

    function sendToBraille(text) {
      const t = String(text || "");

      // Preferred: go through words.js pipeline (monitor + bridge best-effort)
      if (window.BrailleUI && typeof window.BrailleUI.setLine === "function") {
        window.BrailleUI.setLine(t, { reason: "pairletters" });
        return Promise.resolve();
      }

      // Fallback: direct bridge call, but NEVER throw
      if (window.BrailleBridge && typeof window.BrailleBridge.sendText === "function") {
        return window.BrailleBridge.sendText(t).catch(() => {});
      }

      return Promise.resolve();
    }

    async function flashMessage(msg) {
      const saved = lineText;
      try {
        await sendToBraille(msg);
        await new Promise(r => setTimeout(r, FLASH_MS));
      } finally {
        await sendToBraille(saved);
      }
    }

    function waitForRoundCompletion(token) {
      return new Promise((resolve) => {
        if (token !== playToken) return resolve();
        roundDoneResolve = resolve;
      });
    }

    function resolveRound() {
      const r = roundDoneResolve;
      roundDoneResolve = null;
      if (typeof r === "function") r();
    }

    async function nextRound(token) {
      if (token !== playToken) return;

      hits.clear();
      target = pickTarget();

      cells = buildCells(target, lineLenCells);
      lineText = cellsToDisplayText(cells);

      log("[pairletters] round line", {
        round: round + 1,
        totalRounds,
        lineLenRequested: lineLenCells,
        lineLenEffective: cells.length,
        target,
        pool: uniqLetters([...(known || []), ...(fresh || [])]).join(""),
        line: lineText
      });

      await sendToBraille(lineText);
    }

    async function run(ctx, token) {
      currentCtx = ctx;
      const rec = ctx?.record || {};

      const { knownLetters, freshLetters } = computePools(rec);
      known = knownLetters;
      fresh = freshLetters;

      readConfigFromCtx(ctx);

      log("[pairletters] start run", {
        recordId: rec?.id,
        word: rec?.word,
        knownLetters: known,
        freshLetters: fresh,
        rounds: totalRounds,
        lineLen: lineLenCells
      });

      round = 0;
      while (round < totalRounds) {
        if (token !== playToken) return;

        await nextRound(token);
        await waitForRoundCompletion(token);

        round += 1;
      }

      if (token !== playToken) return;

      await flashMessage("klaar");
      stop({ reason: "done" });
    }

    function start(ctx) {
      stop({ reason: "restart" });
      ensureDonePromise();

      running = true;
      playToken += 1;
      const token = playToken;

      run(ctx, token).catch((err) => {
        log("[pairletters] error", { message: err?.message || String(err) });
        resolveDone({ ok: false, error: err?.message || String(err) });
      });

      return donePromise;
    }

    function stop(payload) {
      if (!running && !donePromise) return;

      running = false;
      playToken += 1;

      log("[pairletters] stop", payload || {});
      resolveDone({ ok: true, payload });
    }

    function isRunning() {
      return Boolean(running);
    }

    // info.index is CELL index (0..39)
    function onCursor(info) {
      if (!running) return;

      const idx = typeof info?.index === "number" ? info.index : null;
      if (idx == null) return;

      const letter = String(cells[idx] || "").trim().toLowerCase();
      if (!letter) return;

      log("[pairletters] cursor", { idx, letter, target, hits: Array.from(hits) });

      if (letter !== target) {
        hits.clear();
        flashMessage("fout").catch(() => {});
        return;
      }

      if (!hits.has(idx)) hits.add(idx);

      if (hits.size >= 2) {
        flashMessage("goed").catch(() => {});
        resolveRound();
      }
    }

    return { start, stop, isRunning, onCursor };
  }

  window.Activities = window.Activities || {};
  window.Activities.pairletters = create();
})();