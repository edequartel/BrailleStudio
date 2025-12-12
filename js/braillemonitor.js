/*!
 * braillemonitor.js – Reusable Braille monitor + thumbkey component
 * -----------------------------------------------------------------
 * Usage in a page:
 *
 *   <div id="brailleMonitorComponent"></div>
 *
 *   const monitor = BrailleMonitor.init({
 *     containerId: "brailleMonitorComponent",
 *
 *     mapping: {
 *       leftthumb:  () => document.getElementById("btnHello").click(),
 *       middleleftthumb:  () => document.getElementById("btnCustom").click(),
 *       middlerightthumb: () => document.getElementById("btnRepeat").click(),
 *       rightthumb: () => document.getElementById("btnClear").click()
 *     },
 *
 *     onCursorClick(info) {
 *       // info = { index, letter, word }
 *       console.log("UI cursor click", info);
 *     }
 *   });
 *
 *   // keep monitor + braille synced:
 *   await BrailleUI.setText(text);
 *   monitor.setText(text);
 */

(function (global) {
  "use strict";

  function log(source, msg, level) {
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
    } else if (console && console.log) {
      console.log("[" + source + "] " + msg);
    }
  }

  function makeId(base, suffix) {
    return base + "_" + suffix;
  }

  const BrailleMonitor = {
    /**
     * Initialise a Braille monitor component inside the given container.
     *
     * options:
     *   - containerId: id of a <div> where markup will be injected (required)
     *   - mapping: { leftthumb, middleleftthumb, middlerightthumb, rightthumb }
     *   - onCursorClick: function({ index, letter, word })
     *   - showInfo: boolean (default true)
     */
    init(options) {
      const opts = Object.assign(
        {
          containerId: null,
          mapping: {},
          onCursorClick: null,
          showInfo: true
        },
        options || {}
      );

      if (!opts.containerId) {
        log("BrailleMonitor", "containerId is required", "error");
        return null;
      }

      const container = document.getElementById(opts.containerId);
      if (!container) {
        log(
          "BrailleMonitor",
          "No element with id '" + opts.containerId + "'",
          "error"
        );
        return null;
      }

      const baseId = opts.containerId;
      const monitorId = makeId(baseId, "monitor");
      const thumbRowId = makeId(baseId, "thumbRow");

      let currentText = "";

      // -------------------------------------------------------------------
      // Build DOM
      // -------------------------------------------------------------------
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
        { key: "leftthumb", label: "⟵ Left" },
        { key: "middleleftthumb", label: "⟵ Mid-Left" },
        { key: "middlerightthumb", label: "Mid-Right ⟶" },
        { key: "rightthumb", label: "Right ⟶" }
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
        info.textContent =
          "Deze monitor toont 1-op-1 de tekst op de brailleleesregel. " +
          "Klik op een cel om een cursorrouting te simuleren. " +
          "De knoppen bootsen de duimtoetsen na en lichten op bij echte duimtoetsen.";
        wrapper.appendChild(info);
      }

      container.innerHTML = "";
      container.appendChild(wrapper);

      // -------------------------------------------------------------------
      // Monitor rendering (clickable cells)
      // -------------------------------------------------------------------
      function computeWordAt(index) {
        if (!currentText) return "";
        const len = currentText.length;
        if (index < 0 || index >= len) return "";

        let start = index;
        let end = index;

        while (start > 0 && currentText[start - 1] !== " ") {
          start--;
        }
        while (end < len - 1 && currentText[end + 1] !== " ") {
          end++;
        }

        return currentText.substring(start, end + 1).trim();
      }

      function rebuildCells() {
        monitorP.innerHTML = "";

        if (!currentText) {
          monitorP.textContent = "(leeg)";
          return;
        }

        for (let i = 0; i < currentText.length; i++) {
          const ch = currentText[i] || " ";
          const span = document.createElement("span");
          span.className = "monitor-cell";
          span.dataset.index = String(i);
          span.textContent = ch === " " ? "␣" : ch;
          span.setAttribute("role", "option");
          span.setAttribute("aria-label", "Cel " + i + " teken " + ch);
          monitorP.appendChild(span);
        }
      }

      function handleCellClick(event) {
        const target = event.target;
        if (!target || !target.classList.contains("monitor-cell")) return;

        const index = parseInt(target.dataset.index, 10);
        if (isNaN(index)) return;

        const letter = currentText[index] || " ";
        const word = computeWordAt(index);

        log(
          "BrailleMonitor",
          "UI cursor click index=" + index + ' letter="' + letter + '" word="' + word + '"',
          "info"
        );

        if (typeof opts.onCursorClick === "function") {
          try {
            opts.onCursorClick({
              index,
              letter,
              word
            });
          } catch (err) {
            log(
              "BrailleMonitor",
              "Error in onCursorClick: " + (err && err.message),
              "error"
            );
          }
        }
      }

      monitorP.addEventListener("click", handleCellClick);

      // -------------------------------------------------------------------
      // Thumbkey helpers
      // -------------------------------------------------------------------
      function invokeThumbAction(nameLower) {
        const fn = opts.mapping[nameLower];
        if (typeof fn === "function") {
          try {
            fn();
          } catch (err) {
            log(
              "BrailleMonitor",
              "Error in thumb mapping for " +
                nameLower +
                ": " +
                (err && err.message),
              "error"
            );
          }
        } else {
          log(
            "BrailleMonitor",
            "No mapping for thumbkey: " + nameLower,
            "debug"
          );
        }
      }

      function flashThumbButton(nameLower) {
        const selector =
          "#" +
          thumbRowId +
          ' .thumb-key[data-thumb="' +
          nameLower.toLowerCase() +
          '"]';
        const btn = document.querySelector(selector);
        if (!btn) return;
        btn.classList.add("active");
        setTimeout(() => btn.classList.remove("active"), 150);
      }

      // Click on simulator buttons
      const buttons = thumbRow.querySelectorAll(".thumb-key");
      buttons.forEach((btn) => {
        const nameLower = (btn.dataset.thumb || "").toLowerCase();
        btn.addEventListener("click", () => {
          invokeThumbAction(nameLower);
          flashThumbButton(nameLower);
        });
      });

      // Listen to real thumbkeys from BrailleBridge
      if (global.BrailleBridge && typeof BrailleBridge.on === "function") {
        BrailleBridge.on("thumbkey", (evt) => {
          const nameLower = (evt.nameLower || "").toLowerCase();
          log("BrailleBridge", "Thumbkey event: " + nameLower, "info");
          flashThumbButton(nameLower);
          invokeThumbAction(nameLower);
        });
      } else {
        log(
          "BrailleMonitor",
          "BrailleBridge not available; cannot listen to thumbkeys",
          "warn"
        );
      }

      // -------------------------------------------------------------------
      // Public API for this instance
      // -------------------------------------------------------------------
      function setText(text) {
        currentText = text != null ? String(text) : "";
        rebuildCells();
      }

      function clear() {
        setText("");
      }

      // initial state
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
