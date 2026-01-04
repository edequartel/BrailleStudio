// /js/braille/en.js
(function (global) {
  "use strict";

  global.BrailleRegistry = global.BrailleRegistry || {};

  // Grade-1 / basic (UEB-ish) Unicode braille for letters
  const LETTER = {
    a: "⠁", b: "⠃", c: "⠉", d: "⠙", e: "⠑",
    f: "⠋", g: "⠛", h: "⠓", i: "⠊", j: "⠚",
    k: "⠅", l: "⠇", m: "⠍", n: "⠝", o: "⠕",
    p: "⠏", q: "⠟", r: "⠗", s: "⠎", t: "⠞",
    u: "⠥", v: "⠧", w: "⠺", x: "⠭", y: "⠽", z: "⠵"
  };

  // Digits use a–j after number sign
  const DIGIT = {
    "1": "⠁", "2": "⠃", "3": "⠉", "4": "⠙", "5": "⠑",
    "6": "⠋", "7": "⠛", "8": "⠓", "9": "⠊", "0": "⠚"
  };

  // Basic punctuation (keep it conservative)
  const PUNCT = {
    " ": "⠀",   // braille blank
    ".": "⠲",
    ",": "⠂",
    ";": "⠆",
    ":": "⠒",
    "?": "⠦",
    "!": "⠖",
    "-": "⠤",
    "'": "⠄",
    "\"": "⠶",
    "(": "⠶",   // simple fallback; adjust if you prefer dedicated parens
    ")": "⠶",
    "/": "⠌"
  };

  const CAPITAL = "⠠";
  const NUMBER  = "⠼";
  const UNKNOWN = "⣿"; // visible "unknown" cell

  function isDigit(ch) {
    return ch >= "0" && ch <= "9";
  }

  function textToBrailleCellsEN(text) {
    const raw = String(text ?? "");
    const out = new Array(raw.length);

    let inNumberRun = false;

    for (let i = 0; i < raw.length; i++) {
      const ch = raw[i];

      // digits: prefix number sign at start of a digit run
      if (isDigit(ch)) {
        const cell = DIGIT[ch] || UNKNOWN;
        if (!inNumberRun) {
          out[i] = NUMBER + cell;
          inNumberRun = true;
        } else {
          out[i] = cell;
        }
        continue;
      } else {
        inNumberRun = false;
      }

      // punctuation / space
      if (Object.prototype.hasOwnProperty.call(PUNCT, ch)) {
        out[i] = PUNCT[ch];
        continue;
      }

      // letters (with capital sign if uppercase)
      const lower = ch.toLowerCase();
      if (Object.prototype.hasOwnProperty.call(LETTER, lower)) {
        const cell = LETTER[lower];
        const isUpper = ch !== lower;
        out[i] = isUpper ? (CAPITAL + cell) : cell;
        continue;
      }

      // fallback
      out[i] = UNKNOWN;
    }

    return out;
  }

  global.BrailleRegistry.en = {
    id: "en",
    textToBrailleCells: textToBrailleCellsEN
  };
})(window);