(function (global) {
  "use strict";

  const LETTERS = {
    a:"A", b:"B", c:"C", d:"D", e:"E", f:"F", g:"G", h:"H", i:"I", j:"J",
    k:"K", l:"L", m:"M", n:"N", o:"O", p:"P", q:"Q", r:"R", s:"S", t:"T",
    u:"U", v:"V", w:"W", x:"X", y:"Y", z:"Z"
  };

  const DIGITS = { "1":"A","2":"B","3":"C","4":"D","5":"E","6":"F","7":"G","8":"H","9":"I","0":"J" };

  // Common "computer braille" ASCII markers (many systems use these)
  const CAPITAL = "^";  // represents ⠠
  const NUMBER  = "#";  // represents ⠼

  // Minimal punctuation (extend later)
  const PUNCT = {
    " ": " ",
    ".": "4",
    ",": "1",
    ";": "2",
    ":": "3",
    "?": "8",
    "!": "6",
    "-": "-",
    "'": "'",
    "\"": "7",
    "/": "/",
    "(": "(",
    ")": ")"
  };

  function toAscii(text, { mode } = {}) {
    // mode:
    // - "learn": do NOT add capital/number signs (simple)
    // - "real" : add them
    const real = (mode === "real");

    let out = "";
    let numberMode = false;

    for (let i = 0; i < text.length; i++) {
      const ch = text[i];

      // punctuation / space
      if (PUNCT[ch]) {
        out += PUNCT[ch];
        numberMode = false;
        continue;
      }

      // digits
      if (ch >= "0" && ch <= "9") {
        if (real && !numberMode) out += NUMBER;
        numberMode = true;
        out += DIGITS[ch] || "?";
        continue;
      }

      // letters
      const lower = ch.toLowerCase();
      const isLetter = lower >= "a" && lower <= "z";

      if (isLetter) {
        if (real && ch !== lower) out += CAPITAL; // uppercase
        out += LETTERS[lower] || "?";
        numberMode = false;
        continue;
      }

      // unknown
      out += "?";
      numberMode = false;
    }

    return out;
  }

  global.BrailleNL = { toAscii };
})(window);