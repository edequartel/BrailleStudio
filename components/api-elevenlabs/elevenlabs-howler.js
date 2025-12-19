// file: elevenlabs-howler.js
/* global Howl */

(() => {
  const $ = (id) => document.getElementById(id);

  const els = {
    apiKey: $("apiKey"),
    voiceId: $("voiceId"),
    text: $("text"),
    modelId: $("modelId"),
    outputFormat: $("outputFormat"),
    chkTryMSE: $("chkTryMSE"),
    btnPlay: $("btnPlay"),
    btnStop: $("btnStop"),
    btnClear: $("btnClear"),
    status: $("status"),
    log: $("log"),
  };

  let currentHowl = null;
  let currentObjectUrl = null;
  let currentAbort = null;

  function setStatus(msg) {
    els.status.textContent = msg;
  }

  function log(msg) {
    const ts = new Date().toISOString().slice(11, 19);
    els.log.textContent += `[${ts}] ${msg}\n`;
    els.log.scrollTop = els.log.scrollHeight;
  }

  function cleanupAudio() {
    try {
      if (currentAbort) currentAbort.abort();
    } catch {}
    currentAbort = null;

    try {
      if (currentHowl) {
        currentHowl.stop();
        currentHowl.unload();
      }
    } catch {}
    currentHowl = null;

    if (currentObjectUrl) {
      URL.revokeObjectURL(currentObjectUrl);
      currentObjectUrl = null;
    }
  }

  function getEndpoint(voiceId, outputFormat) {
    // ElevenLabs Stream speech endpoint  [oai_citation:1‡ElevenLabs](https://elevenlabs.io/docs/api-reference/text-to-speech/stream?utm_source=chatgpt.com)
    // Docs show: POST /v1/text-to-speech/{voice_id}/stream
    const base = `https://api.elevenlabs.io/v1/text-to-speech/${encodeURIComponent(voiceId)}/stream`;
    if (!outputFormat) return base;
    // output_format is documented as a query param in some versions of the API docs (keep optional)
    const url = new URL(base);
    url.searchParams.set("output_format", outputFormat);
    return url.toString();
  }

  function buildBody(text, modelId) {
    const body = {
      text,
      // voice_settings are optional; keep conservative defaults
      voice_settings: {
        stability: 0.6,
        similarity_boost: 0.8,
      },
    };
    if (modelId && modelId.trim()) body.model_id = modelId.trim();
    return body;
  }

  async function fetchStream({ apiKey, voiceId, text, modelId, outputFormat, signal }) {
    const endpoint = getEndpoint(voiceId, outputFormat);

    const res = await fetch(endpoint, {
      method: "POST",
      headers: {
        "xi-api-key": apiKey,              // required auth header  [oai_citation:2‡ElevenLabs](https://elevenlabs.io/docs/api-reference/text-to-speech/convert?utm_source=chatgpt.com)
        "Content-Type": "application/json",
        "Accept": "audio/mpeg",
      },
      body: JSON.stringify(buildBody(text, modelId)),
      signal,
    });

    if (!res.ok) {
      const errText = await res.text().catch(() => "");
      throw new Error(`HTTP ${res.status} ${res.statusText}${errText ? ` -- ${errText}` : ""}`);
    }

    if (!res.body) {
      throw new Error("Streaming not supported by this browser (Response.body missing).");
    }

    return res;
  }

  async function playViaBlobBuffering(params) {
    log("Fallback: buffering full MP3 into a Blob…");
    setStatus("Downloading…");

    const res = await fetchStream(params);
    const arrayBuffer = await res.arrayBuffer();
    const blob = new Blob([arrayBuffer], { type: "audio/mpeg" });
    const url = URL.createObjectURL(blob);
    currentObjectUrl = url;

    return new Promise((resolve, reject) => {
      setStatus("Playing…");
      log("Starting playback (Howler) from buffered Blob URL.");

      currentHowl = new Howl({
        src: [url],
        html5: true,
        format: ["mp3"],
        onplay: () => log("Howler: play"),
        onend: () => {
          log("Howler: end");
          setStatus("Idle");
          resolve();
        },
        onloaderror: (_id, err) => reject(new Error(`Howler load error: ${err}`)),
        onplayerror: (_id, err) => reject(new Error(`Howler play error: ${err}`)),
      });

      currentHowl.play();
    });
  }

  async function playViaMediaSource(params) {
    if (!("MediaSource" in window)) {
      throw new Error("MediaSource not available in this browser.");
    }

    // Best-effort: MP3 via MSE is not universally supported (Safari often fails).
    const mime = 'audio/mpeg';
    if (!MediaSource.isTypeSupported(mime)) {
      throw new Error(`MediaSource does not support: ${mime}`);
    }

    log("Attempting true streaming via MediaSource…");
    setStatus("Streaming…");

    const ms = new MediaSource();
    const url = URL.createObjectURL(ms);
    currentObjectUrl = url;

    const howl = new Howl({
      src: [url],
      html5: true,
      // format hint
      format: ["mp3"],
      onplay: () => log("Howler: play (MediaSource)"),
      onend: () => {
        log("Howler: end");
        setStatus("Idle");
      },
      onloaderror: (_id, err) => {
        log(`Howler load error (MediaSource): ${err}`);
      },
      onplayerror: (_id, err) => {
        log(`Howler play error (MediaSource): ${err}`);
      },
    });

    currentHowl = howl;

    await new Promise((resolve, reject) => {
      ms.addEventListener("sourceopen", resolve, { once: true });
      ms.addEventListener("error", () => reject(new Error("MediaSource error")), { once: true });
    });

    const sb = ms.addSourceBuffer(mime);

    const res = await fetchStream(params);
    const reader = res.body.getReader();

    let started = false;

    const appendChunk = (chunk) =>
      new Promise((resolve, reject) => {
        const onUpdateEnd = () => {
          sb.removeEventListener("updateend", onUpdateEnd);
          resolve();
        };
        const onError = () => {
          sb.removeEventListener("error", onError);
          reject(new Error("SourceBuffer error while appending"));
        };
        sb.addEventListener("updateend", onUpdateEnd);
        sb.addEventListener("error", onError);
        sb.appendBuffer(chunk);
      });

    try {
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;

        await appendChunk(value);

        if (!started) {
          started = true;
          log("Starting playback as soon as first chunk appended.");
          setStatus("Playing…");
          howl.play();
        }
      }

      // finalize
      await new Promise((r) => {
        if (!sb.updating) return r();
        sb.addEventListener("updateend", r, { once: true });
      });

      ms.endOfStream();
      log("Stream complete.");
    } catch (e) {
      // ensure we stop to avoid hanging audio elements
      try { ms.endOfStream(); } catch {}
      throw e;
    }
  }

  async function onPlay() {
    const apiKey = els.apiKey.value.trim();
    const voiceId = els.voiceId.value.trim();
    const text = els.text.value.trim();
    const modelId = els.modelId.value.trim();
    const outputFormat = els.outputFormat.value.trim();
    const tryMSE = els.chkTryMSE.checked;

    if (!apiKey || !voiceId || !text) {
      log("Missing required fields: API key, Voice ID, and Text are required.");
      setStatus("Missing input");
      return;
    }

    cleanupAudio();

    const ac = new AbortController();
    currentAbort = ac;

    els.btnPlay.disabled = true;
    els.btnStop.disabled = false;

    const params = { apiKey, voiceId, text, modelId, outputFormat, signal: ac.signal };

    try {
      if (tryMSE) {
        try {
          await playViaMediaSource(params);
          return;
        } catch (e) {
          log(`MediaSource streaming failed; falling back. Reason: ${e.message}`);
          cleanupAudio(); // clear partial MSE artifacts before fallback
        }
      }

      await playViaBlobBuffering(params);
    } catch (e) {
      log(`ERROR: ${e.message}`);
      setStatus("Error");
    } finally {
      els.btnPlay.disabled = false;
      els.btnStop.disabled = false;
      if (setStatus && els.status.textContent === "Playing…") {
        // keep status until onend, else return to idle
      } else if (els.status.textContent !== "Playing…") {
        setStatus("Idle");
      }
    }
  }

  function onStop() {
    log("Stop pressed.");
    cleanupAudio();
    setStatus("Idle");
    els.btnPlay.disabled = false;
    els.btnStop.disabled = true;
  }

  function onClear() {
    els.log.textContent = "";
    log("Log cleared.");
  }

  // Wire up
  els.btnPlay.addEventListener("click", onPlay);
  els.btnStop.addEventListener("click", onStop);
  els.btnClear.addEventListener("click", onClear);

  // Init
  els.btnStop.disabled = true;
  setStatus("Idle");
  log("Ready.");
})();