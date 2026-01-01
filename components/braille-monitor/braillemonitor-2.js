/*!
 * braillemonitor.js – Reusable Braille monitor + thumbkey component
 * -----------------------------------------------------------------
 * CHANGE (2026-01-01):
 * - Do NOT render Unicode braille (U+2800…)
 * - Render Braille ASCII (0x20–0x5F) so the Bartimeus6Dots webfont can draw 6-dot cells.
 * - Braille line is shown ABOVE each printed character (stacked cell).
 *
 * FIX (space visibility):
 * - Ensure SPACE is visible on BOTH lines:
 *   - Braille line: show a visible blank marker (␣) instead of real ASCII space.
 *   - Print line: keep your visible-space marker (␣).
 *
 * IMPORTANT:
 * - Your CSS must apply "Bartimeus6Dots" to .monitor-cell__braille
 * - The Bartimeus6Dots font is expected to map Braille ASCII characters to dots.
 */

(function (global) {
  "use strict";

  function makeId(base, suffix) {
    return base + "_" + suffix;
  }

  function toEventLogType(level) {
    const lv = (level || "info").toLowerCase();
    if (lv === "error") return "error";
    if (lv === "warn") return "system";
    if (lv === "debug") return "system";
    return "system";
  }

  // -------------------------------------------------------------------
  // Braille ASCII mapping (6-dot)
  // -------------------------------------------------------------------

  const BRAILLE_ASCII_LETTERS = {
    a: "A", b: "B", c: "C", d: "D", e: "E",
    f: "F", g: "G", h: "H", i: "I", j: "J",
    k: "K", l: "L", m: "M", n: "N", o: "O",
    p: "P", q: "Q", r: "R", s: "S", t: "T",
    u: "U", v: "V", w: "W", x: "X", y: "Y", z: "Z"
  };

  const BRAILLE_ASCII_DIGITS = {
    "1": "A", "2": "B", "3": "C", "4": "D", "5": "E",
    "6": "F", "7": "G", "8": "H", "9": "I", "0": "J"
  };

  const BRAILLE_ASCII_PUNCT = {
    " ": " ",     // blank braille cell (ASCII space)
    ".": "4",
    ",": "1",
    ";": "2",
    ":": "3",
    "?": "8",
    "!": "6",
    "-": "-",
    "'": "'",
    "\"": "7",
    "/": "/",
    "(": "(",
    ")": ")"
  };

  const BRAILLE_FALLBACK = "?";

  // Visible markers (for both lines)
  const VISIBLE_SPACE = "␣";      // show space as a symbol in UI
  const VISIBLE_BRAILLE_SPACE = "␣"; // IMPORTANT: do NOT output real " " for braille line, otherwise it vanishes visually

  function toBrailleAsciiCell(ch) {
    if (ch == null) return " ";
    const s = String(ch);
    if (!s) return " ";

    const c = s[0];

    if (BRAILLE_ASCII_PUNCT[c]) return BRAILLE_ASCII_PUNCT[c];

    if (BRAILLE_ASCII_DIGITS[c]) return BRAILLE_ASCII_DIGITS[c];

    const lower = c.toLowerCase();
    if (BRAILLE_ASCII_LETTERS[lower]) return BRAILLE_ASCII_LETTERS[lower];

    return BRAILLE_FALLBACK;
  }

  // NEW: Convert braille cell output to a visible representation for UI.
  // For spaces, show a marker so the user sees the cell exists.
  function toVisibleBrailleCell(originalChar) {
    if (originalChar === " ") {
      return VISIBLE_BRAILLE_SPACE;
    }
    return toBrailleAsciiCell(originalChar);
  }

  const BrailleMonitor = {
    init(options) {
      const opts = Object.assign(
        {
          containerId: null,
          mapping: {},
          onCursorClick: null,
          showInfo: true,
          logger: null
        },
        options || {}
      );

      function log(source, msg, level) {
        if (opts.logger && typeof opts.logger.log === "function") {
          const type = toEventLogType(level);
          opts.logger.log(`${source}: ${msg}`, type);
          return;
        }
        if (global.Logging) {
          const fn =
            level === "error"
              ? Logging.error
              : level === "warn"
              ? Logging.warn
              : level === "debug"
              ? Logging.debug
              : Logging.info;
          fn.call(Logging, source, msg);
          return;
        }
        if (global.console && console.log) {
          console.log("[" + source + "] " + msg);
        }
      }

      if (!opts.containerId) {
        log("BrailleMonitor", "containerId is required", "error");
        return null;
      }

      const container = document.getElementById(opts.containerId);
      if (!container) {
        log("BrailleMonitor", "No element with id '" + opts.containerId + "'", "error");
        return null;
      }

      const baseId = opts.containerId;
      const monitorId = makeId(baseId, "monitor");
      const thumbRowId = makeId(baseId, "thumbRow");

      let currentText = "";

      const wrapper = document.createElement("div");
      wrapper.className = "braille-monitor-component";

      const monitorP = document.createElement("div");
      monitorP.id = monitorId;
      monitorP.className = "mono-box braille-monitor-cells";
      monitorP.setAttribute("role", "listbox");
      monitorP.setAttribute("aria-label", "Braillemonitor");

      const thumbRow = document.createElement("div");
      thumbRow.id = thumbRowId;
      thumbRow.className = "button-row thumb-row";

      const thumbDefs = [
        { key: "leftthumb", label: "•" },
        { key: "middleleftthumb", label: "••" },
        { key: "middlerightthumb", label: "••" },
        { key: "rightthumb", label: "•" }
      ];

      thumbDefs.forEach((def) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "thumb-key";
        btn.dataset.thumb = def.key;
        btn.textContent = def.label;
        thumbRow.appendChild(btn);
      });

      wrapper.appendChild(monitorP);
      wrapper.appendChild(thumbRow);

      if (opts.showInfo) {
        const info = document.createElement("p");
        info.className = "small";
        info.textContent = "";
        wrapper.appendChild(info);
      }

      container.innerHTML = "";
      container.appendChild(wrapper);

      function computeWordAt(index) {
        if (!currentText) return "";
        const len = currentText.length;
        if (index < 0 || index >= len) return "";

        let start = index;
        let end = index;

        while (start > 0 && currentText[start - 1] !== " ") start--;
        while (end < len - 1 && currentText[end + 1] !== " ") end++;

        return currentText.substring(start, end + 1).trim();
      }

      // -------------------------------------------------------------------
      // Render two lines per cell:
      // - Braille (Braille ASCII) ABOVE
      // - Print character BELOW
      // Space is visible on BOTH lines.
      // -------------------------------------------------------------------
      function rebuildCells() {
        monitorP.innerHTML = "";

        if (!currentText) {
          monitorP.textContent = "(leeg)";
          return;
        }

        for (let i = 0; i < currentText.length; i++) {
          const ch = currentText[i] || " ";

          // Print line: show visible space marker
          const printChar = ch === " " ? VISIBLE_SPACE : ch;

          // Braille line: for space show visible marker too, otherwise map to braille ascii
          const brailleCell = toVisibleBrailleCell(ch);

          const cell = document.createElement("span");
          cell.className = "monitor-cell monitor-cell--stack";
          cell.dataset.index = String(i);
          cell.setAttribute("role", "option");
          cell.setAttribute("aria-label", "Cel " + i + " teken " + ch);

          const brailleLine = document.createElement("span");
          brailleLine.className = "monitor-cell__braille";
          brailleLine.textContent = brailleCell;
          cell.appendChild(brailleLine);

          const printLine = document.createElement("span");
          printLine.className = "monitor-cell__print";
          printLine.textContent = printChar;
          cell.appendChild(printLine);

          monitorP.appendChild(cell);
        }
      }

      function handleCellClick(event) {
        const target = event.target;
        if (!target) return;

        const cell =
          target.classList && target.classList.contains("monitor-cell")
            ? target
            : target.closest
            ? target.closest(".monitor-cell")
            : null;

        if (!cell) return;

        const index = parseInt(cell.dataset.index, 10);
        if (isNaN(index)) return;

        const letter = currentText[index] || " ";
        const word = computeWordAt(index);

        if (opts.logger && typeof opts.logger.log === "function") {
          opts.logger.log(
            `BrailleMonitor: Cursor routing index=${index} letter="${letter}" word="${word}"`,
            "routing"
          );
        } else {
          log(
            "BrailleMonitor",
            "UI cursor click index=" + index + ' letter="' + letter + '" word="' + word + '"',
            "info"
          );
        }

        if (typeof opts.onCursorClick === "function") {
          try {
            opts.onCursorClick({ index, letter, word });
          } catch (err) {
            log("BrailleMonitor", "Error in onCursorClick: " + (err && err.message), "error");
          }
        }
      }

      monitorP.addEventListener("click", handleCellClick);

      function invokeThumbAction(nameLower) {
        const fn = opts.mapping[nameLower];
        if (typeof fn === "function") {
          try {
            fn();
          } catch (err) {
            log(
              "BrailleMonitor",
              "Error in thumb mapping for " + nameLower + ": " + (err && err.message),
              "error"
            );
          }
        } else {
          log("BrailleMonitor", "No mapping for thumbkey: " + nameLower, "debug");
        }
      }

      function flashThumbButton(nameLower) {
        const selector =
          "#" + thumbRowId + ' .thumb-key[data-thumb="' + nameLower.toLowerCase() + '"]';
        const btn = document.querySelector(selector);
        if (!btn) return;
        btn.classList.add("active");
        setTimeout(() => btn.classList.remove("active"), 150);
      }

      thumbRow.querySelectorAll(".thumb-key").forEach((btn) => {
        const nameLower = (btn.dataset.thumb || "").toLowerCase();
        btn.addEventListener("click", () => {
          if (opts.logger && typeof opts.logger.log === "function") {
            opts.logger.log(`BrailleMonitor: Thumbkey (sim) ${nameLower}`, "key");
          }
          invokeThumbAction(nameLower);
          flashThumbButton(nameLower);
        });
      });

      if (global.BrailleBridge && typeof global.BrailleBridge.on === "function") {
        global.BrailleBridge.on("thumbkey", (evt) => {
          const nameLower = (evt.nameLower || "").toLowerCase();

          if (opts.logger && typeof opts.logger.log === "function") {
            opts.logger.log(`BrailleBridge: Thumbkey ${nameLower}`, "key");
          } else {
            log("BrailleBridge", "Thumbkey event: " + nameLower, "info");
          }

          flashThumbButton(nameLower);
          invokeThumbAction(nameLower);
        });
      } else {
        log("BrailleMonitor", "BrailleBridge not available; cannot listen to thumbkeys", "warn");
      }

      function setText(text) {
        currentText = text != null ? String(text) : "";
        rebuildCells();

        if (opts.logger && typeof opts.logger.log === "function") {
          opts.logger.log(`BrailleMonitor: setText (${currentText.length} chars)`, "system");
        }
      }

      function clear() {
        setText("");
      }

      setText("");

      return {
        monitorId,
        thumbRowId,
        containerId: baseId,
        setText,
        clear
      };
    }
  };

  global.BrailleMonitor = BrailleMonitor;
})(window);