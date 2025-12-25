// /activities/story.js
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

  const DEFAULT_LANG = "nl";

  function create() {
    let intervalId = null;
    let session = null;

    let playToken = 0;
    let currentHowl = null;
    let currentUrl = null;

    // IMPORTANT: to resume without restarting
    let currentSoundId = null;
    let isPaused = false;

    let donePromise = null;
    let doneResolve = null;

    function isRunning() {
      return Boolean(intervalId);
    }

    async function ensureSoundsReady() {
      if (typeof Howl === "undefined") throw new Error("Howler.js not loaded");
      if (typeof Sounds === "undefined" || !Sounds || typeof Sounds.init !== "function") {
        throw new Error("Sounds.js not loaded");
      }
      await Sounds.init("../config/sounds.json", (line) => log("[Sounds]", line));
    }

    function stopCurrentHowl() {
      if (!currentHowl) return;
      try {
        currentHowl.stop(currentSoundId || undefined);
      } catch {
        // ignore
      } finally {
        currentHowl = null;
        currentUrl = null;
        currentSoundId = null;
        isPaused = false;
      }
    }

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

    function normalizeStoryKey(fileName) {
      const raw = String(fileName || "").trim();
      if (!raw) return "";
      const base = raw.split(/[\\/]/).pop() || raw;
      return base.replace(/\.[^/.]+$/, "").trim().toLowerCase();
    }

    // Start a file from the beginning (new playback)
    function startHowlFromBeginning(howl) {
      if (!howl) return;

      // ensure clean start
      try { howl.stop(); } catch {}
      try { howl.seek(0); } catch {}

      isPaused = false;
      currentSoundId = howl.play(); // store id for pause/resume correctness
    }

    function waitForHowlEnd(howl, token) {
      return new Promise((resolve, reject) => {
        if (!howl) return resolve();
        if (token !== playToken) return resolve();

        const onEnd = () => resolve();
        const onLoadError = (id, err) => reject(new Error(String(err || "loaderror")));
        const onPlayError = (id, err) => reject(new Error(String(err || "playerror")));

        howl.once("end", onEnd);
        howl.once("loaderror", onLoadError);
        howl.once("playerror", onPlayError);

        try {
          startHowlFromBeginning(howl);
        } catch (err) {
          reject(err);
        }
      });
    }

    async function playStory(ctx) {
      const token = playToken;
      const record = ctx?.record || {};
      const storyFiles = Array.isArray(record.story) ? record.story : [];
      const lang = ctx?.lang || DEFAULT_LANG;

      const indexRaw = ctx?.activity?.index;
      const parsedIndex = Number(indexRaw);
      const hasValidIndex = Number.isFinite(parsedIndex);

      log("[activity:story] audio sequence", {
        lang, count: storyFiles.length, index: hasValidIndex ? parsedIndex : indexRaw
      });

      if (!storyFiles.length) return;
      if (!hasValidIndex) return;

      await ensureSoundsReady();

      const filesToPlay =
        parsedIndex === -1 ? storyFiles :
        parsedIndex >= 0 && parsedIndex < storyFiles.length ? [storyFiles[parsedIndex]] : [];

      if (!filesToPlay.length) return;

      for (const fileName of filesToPlay) {
        if (token !== playToken) return;

        const key = normalizeStoryKey(fileName);
        if (!key) continue;

        const url = Sounds._buildUrl(lang, "stories", key);
        currentUrl = url;
        currentHowl = Sounds._getHowl(url);

        isPaused = false;
        currentSoundId = null;

        log("[activity:story] play start", { key, url, fileName: String(fileName) });

        try {
          // eslint-disable-next-line no-await-in-loop
          await waitForHowlEnd(currentHowl, token);
          log("[activity:story] play end", { key, url, fileName: String(fileName) });
        } finally {
          stopCurrentHowl();
        }
      }

      if (token !== playToken) return;
      stop({ reason: "audioEnd" });
    }

    // ------------------------------------------------------------
    // Toggle play/pause (RightThumb)
    // This MUST NOT restart from the beginning.
    // ------------------------------------------------------------
    function togglePlayPause(source) {
      if (!isRunning() || !currentHowl) {
        log("[activity:story] toggle ignored (not running/no howl)", { source });
        return;
      }

      try {
        const playingNow = currentSoundId != null
          ? currentHowl.playing(currentSoundId)
          : currentHowl.playing();

        if (playingNow) {
          // pause
          try {
            if (currentSoundId != null) currentHowl.pause(currentSoundId);
            else currentHowl.pause();
          } catch {}
          isPaused = true;
          log("[activity:story] paused", { source, url: currentUrl });
          return;
        }

        // not playing
        if (isPaused) {
          // resume
          if (currentSoundId != null) {
            currentHowl.play(currentSoundId); // resume the same id
          } else {
            // fallback: resume best-effort (Howler may allocate a new id)
            currentSoundId = currentHowl.play();
          }
          isPaused = false;
          log("[activity:story] resumed", { source, url: currentUrl });
          return;
        }

        // If it is neither playing nor paused (e.g. ended), do nothing here.
        // The activity will proceed or end naturally.
        log("[activity:story] toggle ignored (not paused, not playing)", { source, url: currentUrl });
      } catch (err) {
        log("[activity:story] toggle error", { source, message: err?.message || String(err) });
      }
    }

    function start(ctx) {
      stop({ reason: "restart" });
      ensureDonePromise();

      isPaused = false;

      session = {
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        ctx
      };

      log("[activity:story] start", {
        sessionId: session.id,
        recordId: ctx?.record?.id,
        word: ctx?.record?.word,
        storyLines: Array.isArray(ctx?.record?.story) ? ctx.record.story.length : null
      });

      let tick = 0;
      intervalId = window.setInterval(() => {
        tick += 1;
        // lightweight heartbeat
        // log("[activity:story] tick", { sessionId: session.id, tick });
      }, 750);

      playToken += 1;
      playStory(ctx).catch((err) => {
        log("[activity:story] audio error", { message: err?.message || String(err) });
        resolveDone({ ok: false, error: err?.message || String(err) });
      });

      return donePromise;
    }

    function stop(payload) {
      if (intervalId) {
        window.clearInterval(intervalId);
        intervalId = null;
      }

      playToken += 1;

      const url = currentUrl;
      if (payload?.reason && url) log("[activity:story] play stop", { url, reason: payload.reason });

      stopCurrentHowl();

      log("[activity:story] stop", { sessionId: session?.id, payload });
      session = null;

      resolveDone({ ok: true, payload });
    }

    // Expose togglePlayPause so /js/words.js can call it for RightThumb.
    return { start, stop, isRunning, togglePlayPause };
  }

  window.Activities = window.Activities || {};
  window.Activities.story = create();
})();