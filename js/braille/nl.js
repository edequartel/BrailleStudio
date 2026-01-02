// /js/braille/nl.js
(function (global) {
  "use strict";

  global.BrailleRegistry = global.BrailleRegistry || {};

  const LETTER = {
    a: "⠁", b: "⠃", c: "⠉", d: "⠙", e: "⠑",
    f: "⠋", g: "⠛", h: "⠓", i: "⠊", j: "⠚",
    k: "⠅", l: "⠇", m: "⠍", n: "⠝", o: "⠕",
    p: "⠏", q: "⠟", r: "⠗", s: "⠎", t: "⠞",
    u: "⠥", v: "⠧", w: "⠺", x: "⠭", y: "⠽", z: "⠵"
  };

  const DIGIT = {
    "1": "⠁", "2": "⠃", "3": "⠉", "4": "⠙", "5": "⠑",
    "6": "⠋", "7": "⠛", "8": "⠓", "9": "⠊", "0": "⠚"
  };

  // Basispunctuatie (conservatief). Als jouw NL brailletabel andere tekens wil,
  // pas alleen deze mapping aan.
  const PUNCT = {
    " ": "⠀",
    ".": "⠲",
    ",": "⠂",
    ";": "⠆",
    ":": "⠒",
    "?": "⠦",
    "!": "⠖",
    "-": "⠤",
    "'": "⠄",
    "\"": "⠶",
    "(": "⠶",
    ")": "⠶",
    "/": "⠌"
  };
  
  
  const SIGN_CAPITAL = "⠨"; // correct NL (dots 4-6)
  const NUMBER  = "⠼";
  const UNKNOWN = "⣿";

  function isDigit(ch) {
    return ch >= "0" && ch <= "9";
  }

  function textToBrailleCellsNL(text) {
    const raw = String(text ?? "");
    const out = new Array(raw.length);

    let inNumberRun = false;

    for (let i = 0; i < raw.length; i++) {
      const ch = raw[i];

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

      if (Object.prototype.hasOwnProperty.call(PUNCT, ch)) {
        out[i] = PUNCT[ch];
        continue;
      }

      const lower = ch.toLowerCase();
      if (Object.prototype.hasOwnProperty.call(LETTER, lower)) {
        const cell = LETTER[lower];
        const isUpper = ch !== lower;
        out[i] = isUpper ? (CAPITAL + cell) : cell;
        continue;
      }

      out[i] = UNKNOWN;
    }

    return out;
  }

  global.BrailleRegistry.nl = {
    id: "nl",
    textToBrailleCells: textToBrailleCellsNL
  };
})(window);