(() => {
  "use strict";

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
    jsDiag: document.getElementById("jsDiag"),
  };

  // Hard fail early if DOM IDs don't match
  for (const [k, v] of Object.entries(els)) {
    if (!v) {
      console.error("Profile page missing element:", k);
      alert("Profile JS error: missing element '" + k + "'. Check HTML IDs.");
      return;
    }
  }

  // Confirm JS loaded
  els.jsDiag.textContent = "JS: loaded OK";

  function defaultProfile() {
    return { name: "", age: null, address: "", updatedAt: null, version: 1 };
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

  function writeForm(profile) {
    els.name.value = profile.name ?? "";
    els.age.value = profile.age === null || profile.age === undefined ? "" : String(profile.age);
    els.address.value = profile.address ?? "";
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

  function renderPreview(profile) {
    els.jsonPreview.textContent = JSON.stringify(profile, null, 2);
  }

  function setStatus(text, ok = false) {
    els.statusText.textContent = text;
    // dot color still works even if CSS missing
    els.statusDot.style.background = ok ? "#4ade80" : "#64748b";
  }

  function saveProfile(profile) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
    setStatus("Saved " + new Date().toLocaleTimeString(), true);
    renderPreview(profile);
  }

  let saveTimer = null;
  function scheduleSave() {
    setStatus("Editing…", false);
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => saveProfile(readForm()), 200);
  }

  async function exportJson() {
    const profile = readForm();
    const text = JSON.stringify(profile, null, 2);
    renderPreview(profile);

    try {
      await navigator.clipboard.writeText(text);
      setStatus("Exported JSON to clipboard", true);
    } catch {
      setStatus("Clipboard blocked; JSON shown below", true);
    }
  }

  function resetAll() {
    localStorage.removeItem(STORAGE_KEY);
    const fresh = defaultProfile();
    writeForm(fresh);
    renderPreview(fresh);
    setStatus("Reset (nothing stored)", false);
  }

  // Init
  const existing = loadProfile();
  writeForm(existing);
  renderPreview(existing);
  setStatus(existing.updatedAt ? "Loaded from storage" : "Nothing stored yet", !!existing.updatedAt);

  // Auto-save on edits
  els.form.addEventListener("input", scheduleSave, { passive: true });
  els.form.addEventListener("change", scheduleSave, { passive: true });

  // Buttons
  els.resetBtn.addEventListener("click", resetAll);
  els.exportBtn.addEventListener("click", exportJson);

  // Extra: prove localStorage works
  console.log("localStorage origin OK, key:", STORAGE_KEY, "value:", localStorage.getItem(STORAGE_KEY));
})();