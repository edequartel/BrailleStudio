(function (global) {
  "use strict";

  const DEFAULTS = {
    wsUrl: "ws://localhost:5000/ws",
    launchUrl: "braillebridge://",
    launchDelayMs: 1200,
    reconnectDelayMs: 2500,
    autoLaunch: false
  };

  const LOG_PREFIX = "[BrailleBridgeStatus]";

  const STATE_LABELS = {
    checking: "BrailleBridge controleren",
    starting: "BrailleBridge starten",
    ready: "BrailleBridge is klaar",
    partial: "BrailleBridge gedeeltelijk beschikbaar",
    offline: "BrailleBridge niet bereikbaar"
  };

  function boolAttr(value) {
    return value === "" || value === "true" || value === "1";
  }

  function parseJson(text) {
    try {
      return JSON.parse(text);
    } catch {
      return null;
    }
  }

  function toBoolean(value) {
    if (typeof value === "string") {
      return value.trim().toLowerCase() === "true" || value.trim() === "1";
    }
    return Boolean(value);
  }

  function logStatus(message, data = null, level = "info") {
    const entry = { message: `BrailleBridge status: ${message}`, data };
    const consoleMethod = level === "warn" ? console.warn : console.info;
    consoleMethod(`${LOG_PREFIX} ${message}`, data ?? "");
    if (typeof global.appendStatus === "function") {
      global.appendStatus(entry.message, entry.data);
      return;
    }
    global.__brailleBridgeStatusLogQueue = global.__brailleBridgeStatusLogQueue || [];
    global.__brailleBridgeStatusLogQueue.push(entry);
  }

  function textFromDevice(device) {
    if (!device || typeof device !== "object") return "";
    const fields = [
      device.name,
      device.Name,
      device.displayName,
      device.DisplayName,
      device.model,
      device.Model,
      device.port,
      device.Port
    ].filter(Boolean);
    return fields.length ? fields.join(" ") : "Brailleleesregel verbonden";
  }

  function getDisplayPayload(payload) {
    if (!payload || typeof payload !== "object") return null;
    const device = payload.device || payload.Device || payload.activeDevice || payload.ActiveDevice;
    return device && typeof device === "object" ? { ...device, ...payload } : payload;
  }

  class BrailleBridgeStatus {
    constructor(root, options = {}) {
      this.root = root;
      this.options = { ...DEFAULTS, ...options };
      this.ws = null;
      this.reconnectTimer = null;
      this.launchAttemptedAt = 0;
      this.lastState = {};
      this.runtimeVersion = "";
      this.displayStatus = null;
      this.lastStateLog = "";
      this.lastIncomingLabel = "WS inkomend: geen";
      this.wsMessageSeq = 0;
      this.expanded = boolAttr(root.dataset.expanded || "false");
      this.popup = boolAttr(root.dataset.popup || "false");
      this.userToggled = false;
      this.renderBase();
      this.refresh = this.refresh.bind(this);
      this.handleManualStart = this.handleManualStart.bind(this);
      this.toggleExpanded = this.toggleExpanded.bind(this);
      this.handleRootClick = this.handleRootClick.bind(this);
      this.start();
    }

    renderBase() {
      this.root.classList.add("braillebridge-status");
      this.root.classList.toggle("braillebridge-status--popup", this.popup);
      this.root.dataset.state = "checking";
      this.root.innerHTML = `
        <button class="braillebridge-status__toggle" type="button" data-role="toggle" aria-expanded="false" aria-label="BrailleBridge status openen" title="BrailleBridge status">
          <i class="ti ti-plug-connected braillebridge-status__toggle-icon" aria-hidden="true"></i>
          <span class="braillebridge-status__toggle-dot" aria-hidden="true"></span>
        </button>
        <div class="braillebridge-status__popup-panel" data-role="panel" role="dialog" aria-modal="true" aria-label="BrailleBridge status">
          <button class="btn btn-icon btn-ghost-secondary braillebridge-status__popup-close" type="button" data-role="close" aria-label="BrailleBridge status sluiten" title="Sluiten">
            <i class="ti ti-x" aria-hidden="true"></i>
          </button>
          <div class="braillebridge-status__body">
            <div class="braillebridge-status__main">
              <span class="braillebridge-status__icon" aria-hidden="true"><i class="ti ti-plug-connected"></i></span>
              <div class="braillebridge-status__text">
                <p class="braillebridge-status__title" data-role="title">BrailleBridge controleren</p>
                <div class="braillebridge-status__subtitle" data-role="subtitle">Verbinding met localhost:5000 wordt getest.</div>
              </div>
            </div>
            <div class="braillebridge-status__meta" aria-label="BrailleBridge status">
              <span class="badge bg-secondary-lt text-secondary braillebridge-status__badge" data-role="http"><span class="braillebridge-status__dot"></span>Runtime</span>
              <span class="badge bg-secondary-lt text-secondary braillebridge-status__badge" data-role="ws"><span class="braillebridge-status__dot"></span>WebSocket</span>
              <span class="badge bg-secondary-lt text-secondary braillebridge-status__badge" data-role="device"><span class="braillebridge-status__dot"></span>Leesregel</span>
              <span class="badge bg-secondary-lt text-secondary braillebridge-status__badge" data-role="sam"><span class="braillebridge-status__dot"></span>SAM</span>
              <span class="badge bg-secondary-lt text-secondary braillebridge-status__badge" data-role="version">Versie onbekend</span>
              <span class="badge bg-secondary-lt text-secondary braillebridge-status__badge braillebridge-status__incoming" data-role="incoming">WS inkomend: geen</span>
              <button class="btn btn-sm btn-outline-primary braillebridge-status__action" type="button" data-role="start">Start</button>
              <button class="btn btn-sm btn-outline-secondary braillebridge-status__action" type="button" data-role="test">Test</button>
              <button class="btn btn-sm btn-outline-secondary braillebridge-status__header-toggle" type="button" data-role="header-toggle" aria-label="BrailleBridge status sluiten" title="Samenvouwen">
                <i class="ti ti-chevron-up" aria-hidden="true"></i>
              </button>
            </div>
          </div>
          <div class="braillebridge-status__details">
            <div class="braillebridge-status__detail">
              <div class="braillebridge-status__detail-label">BrailleBridge</div>
              <div class="braillebridge-status__detail-value" data-role="runtime-detail">Controleren</div>
            </div>
            <div class="braillebridge-status__detail">
              <div class="braillebridge-status__detail-label">WebSocket</div>
              <div class="braillebridge-status__detail-value" data-role="websocket-detail">Controleren</div>
            </div>
            <div class="braillebridge-status__detail">
              <div class="braillebridge-status__detail-label">Brailleleesregel</div>
              <div class="braillebridge-status__detail-value" data-role="display-detail">Controleren</div>
            </div>
            <div class="braillebridge-status__detail">
              <div class="braillebridge-status__detail-label">SAM</div>
              <div class="braillebridge-status__detail-value" data-role="sam-detail">Controleren</div>
            </div>
          </div>
        </div>
      `;
      this.toggleEl = this.root.querySelector('[data-role="toggle"]');
      this.headerToggleEl = this.root.querySelector('[data-role="header-toggle"]');
      this.closeEl = this.root.querySelector('[data-role="close"]');
      this.titleEl = this.root.querySelector('[data-role="title"]');
      this.subtitleEl = this.root.querySelector('[data-role="subtitle"]');
      this.httpEl = this.root.querySelector('[data-role="http"]');
      this.wsEl = this.root.querySelector('[data-role="ws"]');
      this.deviceEl = this.root.querySelector('[data-role="device"]');
      this.samEl = this.root.querySelector('[data-role="sam"]');
      this.versionEl = this.root.querySelector('[data-role="version"]');
      this.incomingEl = this.root.querySelector('[data-role="incoming"]');
      this.runtimeDetailEl = this.root.querySelector('[data-role="runtime-detail"]');
      this.websocketDetailEl = this.root.querySelector('[data-role="websocket-detail"]');
      this.displayDetailEl = this.root.querySelector('[data-role="display-detail"]');
      this.samDetailEl = this.root.querySelector('[data-role="sam-detail"]');
      this.startEl = this.root.querySelector('[data-role="start"]');
      this.testEl = this.root.querySelector('[data-role="test"]');
      this.startEl?.addEventListener("click", this.handleManualStart);
      this.testEl?.addEventListener("click", this.refresh);
      if (this.toggleEl) {
        this.toggleEl.onclick = (event) => {
          event.preventDefault();
          event.stopPropagation();
          this.setExpanded(true);
        };
      }
      if (this.headerToggleEl) {
        this.headerToggleEl.onclick = (event) => {
          event.preventDefault();
          event.stopPropagation();
          this.setExpanded(false);
        };
      }
      if (this.closeEl) {
        this.closeEl.onclick = (event) => {
          event.preventDefault();
          event.stopPropagation();
          this.setExpanded(false);
        };
      }
      this.root.addEventListener("click", this.handleRootClick, true);
      this.applyExpanded();
    }

    start() {
      logStatus("init", {
        wsUrl: this.options.wsUrl,
        launchUrl: this.options.launchUrl,
        autoLaunch: this.options.autoLaunch
      });
      this.connectWebSocket();
    }

    stop() {
      if (this.reconnectTimer) {
        global.clearTimeout(this.reconnectTimer);
        this.reconnectTimer = null;
      }
      if (this.ws) {
        try { this.ws.close(1000, "status component stopped"); } catch {}
        this.ws = null;
      }
    }

    connectWebSocket() {
      if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
        logStatus("WS -> connect skipped", { reason: "already open or connecting" });
        return;
      }
      logStatus(`WS -> connect ${this.options.wsUrl}`);
      this.applyVisualState("checking", this.buildState({ wsOk: false, detail: "WebSocket verbinden" }));
      try {
        const ws = new WebSocket(this.options.wsUrl);
        this.ws = ws;
        ws.addEventListener("open", () => this.handleWebSocketOpen());
        ws.addEventListener("message", (event) => this.handleWebSocketMessage(event.data));
        ws.addEventListener("close", () => this.handleWebSocketClose());
        ws.addEventListener("error", () => this.handleWebSocketError());
      } catch (err) {
        this.handleWebSocketError(err);
      }
    }

    handleWebSocketOpen() {
      logStatus(`WS <- open ${this.options.wsUrl}`);
      logStatus("WS state", { readyState: this.ws?.readyState ?? null });
    }

    async handleWebSocketMessage(raw) {
      let text = "";
      if (typeof raw === "string") {
        text = raw;
      } else if (raw && typeof raw.text === "function") {
        text = await raw.text();
      }
      const messageId = ++this.wsMessageSeq;
      logStatus(`WS <- raw [${messageId}]`, text || "(empty)");
      const message = text ? parseJson(text) : null;
      const messageType = String(message?.type || "").trim();
      const hasStatusFields = message && [
        "bridgeVersion", "version", "samLoaded", "samFound", "samActive",
        "displayConnected", "displayName"
      ].some((key) => Object.prototype.hasOwnProperty.call(message, key));
      const isStatusMessage = message && (
        messageType === "brailleDisplayStatus" ||
        messageType === "hello" ||
        messageType === "status" ||
        messageType === "bridgeStatus" ||
        hasStatusFields
      );
      if (!isStatusMessage) {
        logStatus(`WS <- ignored [${messageId}]`, {
          type: message?.type || "(unknown)",
          bytes: text ? text.length : 0
        });
        return;
      }
      this.lastIncomingLabel = `WS inkomend: ${messageType} ${new Date().toLocaleTimeString("nl-NL", { hour: "2-digit", minute: "2-digit", second: "2-digit" })}`;
      logStatus(`WS <- ${messageType} [${messageId}]`, message);
      this.displayStatus = this.normalizeStatusMessage(message);
      this.runtimeVersion = this.displayStatus.bridgeVersion;
      this.applyState(this.buildState({
        wsOk: true,
        changing: this.displayStatus.reason === "samConfigStart",
        detail: this.displayStatus.reason || ""
      }));
    }

    handleWebSocketClose() {
      this.ws = null;
      logStatus("WS <- close", {
        reconnectDelayMs: this.options.reconnectDelayMs
      });
      this.setOffline("WebSocket gesloten");
      this.tryAutoLaunch("WebSocket gesloten");
      this.scheduleReconnect();
    }

    handleWebSocketError(err = null) {
      logStatus("WS <- error", { error: err?.message || String(err || "WebSocket fout") }, "warn");
      this.setOffline(err?.message || "WebSocket fout");
      this.tryAutoLaunch(err?.message || "WebSocket fout");
      this.scheduleReconnect();
    }

    scheduleReconnect() {
      if (this.reconnectTimer) return;
      logStatus("WS -> reconnect scheduled", { delayMs: this.options.reconnectDelayMs });
      this.reconnectTimer = global.setTimeout(() => {
        this.reconnectTimer = null;
        logStatus("WS -> reconnect attempt");
        this.connectWebSocket();
      }, this.options.reconnectDelayMs);
    }

    normalizeStatusMessage(message) {
      const nested = message && typeof message.status === "object"
        ? message.status
        : (message && typeof message.data === "object" ? message.data : {});
      const source = { ...message, ...nested };
      return {
        ...source,
        bridgeVersion: String(source?.bridgeVersion ?? source?.bridge?.version ?? source?.version ?? source?.Version ?? ""),
        samLoaded: toBoolean(source?.samLoaded ?? source?.SamLoaded),
        samFound: toBoolean(source?.samFound ?? source?.SamFound),
        samActive: toBoolean(source?.samActive ?? source?.SamActive),
        displayConnected: toBoolean(source?.displayConnected ?? source?.connected ?? source?.Connected),
        displayName: String(source?.displayName ?? source?.name ?? source?.Name ?? "")
      };
    }

    setOffline(detail) {
      this.runtimeVersion = "";
      this.displayStatus = null;
      this.lastIncomingLabel = "WS inkomend: geen";
      this.applyState(this.buildState({ wsOk: false, detail }));
    }

    buildState(overrides = {}) {
      const displayPayload = getDisplayPayload(this.displayStatus);
      const deviceOk = Boolean(displayPayload?.displayConnected);
      const changing = Boolean(overrides.changing || displayPayload?.reason === "samConfigStart");
      const samOk = Boolean(displayPayload?.samLoaded && displayPayload?.samFound && displayPayload?.samActive);
      return {
        httpOk: Boolean(overrides.wsOk),
        wsOk: Boolean(overrides.wsOk),
        deviceOk,
        samOk,
        changing,
        version: this.runtimeVersion ? { version: this.runtimeVersion } : null,
        display: displayPayload,
        displayStatus: displayPayload,
        runtimeStatus: null,
        detail: overrides.detail || ""
      };
    }

    tryAutoLaunch(reason) {
      if (!this.options.autoLaunch) return false;
      return this.tryLaunch(`automatic: ${reason || "WebSocket offline"}`);
    }

    tryLaunch(reason = "manual") {
      const now = Date.now();
      if (now - this.launchAttemptedAt < 30000) return false;
      this.launchAttemptedAt = now;
      logStatus("launch -> braillebridge://", { reason });
      this.applyVisualState("starting");
      const frame = document.createElement("iframe");
      frame.hidden = true;
      frame.src = this.options.launchUrl;
      document.body.appendChild(frame);
      global.setTimeout(() => frame.remove(), this.options.launchDelayMs);
      return true;
    }

    handleManualStart(event) {
      event?.preventDefault?.();
      event?.stopPropagation?.();
      this.launchAttemptedAt = 0;
      this.tryLaunch("manual start button");
      global.setTimeout(() => this.connectWebSocket(), this.options.launchDelayMs);
    }

    async refresh(event = null) {
      event?.preventDefault?.();
      event?.stopPropagation?.();
      logStatus("test -> WebSocket reconnect", { wsUrl: this.options.wsUrl });
      if (this.ws) {
        try { this.ws.close(1000, "manual WebSocket test"); } catch {}
        this.ws = null;
      }
      this.connectWebSocket();
    }

    toggleExpanded() {
      this.userToggled = true;
      this.expanded = !this.expanded;
      this.applyExpanded();
    }

    setExpanded(expanded) {
      this.userToggled = true;
      this.expanded = Boolean(expanded);
      this.applyExpanded();
    }

    handleRootClick(event) {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) return;
      if (target.closest('[data-role="start"], [data-role="test"]')) return;
      const isToggleClick = Boolean(target.closest('[data-role="toggle"], [data-role="header-toggle"], [data-role="close"]'));
      const isCompactClick = !this.expanded && Boolean(target.closest(".braillebridge-status"));
      if (!isToggleClick && !isCompactClick) return;
      event.preventDefault();
      event.stopPropagation();
      this.setExpanded(isToggleClick ? !this.expanded : true);
    }

    applyState(next) {
      this.lastState = next;
      let state = "offline";
      if (next.changing) {
        state = "starting";
      } else if (next.wsOk && next.deviceOk && next.samOk) {
        state = "ready";
      } else if (next.wsOk) {
        state = "partial";
      }
      this.applyVisualState(state, next);
      const stateLog = {
        state,
        runtime: next.wsOk,
        websocket: next.wsOk,
        display: next.deviceOk,
        sam: next.samOk,
        changing: next.changing,
        name: next.display?.name || next.display?.Name || "",
        version: next.display?.version || next.display?.Version || next.version?.version || ""
      };
      const stateLogKey = JSON.stringify(stateLog);
      if (stateLogKey !== this.lastStateLog) {
        this.lastStateLog = stateLogKey;
        logStatus("state", stateLog);
      }
      if (this.popup && !this.userToggled) {
        this.expanded = false;
        this.applyExpanded();
      } else if (state !== "ready" && !this.userToggled) {
        this.expanded = true;
        this.applyExpanded();
      } else if (!this.userToggled) {
        this.expanded = false;
        this.applyExpanded();
      }
      this.root.dispatchEvent(new CustomEvent("braillebridge-status", {
        bubbles: true,
        detail: { ...next, state }
      }));
    }

    applyVisualState(state, data = this.lastState) {
      this.root.dataset.state = state;
      if (this.toggleEl) {
        this.toggleEl.title = this.getSubtitle(state, data);
      }
      if (this.titleEl) this.titleEl.textContent = state === "ready" ? "BrailleBridge" : (STATE_LABELS[state] || STATE_LABELS.checking);
      if (this.subtitleEl) this.subtitleEl.textContent = this.getSubtitle(state, data);
      this.setBadge(this.httpEl, data.wsOk, data.wsOk ? "Runtime actief" : "Runtime offline");
      this.setBadge(this.wsEl, data.wsOk, data.wsOk ? "WebSocket ok" : "WebSocket offline");
      this.setBadge(this.deviceEl, data.deviceOk, data.changing ? "Leesregel wijzigt" : (data.deviceOk ? "Leesregel verbonden" : "Geen leesregel"));
      this.setBadge(this.samEl, data.samOk, data.changing ? "SAM wijzigt" : (data.samOk ? "SAM actief" : "SAM niet actief"));
      if (this.incomingEl) {
        const hasIncoming = this.lastIncomingLabel !== "WS inkomend: geen";
        this.incomingEl.textContent = this.lastIncomingLabel;
        this.incomingEl.className = `badge ${hasIncoming ? "bg-primary-lt text-primary" : "bg-secondary-lt text-secondary"} braillebridge-status__badge braillebridge-status__incoming`;
      }
      if (this.versionEl) {
        const version = data.display?.version || data.display?.Version || data.version?.version || data.version?.informationalVersion || "";
        this.versionEl.textContent = version ? `Versie ${version}` : "Versie onbekend";
        this.versionEl.className = `badge ${version ? "bg-primary-lt text-primary" : "bg-secondary-lt text-secondary"} braillebridge-status__badge`;
      }
      if (this.runtimeDetailEl) {
        const runtimeVersion = data.version?.version || "";
        this.runtimeDetailEl.textContent = data.wsOk
          ? `Actief${runtimeVersion ? `, versie ${runtimeVersion}` : ""}`
          : `Offline${data.detail ? `: ${data.detail}` : ""}`;
      }
      if (this.websocketDetailEl) {
        this.websocketDetailEl.textContent = data.wsOk ? this.options.wsUrl : "Niet verbonden";
      }
      if (this.displayDetailEl) {
        const displayName = textFromDevice(data.display);
        const displayVersion = data.display?.version || data.display?.Version || "";
        this.displayDetailEl.textContent = data.changing
          ? "Configuratie wijzigt"
          : data.deviceOk
          ? `${displayName || "Verbonden"}${displayVersion ? `, versie ${displayVersion}` : ""}`
          : "Niet verbonden";
      }
      if (this.samDetailEl) {
        this.samDetailEl.textContent = this.getSamDetail(data.display);
      }
      if (this.startEl) {
        this.startEl.hidden = state === "ready";
      }
    }

    setBadge(el, ok, text) {
      if (!el) return;
      el.className = `badge ${ok ? "bg-success-lt text-success" : "bg-danger-lt text-danger"} braillebridge-status__badge`;
      el.innerHTML = `<span class="braillebridge-status__dot"></span>${text}`;
    }

    getSubtitle(state, data) {
      if (state === "starting") return "BrailleBridge wordt geopend. Bevestig de browsermelding als die verschijnt.";
      if (state === "ready") {
        const deviceText = textFromDevice(data.display);
        return deviceText ? `${deviceText} is klaar voor gebruik.` : "Runtime, WebSocket en brailleleesregel zijn klaar.";
      }
      if (state === "partial") {
        if (!data.deviceOk) return "BrailleBridge draait, maar er is nog geen fysieke brailleleesregel actief.";
        if (!data.samOk) return "BrailleBridge draait, maar SAM is nog niet actief.";
        if (!data.wsOk) return "Runtime reageert, maar de WebSocket is nog niet verbonden.";
        return "Een deel van BrailleBridge is bereikbaar.";
      }
      return "Start BrailleBridge en sluit een brailleleesregel aan.";
    }

    getSamDetail(display) {
      if (!display || typeof display !== "object") return "Onbekend";
      const loaded = Boolean(display.samLoaded ?? display.SamLoaded);
      const found = Boolean(display.samFound ?? display.SamFound);
      const active = Boolean(display.samActive ?? display.SamActive);
      return `geladen ${loaded ? "ja" : "nee"}, gevonden ${found ? "ja" : "nee"}, actief ${active ? "ja" : "nee"}`;
    }

    applyExpanded() {
      this.root.classList.toggle("is-collapsed", !this.expanded);
      this.root.classList.toggle("is-popup-open", this.popup && this.expanded);
      if (this.toggleEl) {
        this.toggleEl.setAttribute("aria-expanded", this.expanded ? "true" : "false");
        this.toggleEl.setAttribute("aria-label", this.expanded ? "BrailleBridge status sluiten" : "BrailleBridge status openen");
      }
      if (this.headerToggleEl) {
        this.headerToggleEl.hidden = this.popup;
        this.headerToggleEl.setAttribute("aria-expanded", this.expanded ? "true" : "false");
        this.headerToggleEl.innerHTML = `<i class="ti ${this.expanded ? "ti-chevron-up" : "ti-chevron-down"}" aria-hidden="true"></i>`;
        this.headerToggleEl.title = this.expanded ? "Samenvouwen" : "Details openen";
      }
      if (this.closeEl) {
        this.closeEl.hidden = !this.popup;
      }
    }
  }

  function init(root) {
    const options = {
      wsUrl: root.dataset.wsUrl || DEFAULTS.wsUrl,
      launchUrl: root.dataset.launchUrl || DEFAULTS.launchUrl,
      reconnectDelayMs: Number(root.dataset.reconnectDelayMs || DEFAULTS.reconnectDelayMs),
      autoLaunch: boolAttr(root.dataset.autoLaunch || "false")
    };
    return new BrailleBridgeStatus(root, options);
  }

  global.BrailleBridgeStatus = {
    init,
    initAll() {
      return Array.from(document.querySelectorAll("[data-braillebridge-status]")).map((root) => {
        if (root.__brailleBridgeStatus) return root.__brailleBridgeStatus;
        root.__brailleBridgeStatus = init(root);
        return root.__brailleBridgeStatus;
      });
    }
  };

  function setRootExpanded(root, expanded) {
    const isExpanded = Boolean(expanded);
    root.classList.toggle("is-collapsed", !isExpanded);
    const toggle = root.querySelector('[data-role="toggle"]');
    const headerToggle = root.querySelector('[data-role="header-toggle"]');
    const close = root.querySelector('[data-role="close"]');
    if (toggle) {
      toggle.setAttribute("aria-expanded", isExpanded ? "true" : "false");
      toggle.setAttribute("aria-label", isExpanded ? "BrailleBridge status sluiten" : "BrailleBridge status openen");
    }
    if (headerToggle) {
      headerToggle.hidden = Boolean(root.__brailleBridgeStatus?.popup);
      headerToggle.setAttribute("aria-expanded", isExpanded ? "true" : "false");
      headerToggle.innerHTML = `<i class="ti ${isExpanded ? "ti-chevron-up" : "ti-chevron-down"}" aria-hidden="true"></i>`;
      headerToggle.title = isExpanded ? "Samenvouwen" : "Details openen";
    }
    if (close) {
      close.hidden = !Boolean(root.__brailleBridgeStatus?.popup);
    }
    if (root.__brailleBridgeStatus) {
      root.__brailleBridgeStatus.userToggled = true;
      root.__brailleBridgeStatus.expanded = isExpanded;
      root.__brailleBridgeStatus.applyExpanded();
    }
  }

  async function runFallbackTest(root) {
    const wsUrl = root?.dataset?.wsUrl || DEFAULTS.wsUrl;
    logStatus("test -> fallback WebSocket", { wsUrl });
    try {
      logStatus(`WS -> fallback connect ${wsUrl}`);
      const ws = new WebSocket(wsUrl);
      ws.addEventListener("open", () => {
        logStatus(`WS <- fallback open ${wsUrl}`);
        ws.close(1000, "fallback status test done");
      }, { once: true });
      ws.addEventListener("message", async (event) => {
        const text = typeof event.data === "string"
          ? event.data
          : event.data && typeof event.data.text === "function"
          ? await event.data.text()
          : "";
        logStatus("WS <- fallback message", parseJson(text) ?? text);
      });
      ws.addEventListener("close", () => logStatus("WS <- fallback close"), { once: true });
      ws.addEventListener("error", () => logStatus("WS <- fallback error"), { once: true });
    } catch (err) {
      logStatus("WS xx fallback", { error: err?.message || String(err) }, "warn");
    }
  }

  document.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;
    const openPopup = document.querySelector('[data-braillebridge-status].braillebridge-status--popup.is-popup-open');
    if (openPopup && target === openPopup) {
      event.preventDefault();
      event.stopPropagation();
      setRootExpanded(openPopup, false);
      return;
    }
    if (openPopup && !target.closest('[data-braillebridge-status].braillebridge-status--popup.is-popup-open')) {
      setRootExpanded(openPopup, false);
      return;
    }
    const testButton = target.closest('[data-braillebridge-status] [data-role="test"]');
    if (testButton) {
      const testRoot = testButton.closest("[data-braillebridge-status]");
      event.preventDefault();
      event.stopPropagation();
      if (testRoot?.__brailleBridgeStatus?.refresh) {
        testRoot.__brailleBridgeStatus.refresh(event);
      } else if (testRoot) {
        runFallbackTest(testRoot);
      }
      return;
    }
    if (target.closest('[data-braillebridge-status] [data-role="start"]')) return;

    const headerToggle = target.closest('[data-braillebridge-status] [data-role="header-toggle"], [data-braillebridge-status] [data-role="close"]');
    const toggle = target.closest('[data-braillebridge-status] [data-role="toggle"]');
    const compactRoot = target.closest('[data-braillebridge-status].is-collapsed');
    const root = headerToggle?.closest("[data-braillebridge-status]")
      || toggle?.closest("[data-braillebridge-status]")
      || compactRoot;
    if (!root) return;

    event.preventDefault();
    event.stopPropagation();
    setRootExpanded(root, !headerToggle);
  }, true);

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    const openPopup = document.querySelector('[data-braillebridge-status].braillebridge-status--popup.is-popup-open');
    if (!openPopup) return;
    event.preventDefault();
    setRootExpanded(openPopup, false);
  }, true);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => global.BrailleBridgeStatus.initAll());
  } else {
    global.BrailleBridgeStatus.initAll();
  }
})(window);
