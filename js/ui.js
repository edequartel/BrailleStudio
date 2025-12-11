// js/ui.js
(function (global) {
  "use strict";

  const UI = {
    /**
     * Setup logging to console + optional DOM element.
     */
    initLogging({ logElementId = null, level = "debug" } = {}) {
      if (!global.Logging) return;

      Logging.setLevel(level);
      Logging.clearSinks();
      Logging.addSink(Logging.createConsoleSink());

      if (logElementId) {
        Logging.addSink(
          Logging.createDomSink(logElementId, {
            maxEntries: 300,
            newestOnTop: false
          })
        );
      }
    },

    /**
     * Update a connection status label.
     */
    setConnectionStatus(elementId, text, ok) {
      const el = document.getElementById(elementId);
      if (!el) return;
      el.textContent = text;
      el.style.color = ok ? "green" : "red";
    },

    /**
     * Attach BrailleUI monitor to an element id.
     */
    attachBrailleMonitor(elementId) {
      if (!global.BrailleUI) return;
      BrailleUI.attachMonitor(elementId);
    }
  };

  global.UI = UI;
})(window);
