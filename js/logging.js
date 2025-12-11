/*!
 * logging.js – Simple logging framework for BrailleServer
 * -------------------------------------------------------
 * Usage:
 *   // Configure once per page:
 *   Logging.setLevel("debug"); // or "info", "warn", "error", "none"
 *   Logging.addSink(Logging.createDomSink("logBox", {
 *     maxEntries: 300,
 *     newestOnTop: false
 *   }));
 *
 *   // Use anywhere:
 *   Logging.info("BrailleUI-Demo", "Page loaded");
 *   Logging.debug("BrailleBridge", "Connecting...");
 *   Logging.error("Sounds", "Failed to load sounds.json");
 *
 * Existing BrailleLog shim:
 *   BrailleLog.log(source, message) → Logging.info(source, message)
 */

(function (global) {
  "use strict";

  const LEVELS = {
    debug: 10,
    info:  20,
    warn:  30,
    error: 40,
    none:  100
  };

  const LEVEL_NAMES = Object.keys(LEVELS);

  function clampLevelName(name) {
    if (!name) return "info";
    const lower = String(name).toLowerCase();
    return LEVEL_NAMES.includes(lower) ? lower : "info";
  }

  const Logging = {
    _levelName: "info",
    _levelValue: LEVELS.info,
    _sinks: new Set(),

    LEVELS,

    setLevel(name) {
      const lvl = clampLevelName(name);
      this._levelName = lvl;
      this._levelValue = LEVELS[lvl];
    },

    getLevel() {
      return this._levelName;
    },

    /**
     * Add a sink:
     *   sink(entry) where entry = { timestamp, level, source, message }
     */
    addSink(fn) {
      if (typeof fn !== "function") return;
      this._sinks.add(fn);
      return () => this._sinks.delete(fn);
    },

    clearSinks() {
      this._sinks.clear();
    },

    /**
     * Core log function
     */
    log(levelName, source, message) {
      const lvl = clampLevelName(levelName);
      const lvlValue = LEVELS[lvl];

      if (lvlValue < this._levelValue) {
        // filtered out
        return;
      }

      const entry = {
        timestamp: new Date(),
        level: lvl,
        source: source || "App",
        message: message != null ? String(message) : ""
      };

      // Dispatch to all sinks
      for (const sink of this._sinks) {
        try {
          sink(entry);
        } catch (err) {
          // last resort
          if (typeof console !== "undefined" && console.error) {
            console.error("[Logging] sink error:", err);
          }
        }
      }
    },

    debug(source, message) {
      this.log("debug", source, message);
    },

    info(source, message) {
      this.log("info", source, message);
    },

    warn(source, message) {
      this.log("warn", source, message);
    },

    error(source, message) {
      this.log("error", source, message);
    },

    /**
     * Fancy DOM sink that renders each log entry as a styled row.
     * elementOrId: DOM element or its id.
     * options:
     *   - maxEntries (default 500)
     *   - newestOnTop (default false)
     */
    createDomSink(elementOrId, options) {
      let el = null;
      const opts = Object.assign(
        {
          maxEntries: 500,
          newestOnTop: false
        },
        options || {}
      );

      function resolveElement() {
        if (el) return el;
        if (typeof elementOrId === "string") {
          el = document.getElementById(elementOrId);
        } else {
          el = elementOrId;
        }
        return el;
      }

      function makeClassSafe(str) {
        return String(str || "")
          .replace(/[^a-zA-Z0-9_-]/g, "_")
          .toLowerCase();
      }

      return function (entry) {
        const target = resolveElement();
        if (!target) return;

        const t = entry.timestamp.toISOString().substring(11, 19);

        // <div class="log-entry log-level-info log-source-braillebridge">
        const row = document.createElement("div");
        row.className =
          "log-entry " +
          "log-level-" + makeClassSafe(entry.level) + " " +
          "log-source-" + makeClassSafe(entry.source);

        const timeSpan = document.createElement("span");
        timeSpan.className = "log-time";
        timeSpan.textContent = t;

        const levelSpan = document.createElement("span");
        levelSpan.className = "log-level";
        levelSpan.textContent = entry.level.toUpperCase();

        const sourceSpan = document.createElement("span");
        sourceSpan.className = "log-source";
        sourceSpan.textContent = entry.source;

        const msgSpan = document.createElement("span");
        msgSpan.className = "log-message";
        msgSpan.textContent = entry.message;

        row.appendChild(timeSpan);
        row.appendChild(levelSpan);
        row.appendChild(sourceSpan);
        row.appendChild(msgSpan);

        // Insert row
        if (opts.newestOnTop && target.firstChild) {
          target.insertBefore(row, target.firstChild);
        } else {
          target.appendChild(row);
        }

        // Limit number of entries
        let childCount = target.childElementCount;
        while (childCount > opts.maxEntries) {
          if (opts.newestOnTop) {
            target.removeChild(target.lastElementChild);
          } else {
            target.removeChild(target.firstElementChild);
          }
          childCount--;
        }

        // Auto scroll (only when newest at bottom)
        if (!opts.newestOnTop && typeof target.scrollTop === "number") {
          target.scrollTop = target.scrollHeight;
        }
      };
    },

    /**
     * Default console sink.
     */
    createConsoleSink() {
      return function (entry) {
        if (typeof console === "undefined") return;
        const t = entry.timestamp.toISOString().substring(11, 19);
        const prefix =
          `[${t}] [${entry.level.toUpperCase()}] [${entry.source}]`;

        if (entry.level === "error" && console.error) {
          console.error(prefix, entry.message);
        } else if (entry.level === "warn" && console.warn) {
          console.warn(prefix, entry.message);
        } else if (console.log) {
          console.log(prefix, entry.message);
        }
      };
    }
  };

  // Default: console sink at "info" level
  Logging.addSink(Logging.createConsoleSink());
  Logging.setLevel("info");

  // Expose globally
  global.Logging = Logging;

  // Backwards-compatible BrailleLog shim:
  global.BrailleLog = {
    log(source, message) {
      Logging.info(source, message);
    }
  };

})(window);
