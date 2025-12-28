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
  const DEFAULT_LINE_LEN = 18; // aantal letters in set (wordt gepadded door BrailleBridge/words.js)
  const FLASH_MS = 450;

  function create() {
    let running = false;
    let donePromise = null;
    let doneResolve = null;

    // state per run
    let round = 0;
    let totalRounds = DEFAULT_ROUNDS;

    let line = "";          // de huidige regel die naar de leesregel is gestuurd
    let target = "";        // de letter die 2x voorkomt
    let hits = new Set();   // indices waar target is gekozen

    let known = [];
    let fresh = [];

    let playToken = 0;

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
        // neem alleen 1-char letters
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

      // "Nieuw te leren" = letters in record.letters die nog niet bekend zijn
      const knownSet = new Set(knownLetters);
      const freshLetters = letters.filter(ch => !knownSet.has(ch));

      return { knownLetters, freshLetters };
    }

    function pickTarget() {
      // voorkeur: een nieuwe letter; fallback: bekende letter; fallback: eerste uit record
      if (fresh.length) return fresh[Math.floor(Math.random() * fresh.length)];
      if (known.length) return known[Math.floor(Math.random() * known.length)];
      return "a";
    }

    function buildLine(targetLetter, lineLen) {
      // Plaats target 2x op twee verschillende posities.
      // Andere posities: allemaal unieke letters, niet gelijk aan target.
      const len = Math.max(6, Math.min(40, Number(lineLen) || DEFAULT_LINE_LEN));

      // Candidate pool voor fillers: combineer known + fresh, maar zonder target
      const pool = [...known, ...fresh].filter(ch => ch !== targetLetter);
      // Als pool te klein is, vul met alfabet
      const alphabet = "abcdefghijklmnopqrstuvwxyz".split("").filter(ch => ch !== targetLetter);
      const candidates = [...pool, ...alphabet];

      const used = new Set();
      used.add(targetLetter);

      // kies twee target posities
      const pos1 = Math.floor(Math.random() * len);
      let pos2 = Math.floor(Math.random() * len);
      while (pos2 === pos1) pos2 = Math.floor(Math.random() * len);

      const arr = new Array(len).fill("?");

      arr[pos1] = targetLetter;
      arr[pos2] = targetLetter;

      // vul rest met unieke fillers
      for (let i = 0; i < len; i++) {
        if (i === pos1 || i === pos2) continue;

        let chosen = "";
        for (let tries = 0; tries < candidates.length; tries++) {
          const c = candidates[Math.floor(Math.random() * candidates.length)];
          if (!c) continue;
          if (used.has(c)) continue;
          chosen = c;
          break;
        }
        if (!chosen) chosen = "x"; // uiterste fallback
        used.add(chosen);
        arr[i] = chosen;
      }

      return arr.join(" ");
    }

    async function sendToBraille(text) {
      // We sturen direct naar BrailleBridge, want words.js toont bij running anders activity detail.
      // Cursor events blijven binnenkomen; wij gebruiken index op onze eigen `line`.
      if (window.BrailleBridge && typeof window.BrailleBridge.sendText === "function") {
        await window.BrailleBridge.sendText(String(text || ""));
      }
    }

    async function flashMessage(msg) {
      const saved = line;
      try {
        await sendToBraille(msg);
        await new Promise(r => setTimeout(r, FLASH_MS));
      } finally {
        await sendToBraille(saved);
      }
    }

    async function nextRound(token) {
      if (token !== playToken) return;

      hits.clear();
      target = pickTarget();

      // Je kunt via activity config ook lineLen meegeven
      const lineLen = currentCtx?.activity?.lineLen ?? DEFAULT_LINE_LEN;

      line = buildLine(target, lineLen);
      log("[pairletters] round line", { round: round + 1, totalRounds, target, line });

      await sendToBraille(line);
    }

    let currentCtx = null;

    async function run(ctx, token) {
      currentCtx = ctx;

      const rec = ctx?.record || {};
      const { knownLetters, freshLetters } = computePools(rec);
      known = knownLetters;
      fresh = freshLetters;

      totalRounds = Number(ctx?.activity?.nrof ?? ctx?.activity?.nrOf ?? ctx?.activity?.nRounds ?? rec?.nrof);
      if (!Number.isFinite(totalRounds) || totalRounds <= 0) totalRounds = DEFAULT_ROUNDS;

      log("[pairletters] start run", {
        recordId: rec?.id,
        word: rec?.word,
        knownLetters: known,
        freshLetters: fresh,
        rounds: totalRounds
      });

      round = 0;

      while (round < totalRounds) {
        if (token !== playToken) return;
        await nextRound(token);

        // wacht totdat deze ronde klaar is (2 correcte keuzes)
        // dit wordt afgehandeld in onCursor()
        await waitForRoundCompletion(token);

        round += 1;
      }

      if (token !== playToken) return;

      await flashMessage("klaar");
      stop({ reason: "done" });
    }

    let roundDoneResolve = null;
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

      // laat de leesregel weer door words.js bepalen na stop (idle = woord)
      log("[pairletters] stop", payload || {});
      resolveDone({ ok: true, payload });
    }

    function isRunning() {
      return Boolean(running);
    }

    // ------------------------------------------------------------
    // Cursor keuze: routing key op een cel
    // info.index is 0..39 (van BrailleBridge/words.js)
    // We gebruiken index om in onze eigen `line` te kijken.
    // ------------------------------------------------------------
    function onCursor(info) {
      if (!running) return;

      const idx = typeof info?.index === "number" ? info.index : null;
      if (idx == null) return;

      // Onze line bevat spaties: "b a l k ..."
      // Index uit bridge is cell index. In de praktijk matcht dit het karakter in de string op de leesregel.
      // Daarom maken we een "display string" zonder extra padding: we gebruiken exact `line` zoals gestuurd.
      const s = String(line || "");
      const ch = s[idx] || "";

      // Alleen letters tellen (geen spaties)
      const letter = String(ch).trim().toLowerCase();
      if (!letter) return;

      log("[pairletters] cursor", { idx, letter, target, hits: Array.from(hits) });

      if (letter !== target) {
        hits.clear();
        flashMessage("fout").catch(() => {});
        return;
      }

      // juiste letter
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