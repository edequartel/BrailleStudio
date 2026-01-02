/*!
 * /components/braille-monitor/braillemonitor.js
 * -----------------------------------------------------------------
 * UNICODE BRAILLE MONITOR (no Bartimeus6Dots)
 *
 * - Renders Unicode braille (U+2800…U+28FF) in the UI.
 * - Stacked cells: Braille (top) + Print (bottom)
 * - Keeps visible-space marker (␣) on print line.
 * - Braille blank cell uses U+2800 (⠀).
 *
 * Optional translator hook:
 *   window.Braille.textToBrailleUnicodeLine(text) -> string
 *
 * IMPORTANT CONTRACT:
 * - The returned braille string MUST be the same length as `text`
 *   (1 braille cell per print character), otherwise we fall back to 1:1 mapping.
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

  // Visible markers (UI)
  const VISIBLE_SPACE = "␣";          // for print line
  const BRAILLE_BLANK = "⠀";         // U+2800 (blank braille pattern)
  const BRAILLE_UNKNOWN = "⣿";       // visible fallback block

  // Minimal 1:1 fallback mapping (NOT full Dutch literary braille rules)
  const BRAILLE_UNICODE_MAP = {
    a: "⠁", b: "⠃", c: "⠉", d: "⠙", e: "⠑",
    f: "⠋", g: "⠛", h: "⠓", i: "⠊", j: "⠚",
    k: "⠅", l: "⠇", m: "⠍", n: "⠝", o: "⠕",
    p: "⠏", q: "⠟", r: "⠗", s: "⠎", t: "⠞",
    u: "⠥", v: "⠧", w: "⠺", x: "⠭", y: "⠽", z: "⠵",

    " ": BRAILLE_BLANK,

    ".": "⠲",
    ",": "⠂",
    ";": "⠆",
    ":": "⠒",
    "?": "⠦",
    "!": "⠖",
    "-": "⠤",
    "'": "⠄",
    "\"": "⠶",
    "/": "⠌",
    "(": "⠐⠣",
    ")": "⠐⠜"
  };

  function fallbackBrailleCell(ch) {
    const c = String(ch ?? "");
    if (!c) return BRAILLE_BLANK;

    const direct = BRAILLE_UNICODE_MAP[c];
    if (direct) return direct;

    const lower = c.toLowerCase();
    const low = BRAILLE_UNICODE_MAP[lower];
    if (low) return low;

    // digits: simple fallback (number sign is NOT handled in 1:1 mapping)
    if (c >= "0" && c <= "9") return BRAILLE_UNKNOWN;

    return BRAILLE_UNKNOWN;
  }

  /**
   * Convert print text -> braille line for UI.
   * Uses translator hook if available AND returns 1:1 length.
   */
  function textToBrailleUnicodeLine(text) {
    const raw = String(text ?? "");

    // Preferred external translator hook
    if (global.Braille && typeof global.Braille.textToBrailleUnicodeLine === "function") {
      try {
        const out = global.Braille.textToBrailleUnicodeLine(raw);
        if (typeof out === "string" && out.length === raw.length) {
          return out;
        }
      } catch {
        // ignore and fall back
      }
    }

    // Local 1:1 fallback
    let out = "";
    for (let i = 0; i < raw.length; i++) out += fallbackBrailleCell(raw[i]);
    return out;
  }

  function visiblePrintChar(ch) {
    return ch === " " ? VISIBLE_SPACE : ch;
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

      function rebuildCells() {
        monitorP.innerHTML = "";

        if (!currentText) {
          monitorP.textContent = "(leeg)";
          return;
        }

        const brailleLine = textToBrailleUnicodeLine(currentText);

        for (let i = 0; i < currentText.length; i++) {
          const ch = currentText[i] || " ";
          const printChar = visiblePrintChar(ch);
          const brailleCell = brailleLine[i] || BRAILLE_BLANK;

          const cell = document.createElement("span");
          cell.className = "monitor-cell monitor-cell--stack";
          cell.dataset.index = String(i);
          cell.setAttribute("role", "option");
          cell.setAttribute("aria-label", "Cel " + i + " teken " + ch);

          const brailleEl = document.createElement("span");
          brailleEl.className = "monitor-cell__braille";
          brailleEl.textContent = brailleCell;
          cell.appendChild(brailleEl);

          const printEl = document.createElement("span");
          printEl.className = "monitor-cell__print";
          printEl.textContent = printChar;
          cell.appendChild(printEl);

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

        log("BrailleMonitor", "UI cursor click index=" + index + ' letter="' + letter + '" word="' + word + '"', "info");

        if (typeof opts.onCursorClick === "function") {
          try { opts.onCursorClick({ index, letter, word }); }
          catch (err) { log("BrailleMonitor", "Error in onCursorClick: " + (err && err.message), "error"); }
        }
      }

      monitorP.addEventListener("click", handleCellClick);

      function invokeThumbAction(nameLower) {
        const fn = opts.mapping[nameLower];
        if (typeof fn === "function") {
          try { fn(); }
          catch (err) { log("BrailleMonitor", "Error in thumb mapping for " + nameLower + ": " + (err && err.message), "error"); }
        } else {
          log("BrailleMonitor", "No mapping for thumbkey: " + nameLower, "debug");
        }
      }

      function flashThumbButton(nameLower) {
        const selector = "#" + thumbRowId + ' .thumb-key[data-thumb="' + nameLower.toLowerCase() + '"]';
        const btn = document.querySelector(selector);
        if (!btn) return;
        btn.classList.add("active");
        setTimeout(() => btn.classList.remove("active"), 150);
      }

      thumbRow.querySelectorAll(".thumb-key").forEach((btn) => {
        const nameLower = (btn.dataset.thumb || "").toLowerCase();
        btn.addEventListener("click", () => {
          invokeThumbAction(nameLower);
          flashThumbButton(nameLower);
        });
      });

      if (global.BrailleBridge && typeof global.BrailleBridge.on === "function") {
        global.BrailleBridge.on("thumbkey", (evt) => {
          const nameLower = (evt.nameLower || "").toLowerCase();
          flashThumbButton(nameLower);
          invokeThumbAction(nameLower);
        });
      }

      function setText(text) {
        currentText = text != null ? String(text) : "";
        rebuildCells();
      }

      function clear() {
        setText("");
      }

      setText("");

      return { monitorId, thumbRowId, containerId: baseId, setText, clear };
    }
  };

  global.BrailleMonitor = BrailleMonitor;
})(window);