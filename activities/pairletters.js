// /activities/pairletters.js
(function () {
  "use strict";

  // VERSION MARKER (useful for cache/path debugging)
  console.log("[pairletters] LOADED version 2025-12-31 twoletters-fixed");

  function log(msg, data) {
    const line = data ? `${msg} ${safeJson(data)}` : msg;
    if (typeof window.logMessage === "function") window.logMessage(line);
    else console.log(line);
  }
  function safeJson(x) {
    try { return JSON.stringify(x); } catch { return String(x); }
  }

  const DEFAULT_ROUNDS = 5;
  const DEFAULT_LINE_LEN = 18; // number of CELLS
  const FEEDBACK_MS = 300;
  const FLIPBACK_MS = 650;

  const DEFAULT_TARGET_COUNT = 2;

  function create() {
    let running = false;

    let donePromise = null;
    let doneResolve = null;

    // run config
    let round = 0;
    let totalRounds = DEFAULT_ROUNDS;
    let lineLenCells = DEFAULT_LINE_LEN;

    // twoletters:true => EXACTLY two distinct letters per line (target + distractor)
    let twoLettersOnly = false;

    // only used in twoletters mode
    let targetCount = DEFAULT_TARGET_COUNT;

    // round state
    let cells = [];
    let lineText = "";
    let known = [];
    let fresh = [];
    let currentTarget = "a";

    // selection state
    let stage = 0;
    let firstIdx = null;
    let firstLetter = "";
    let secondIdx = null;
    let secondLetter = "";

    // mapping
    let cellCharStarts = [];

    // control
    let playToken = 0;
    let roundDoneResolve = null;
    let inputLocked = false;
    let selectionEpoch = 0;

    // keep record for record.letters pool
    let ctxRecord = null;

    // ------------------------------------------------------------
    // Promise helpers
    // ------------------------------------------------------------
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

    // ------------------------------------------------------------
    // Utility
    // ------------------------------------------------------------
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

    // Read bool from multiple possible key names
    function readBool(a, keys, fallback) {
      for (const k of keys) {
        if (a && Object.prototype.hasOwnProperty.call(a, k)) {
          const v = a[k];
          if (v === true) return true;
          if (v === false) return false;
          const s = String(v).trim().toLowerCase();
          if (s === "true") return true;
          if (s === "false") return false;
        }
      }
      return fallback;
    }

    function readConfigFromCtx(ctx) {
      const a = ctx?.activity || {};

      totalRounds = clampInt(a.nrof ?? a.nrOf ?? a.nRounds, 1, 200, DEFAULT_ROUNDS);
      lineLenCells = clampInt(a.lineLen ?? a.lineLength ?? a.len, 2, 40, DEFAULT_LINE_LEN);

      // accept multiple spellings to avoid runner normalization issues
      twoLettersOnly = readBool(a, ["twoletters", "twoLetters", "twoLettersOnly"], false);

      // accept multiple spellings
      const tc = a.targetCount ?? a.targetcount ?? a.targets ?? a.targetCounts;
      targetCount = clampInt(tc, 2, 40, DEFAULT_TARGET_COUNT);

      // IMPORTANT: show the incoming activity config
      log("[pairletters] activity cfg", a);
      log("[pairletters] config", { totalRounds, lineLenCells, twoLettersOnly, targetCount });
    }

    function pickTarget() {
      if (fresh.length) return fresh[Math.floor(Math.random() * fresh.length)];
      if (known.length) return known[Math.floor(Math.random() * known.length)];
      const rl = uniqLetters(ctxRecord?.letters);
      if (rl.length) return rl[Math.floor(Math.random() * rl.length)];
      return "a";
    }

    // distractor pool: prefer record letters (word letters) first
    function pickDistractor(targetLetter) {
      const recordPool = uniqLetters(ctxRecord?.letters).filter(ch => ch !== targetLetter);
      if (recordPool.length) return recordPool[Math.floor(Math.random() * recordPool.length)];

      const globalPool = uniqLetters([...(known || []), ...(fresh || [])]).filter(ch => ch !== targetLetter);
      if (globalPool.length) return globalPool[Math.floor(Math.random() * globalPool.length)];

      return targetLetter;
    }

    // Default mode (old): many letters, target appears exactly twice
    function buildCellsMultiLetter(targetLetter, requestedLenCells) {
      const poolAll = uniqLetters([...(known || []), ...(fresh || [])]);
      const candidates = poolAll.filter(ch => ch && ch !== targetLetter);

      const maxLenPossible = candidates.length + 2;
      let len = clampInt(requestedLenCells, 2, 40, DEFAULT_LINE_LEN);

      if (len > maxLenPossible) {
        log("[pairletters] warning: lineLen reduced (pool too small)", {
          requested: len,
          maxPossible: maxLenPossible,
          pool: poolAll.join(""),
          target: targetLetter
        });
        len = maxLenPossible;
      }
      len = Math.max(2, len);

      const pos1 = Math.floor(Math.random() * len);
      let pos2 = Math.floor(Math.random() * len);
      while (pos2 === pos1) pos2 = Math.floor(Math.random() * len);

      const arr = new Array(len).fill("x");
      arr[pos1] = targetLetter;
      arr[pos2] = targetLetter;

      // shuffle candidates
      const available = candidates.slice();
      for (let i = available.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        const tmp = available[i];
        available[i] = available[j];
        available[j] = tmp;
      }

      let takeIdx = 0;
      for (let i = 0; i < len; i++) {
        if (i === pos1 || i === pos2) continue;
        arr[i] = available[takeIdx++] || targetLetter;
      }

      return arr;
    }

    // twoletters:true mode:
    // EXACTLY two distinct letters: target + ONE distractor (fixed per round)
    function buildCellsTwoLetter(targetLetter, distractorLetter, requestedLenCells) {
      let len = clampInt(requestedLenCells, 2, 40, DEFAULT_LINE_LEN);
      len = Math.max(2, len);

      const target = String(targetLetter || "").trim().toLowerCase() || "a";
      const distractor = String(distractorLetter || "").trim().toLowerCase() || target;

      // ensure at least 1 distractor remains
      const maxTargets = Math.max(2, len - 1);
      const tCount = Math.max(2, Math.min(maxTargets, targetCount));

      const arr = new Array(len).fill(distractor);

      // choose tCount unique positions for target
      const positions = [];
      for (let i = 0; i < len; i++) positions.push(i);
      for (let i = positions.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        const tmp = positions[i];
        positions[i] = positions[j];
        positions[j] = tmp;
      }
      for (let k = 0; k < tCount; k++) {
        arr[positions[k]] = target;
      }

      // HARD INVARIANT: only two letters
      for (let i = 0; i < arr.length; i++) {
        if (arr[i] !== target) arr[i] = distractor;
      }

      return arr;
    }

    // Rendering: in twoletters mode show letters always; in default mask selected with "é"
    function renderLineAndMapping() {
      const parts = [];
      cellCharStarts = [];
      let charPos = 0;

      for (let i = 0; i < cells.length; i++) {
        if (i > 0) {
          parts.push(" ");
          charPos += 1;
        }
        cellCharStarts[i] = charPos;

        const isSelected = (i === firstIdx || i === secondIdx);
        const shown = twoLettersOnly
          ? (cells[i] || " ")
          : (isSelected ? "é" : (cells[i] || " "));

        parts.push(shown);
        charPos += 1;
      }

      lineText = parts.join("");
      return lineText;
    }

    function toCellIndex(rawIndex) {
      if (rawIndex == null) return null;
      const idx = Number(rawIndex);
      if (!Number.isFinite(idx)) return null;
      const i = Math.floor(idx);

      if (!cells || !cells.length) return null;

      if (lineText && i >= 0 && i < lineText.length) {
        if (lineText[i] === " ") return null;
        let best = 0;
        for (let k = 0; k < cellCharStarts.length; k++) {
          if (cellCharStarts[k] <= i) best = k;
          else break;
        }
        if (best >= 0 && best < cells.length) return best;
      }

      if (i >= 0 && i < cells.length) return i;
      return null;
    }

    function sleep(ms) {
      return new Promise(r => setTimeout(r, ms));
    }

    function sendToBraille(text) {
      const t = String(text || "");
      if (window.BrailleUI && typeof window.BrailleUI.setLine === "function") {
        window.BrailleUI.setLine(t, { reason: "pairletters" });
        return Promise.resolve();
      }
      if (window.BrailleBridge && typeof window.BrailleBridge.sendText === "function") {
        return window.BrailleBridge.sendText(t).catch(() => {});
      }
      return Promise.resolve();
    }

    async function flashMessage(msg) {
      const saved = lineText;
      try {
        await sendToBraille(msg);
        await sleep(FEEDBACK_MS);
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

    function resetSelectionState() {
      stage = 0;
      firstIdx = null;
      firstLetter = "";
      secondIdx = null;
      secondLetter = "";
    }

    async function redraw(reason) {
      renderLineAndMapping();
      log("[pairletters] redraw", {
        reason,
        lineText,
        twoLettersOnly,
        target: currentTarget,
        targetCount,
        unique: Array.from(new Set(cells))
      });
      await sendToBraille(lineText);
    }

    async function nextRound(token) {
      if (token !== playToken) return;

      selectionEpoch += 1;
      inputLocked = false;
      resetSelectionState();

      currentTarget = pickTarget();

      if (twoLettersOnly) {
        const distractor = pickDistractor(currentTarget);
        cells = buildCellsTwoLetter(currentTarget, distractor, lineLenCells);

        log("[pairletters] twoletters round", {
          target: currentTarget,
          distractor,
          targetCount,
          len: cells.length,
          unique: Array.from(new Set(cells))
        });
      } else {
        cells = buildCellsMultiLetter(currentTarget, lineLenCells);
      }

      await redraw("new-round");
    }

    async function run(ctx, token) {
      ctxRecord = ctx?.record || {};
      const { knownLetters, freshLetters } = computePools(ctxRecord);
      known = knownLetters;
      fresh = freshLetters;

      readConfigFromCtx(ctx);

      log("[pairletters] start run", {
        recordId: ctxRecord?.id,
        word: ctxRecord?.word,
        knownLetters: known,
        freshLetters: fresh,
        rounds: totalRounds,
        lineLen: lineLenCells,
        twoLettersOnly,
        targetCount
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
      inputLocked = false;

      log("[pairletters] stop", payload || {});
      resolveDone({ ok: true, payload });
    }

    function isRunning() {
      return Boolean(running);
    }

    function normLetter(x) {
      const s = String(x ?? "").trim().toLowerCase();
      if (!s) return "";
      if (s === "é") return "";
      if (s.length !== 1) return "";
      if (s < "a" || s > "z") return "";
      return s;
    }

    async function handleMismatch(epochAtPick) {
      await flashMessage("fout");
      await sleep(FLIPBACK_MS);

      if (!running) return;
      if (epochAtPick !== selectionEpoch) return;

      resetSelectionState();
      await redraw("flipback");
      inputLocked = false;
    }

    async function handleMatch(epochAtPick) {
      await flashMessage("goed");
      if (!running) return;
      if (epochAtPick !== selectionEpoch) return;

      resolveRound();
    }

    function onCursor(info) {
      if (!running) return;
      if (inputLocked) return;

      const idx = toCellIndex(info?.index);
      if (idx == null) return;
      if (idx < 0 || idx >= cells.length) return;

      const letter = normLetter(cells[idx]);
      if (!letter) return;

      if (idx === firstIdx || idx === secondIdx) return;

      if (stage === 0) {
        firstIdx = idx;
        firstLetter = letter;
        stage = 1;
        redraw("pick-first").catch(() => {});
        return;
      }

      if (stage === 1) {
        secondIdx = idx;
        secondLetter = letter;
        stage = 2;

        redraw("pick-second").catch(() => {});
        const epochAtPick = selectionEpoch;

        const sameLetter = (secondLetter === firstLetter);

        // In twoletters mode distractor repeats, so only TARGET matches count.
        const isMatch = twoLettersOnly
          ? (sameLetter && secondLetter === currentTarget)
          : sameLetter;

        inputLocked = true;

        if (isMatch) handleMatch(epochAtPick).catch(() => {});
        else handleMismatch(epochAtPick).catch(() => {});
      }
    }

    return { start, stop, isRunning, onCursor };
  }

  window.Activities = window.Activities || {};
  window.Activities.pairletters = create();
})();