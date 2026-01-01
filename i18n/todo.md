Below is a practical, repo-friendly, step-by-step migration plan to localize BrailleServer (UI + activities + audio + braille rules) without rewriting everything at once. It assumes your current flow is: runner loads words.json once → passes activity data via ctx → activities render via BrailleUI and play audio.

⸻

Step 0 — Decide your localization scope (do this once)

You will localize four categories separately:
	1.	UI strings (buttons, headings, labels, errors)
	2.	Activity text (captions, instructions, feedback)
	3.	Content data (words.json text fields or instruction fields)
	4.	Audio (voice prompts and feedback mp3 per language)
	5.	(Optional but recommended) Braille rules per language (capital/number signs, punctuation)

Do not mix these concerns in one file.

⸻

Step 1 — Add a language selector (minimal)

Add one place where language is determined:

Rules (recommended precedence):
	1.	?lang=en in URL
	2.	localStorage.appLang
	3.	default nl

Create /js/locale.js:

(function () {
  “use strict”;

  function getLangFromUrl() {
    try {
      const u = new URL(window.location.href);
      return u.searchParams.get(“lang”);
    } catch {
      return null;
    }
  }

  const urlLang = getLangFromUrl();
  const stored = localStorage.getItem(“appLang”);
  const lang = (urlLang || stored || “nl”).toLowerCase();

  window.AppLocale = {
    lang,
    fallback: “en”
  };
})();

Include it early in your HTML (before runner):

<script src=“../js/locale.js”></script>


⸻

Step 2 — Create an i18n folder and language packs

Add:

/i18n/nl/ui.json
/i18n/en/ui.json
/i18n/nl/activity.json
/i18n/en/activity.json

Example /i18n/en/ui.json:

{
  “ui.start”: “Start”,
  “ui.stop”: “Stop”,
  “ui.settings”: “Settings”
}

Example /i18n/nl/activity.json:

{
  “feedback.correct”: “Goed gedaan!”,
  “feedback.incorrect”: “Dat klopt niet.”,
  “activity.generic.ready”: “Klaar om te beginnen.”
}


⸻

Step 3 — Add a single i18n loader + translator function

Create /js/i18n.js:

(function () {
  “use strict”;

  async function fetchJson(url) {
    const r = await fetch(url, { cache: “no-store” });
    if (!r.ok) throw new Error(`i18n fetch failed ${r.status} ${url}`);
    return r.json();
  }

  window.I18N = {}; // { nl: {key:value}, en: {key:value} }

  window.loadI18N = async function () {
    const lang = window.AppLocale?.lang || “nl”;
    const fallback = window.AppLocale?.fallback || “en”;

    const [uiLang, actLang, uiFb, actFb] = await Promise.all([
      fetchJson(`../i18n/${lang}/ui.json`).catch(() => ({})),
      fetchJson(`../i18n/${lang}/activity.json`).catch(() => ({})),
      fetchJson(`../i18n/${fallback}/ui.json`).catch(() => ({})),
      fetchJson(`../i18n/${fallback}/activity.json`).catch(() => ({}))
    ]);

    window.I18N[lang] = { ...uiLang, ...actLang };
    window.I18N[fallback] = { ...uiFb, ...actFb };
  };

  window.t = function (key, vars = {}) {
    const lang = window.AppLocale?.lang || “nl”;
    const fallback = window.AppLocale?.fallback || “en”;

    const dictL = window.I18N?.[lang] || {};
    const dictF = window.I18N?.[fallback] || {};
    let s = dictL[key] ?? dictF[key] ?? key;

    s = String(s);
    return s.replace(/\{(\w+)\}/g, (_, k) => (vars[k] ?? “”));
  };
})();

Include it before runner:

<script src=“../js/i18n.js”></script>


⸻

Step 4 — Make runner load i18n before starting activities

In your page bootstrap (or in runner.js init), do:

await window.loadI18N();

Then start runner normally.

Minimum integration point:
Where you currently do “start app”, add:

(async () => {
  await window.loadI18N();
  // then load words.json and start runner
})();


⸻

Step 5 — Localize words.json without breaking runner

You have two viable approaches. Pick one.

Option A (fastest): language-specific words files

Create:

/data/nl/words.json
/data/en/words.json

Then in runner:

const lang = window.AppLocale.lang;
const url = `../data/${lang}/words.json`;
const wordsData = await (await fetch(url)).json();

This keeps your current schema intact and is the lowest-risk change.

Option B (cleaner long-term): keep structure, replace text with keys

Keep one words.json, but replace:
	•	caption → captionKey
	•	text → textKey or instructionKey

Example:

{
  “id”: “bal-001”,
  “captionKey”: “word.bal.caption”,
  “instructionKey”: “word.bal.instruction”
}

Then put actual strings per language in i18n packs.

This avoids duplicating large JSON content in multiple languages.

Recommendation: start with Option A, migrate later to Option B.

⸻

Step 6 — Update activities to never hardcode human strings

In each activity module:
	•	Replace “goed gedaan” with t(“feedback.correct”)
	•	Replace labels with t(“...”)

Example:

window.BrailleUI.setLine(window.t(“feedback.correct”));

If you must show an activity caption:

window.BrailleUI.setLine(ctx.activityCaption || window.t(“activity.generic.ready”));


⸻

Step 7 — Localize feedback audio and instruction audio

Create a resolver so activities don’t know paths.

Add to a shared audio helper (or in your existing player):

window.resolveSound = function (relPath) {
  const lang = window.AppLocale?.lang || “nl”;
  // convention:
  // /sounds/<lang>/...
  // /sounds/shared/...
  return `../sounds/${lang}/${relPath}`;
};

Then in i18n you store relative paths:

/i18n/en/activity.json

{
  “feedback.correct.audio”: “ui/correct.mp3”,
  “feedback.incorrect.audio”: “ui/incorrect.mp3”
}

Usage:

audio.play(window.resolveSound(window.t(“feedback.correct.audio”)));

Fallback: if file not found, try shared.

⸻

Step 8 — Add accessible language-aware ARIA labels (easy win)

For any clickable UI:

<button id=“startBtn”></button>

In JS:

startBtn.textContent = t(“ui.start”);
startBtn.setAttribute(“aria-label”, t(“ui.start”));

For feedback regions:

<div id=“live” aria-live=“polite”></div>

Then:

live.textContent = t(“feedback.correct”);


⸻

Step 9 — Braille language rules (recommended)

If you are converting text to braille (capital sign, number sign, punctuation), add per-language rules:

/braille/nl.json
/braille/en.json

Load it once in runner, store in ctx.brailleTable, and ensure the conversion function reads that table.

This prevents “Dutch braille assumptions” leaking into English (and later other languages).

⸻

Step 10 — Add a regression-proof test checklist

After each step, test:
	1.	?lang=nl loads Dutch UI + words + audio
	2.	?lang=en loads English UI + words + audio
	3.	Missing translation keys show fallback (English) or key name (visible)
	4.	Missing audio falls back to shared (or fails gracefully)
	5.	Activity progression still works (runner flow unchanged)

⸻

Minimal sequence that works (if you want the shortest path)

If you want the fewest edits for a first working version:
	1.	Add /js/locale.js
	2.	Add /js/i18n.js and /i18n/nl/ui.json, /i18n/en/ui.json
	3.	Make runner call await loadI18N()
	4.	Move words.json to /data/nl/words.json and /data/en/words.json
	5.	Change runner fetch path to ../data/${lang}/words.json
	6.	Gradually replace hardcoded strings in activities with t(...)

⸻

If you paste your current runner.js (or the part where it fetches words.json and starts the first activity), I will give you an exact patch: the precise code blocks to insert, and the exact new file paths to add to your repo.