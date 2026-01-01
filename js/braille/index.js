(function (global) {
  "use strict";

  function getLocale() {
    const saved = localStorage.getItem("bs_locale");
    if (saved) return saved.toLowerCase();
    const htmlLang = (document.documentElement.getAttribute("lang") || "nl").toLowerCase();
    return htmlLang;
  }

  function getMode() {
    return (localStorage.getItem("bs_braille_mode") || "learn").toLowerCase();
  }

  // Plug in language modules (you can expand later)
  const tables = {
    nl: global.BrailleNL
  };

  function textToBrailleAscii(text) {
    const locale = getLocale();
    const mode = getMode();
    const mod = tables[locale] || tables.nl;

    if (!mod || typeof mod.toAscii !== "function") return String(text ?? "");
    return mod.toAscii(String(text ?? ""), { mode, locale });
  }

  global.Braille = {
    getLocale,
    getMode,
    textToBrailleAscii
  };
})(window);