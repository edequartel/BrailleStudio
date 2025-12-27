(() => {
  "use strict";

  // Change this key if you want multiple profiles per user/device.
  const STORAGE_KEY = "brailleserver.profile.v1";

  const els = {
    form: document.getElementById("profileForm"),
    name: document.getElementById("name"),
    age: document.getElementById("age"),
    address: document.getElementById("address"),
    resetBtn: document.getElementById("resetBtn"),
    exportBtn: document.getElementById("exportBtn"),
    statusDot: document.getElementById("statusDot"),
    statusText: document.getElementById("statusText"),
    jsonPreview: document.getElementById("jsonPreview"),
  };

  function defaultProfile() {
    return {
      name: "",
      age: null,
      address: "",
      updatedAt: null,
      version: 1,
    };
  }

  function safeParse(json) {
    try { return JSON.parse(json); } catch { return null; }
  }

  function loadProfile() {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return defaultProfile();
    const parsed = safeParse(raw);
    if (!parsed || typeof parsed !== "object") return defaultProfile();
    return { ...defaultProfile(), ...parsed };
  }

  function readForm() {
    const ageValue = els.age.value.trim();
    const age = ageValue === "" ? null : Number(ageValue);

    return {
      name: els.name.value.trim(),
      age: Number.isFinite(age) ? age : null,
      address: els.address.value.trim(),
      updatedAt: new Date().toISOString(),
      version: 1,
    };
  }

  function writeForm(profile) {
    els.name.value = profile.name ?? "";
    els.age.value = profile.age === null || profile.age === undefined ? "" : String(profile.age);
    els.address.value = profile.address ?? "";
  }

  function setStatus(saved, text) {
    els.statusDot.style.background = saved ? "var(--ok)" : "var(--idle)";
    els.statusText.textContent = text;
  }

  function renderPreview(profile) {
    els.jsonPreview.textContent = JSON.stringify(profile, null, 2);
  }

  function saveProfile(profile) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
    setStatus(true, `Saved ${new Date().toLocaleTimeString()}`);
    renderPreview(profile);
  }

  // Debounce saving so it doesn't write on every keystroke instantly.
  let saveTimer = null;
  function scheduleSave() {
    setStatus(false, "Editing…");
    if (saveTimer) clearTimeout(saveTimer);

    saveTimer = setTimeout(() => {
      const profile = readForm();
      saveProfile(profile);
    }, 250);
  }

  async function exportJson() {
    const profile = readForm();
    const text = JSON.stringify(profile, null, 2);

    try {
      await navigator.clipboard.writeText(text);
      setStatus(true, "Exported JSON to clipboard");
    } catch {
      // Fallback: show it in preview (already shown)
      setStatus(true, "Clipboard blocked; JSON shown below");
    }

    renderPreview(profile);
  }

  function resetAll() {
    localStorage.removeItem(STORAGE_KEY);
    const fresh = defaultProfile();
    writeForm(fresh);
    renderPreview(fresh);
    setStatus(false, "Reset (nothing stored)");
  }

  // Init
  const existing = loadProfile();
  writeForm(existing);
  renderPreview(existing);
  setStatus(!!existing.updatedAt, existing.updatedAt ? "Loaded from storage" : "Nothing stored yet");

  // Auto-save on edits
  ["input", "change"].forEach(evt => {
    els.form.addEventListener(evt, scheduleSave, { passive: true });
  });

  // Buttons
  els.resetBtn.addEventListener("click", resetAll);
  els.exportBtn.addEventListener("click", exportJson);
})();