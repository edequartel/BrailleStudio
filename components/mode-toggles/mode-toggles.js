/*!
 * mode-toggles.js - Reusable keyboard mode toggle indicators
 *
 * Usage:
 *   <div id="modeToggles"></div>
 *   const toggles = new ModeToggles({ containerId: "modeToggles" });
 *   toggles.setEditorMode(true);
 *   toggles.setInsertMode(false);
 *   toggles.setBlink(false);
 *   toggles.setCaret(true);
 */

(function (global) {
  "use strict";

  class ModeToggles {
    constructor(options) {
      const opts = Object.assign(
        {
          containerId: null,
          items: [
            { key: "editor", label: "Editor mode (F2)", initial: false },
            { key: "insert", label: "Insert mode (Insert)", initial: false },
            { key: "blink", label: "Blink (F3)", initial: false },
            { key: "caret", label: "Caret (F4)", initial: true }
          ]
        },
        options || {}
      );

      if (!opts.containerId) {
        throw new Error("ModeToggles: containerId is required");
      }

      const container = document.getElementById(opts.containerId);
      if (!container) {
        throw new Error("ModeToggles: No element with id '" + opts.containerId + "'");
      }

      this.container = container;
      this.items = Array.isArray(opts.items) ? opts.items : [];
      this.pills = new Map();
      this.states = new Map();

      this._render();
    }

    _render() {
      this.container.innerHTML = "";

      const root = document.createElement("div");
      root.className = "mode-toggles";

      this.items.forEach((item) => {
        if (!item || !item.key) return;

        const key = String(item.key);
        const label = String(item.label || key);
        const initial = !!item.initial;

        const block = document.createElement("div");
        block.className = "mode-toggle";
        block.dataset.key = key;

        const text = document.createElement("span");
        text.className = "mode-toggle__label";
        text.textContent = label + ":";

        const pill = document.createElement("span");
        pill.className = "mode-toggle__pill";

        block.appendChild(text);
        block.appendChild(pill);
        root.appendChild(block);

        this.pills.set(key, pill);
        this.setState(key, initial);
      });

      this.container.appendChild(root);
    }

    setState(key, enabled) {
      const k = String(key || "");
      const on = !!enabled;
      this.states.set(k, on);

      const pill = this.pills.get(k);
      if (!pill) return;

      pill.textContent = on ? "ON" : "OFF";
      pill.classList.toggle("on", on);
      pill.classList.toggle("off", !on);
    }

    getState(key) {
      return !!this.states.get(String(key || ""));
    }

    setEditorMode(enabled) { this.setState("editor", enabled); }
    setInsertMode(enabled) { this.setState("insert", enabled); }
    setBlink(enabled) { this.setState("blink", enabled); }
    setCaret(enabled) { this.setState("caret", enabled); }
  }

  global.ModeToggles = ModeToggles;
})(window);

