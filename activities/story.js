// /activities/story.js
(function () {
  "use strict";

  function log(msg, data) {
    const line = data ? `${msg} ${safeJson(data)}` : msg;
    if (typeof window.logMessage === "function") window.logMessage(line);
    else console.log(line);
  }

  function safeJson(x) {
    try {
      return JSON.stringify(x);
    } catch (err) {
      return String(x);
    }
  }

  function create() {
    let intervalId = null;
    let session = null;

    function isRunning() {
      return Boolean(intervalId);
    }

    function start(ctx) {
      stop({ reason: "restart" });
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
        log("[activity:story] tick", { sessionId: session.id, tick });
      }, 750);
    }

    function stop(payload) {
      if (!isRunning()) return;
      window.clearInterval(intervalId);
      intervalId = null;
      log("[activity:story] stop", { sessionId: session?.id, payload });
      session = null;
    }

    return { start, stop, isRunning };
  }

  window.Activities = window.Activities || {};
  if (!window.Activities.story) window.Activities.story = create();
})();

