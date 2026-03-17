# BrailleBridge WebSocket Client Contract

Version: 2.1

Last updated: 2026-03-16

## 1) Endpoint

- WebSocket URL: `ws://localhost:5000/ws`
- Transport: UTF-8 text frames containing JSON

## 2) Message directions

- Server -> Client:
  - `thumbKey` (device thumb key press)
  - `editorKey` (device editor command key press)
  - `cursor` (cursor-routing context)
  - `chord` (braille chord context)
  - `brailleLine` (current display line/state, including authoritative caret and 40-cell display projection)
  - optional raw key event (only when UI checkbox **sent raw keys** is enabled)
- Client -> Server:
  - command messages (`setEditorMode`, `setEditorInsertMode`, `editorInput`)
  - caret commands (`setCaret`, `moveCaret`, `setCaretFromCell`, `setCaretVisibility`, `setCaretStyle`, `setCaretToEnd`, `setCaretToBegin`)
  - query command (`getBrailleLine`)
  - cursor-routing simulation command (`cursorRouting`)

## 3) Server -> Client event contracts

## 3.1 Envelope-style events (Thumb/Cursor/Chord)

These use a shared top-level structure:

```json
{
  "Type": "thumbKey | cursor | chord",
  "Ok": true,
  "TimestampUtc": "2026-03-06T08:25:35.0180473Z",
  "Sam": {
    "MsgType": 9,
    "UnitId": 1,
    "Strip": 0,
    "Param": 0
  },
  "Payload": {}
}
```

Notes:
- `cursor` and `chord` currently keep their historical shape without `Payload` (see sections 3.3 and 3.5).
- `thumbKey` uses `Payload` (see 3.2).

### Shared `Sam`

```ts
type Sam = {
  MsgType: number;
  UnitId: number;
  Strip: number;
  Param: number;
};
```

## 3.2 `thumbKey` event

```json
{
  "Type": "thumbKey",
  "Ok": true,
  "TimestampUtc": "2026-03-06T08:25:01.0000000Z",
  "Payload": {
    "Name": "LeftThumb",
    "Press": true
  },
  "Sam": {
    "MsgType": 11,
    "UnitId": 1,
    "Strip": 0,
    "Param": 0
  }
}
```

```ts
type ThumbKeyEvent = {
  Type: "thumbKey";
  Ok: boolean;
  TimestampUtc: string;
  Payload: {
    Name: string;
    Press: boolean;
  };
  Sam: Sam;
};
```

## 3.3 `cursor` event

```json
{
  "Type": "cursor",
  "Ok": true,
  "TimestampUtc": "2026-03-06T08:25:35.0180473Z",
  "SourceText": "Hello braill",
  "Table": "nl-NL-g0.utb",
  "Cursor": {
    "CellIndex": 0,
    "TextIndex": 0,
    "Character": "H",
    "CharacterCodePoint": "U+0048",
    "Word": "Hello"
  },
  "Braille": {
    "CellChar": "\u2828",
    "CellCodePoint": "U+2828",
    "IsCapitalSign": true,
    "IsNumberSign": false,
    "IsCapitalWordSign": false,
    "CapitalSignActive": false,
    "CapitalWordSignActive": false,
    "NumberSignActive": false
  },
  "Sam": {
    "MsgType": 9,
    "UnitId": 1,
    "Strip": 0,
    "Param": 0
  }
}
```

```ts
type CursorContextEvent = {
  Type: "cursor";
  Ok: boolean;
  TimestampUtc: string;
  SourceText: string;
  Table: string;
  Cursor: {
    CellIndex: number;
    TextIndex: number;
    Character: string;
    CharacterCodePoint: string;
    Word: string;
  };
  Braille: {
    CellChar: string;
    CellCodePoint: string;
    IsCapitalSign: boolean;
    IsNumberSign: boolean;
    IsCapitalWordSign: boolean;
    CapitalSignActive: boolean;
    CapitalWordSignActive: boolean;
    NumberSignActive: boolean;
  };
  Sam: Sam;
};
```

## 3.4 `editorKey` event

```json
{
  "Type": "editorKey",
  "Ok": true,
  "TimestampUtc": "2026-03-06T09:25:01.0000000Z",
  "Payload": {
    "Key": "Backspace",
    "Press": true
  },
  "Sam": {
    "MsgType": 8,
    "UnitId": 1,
    "Strip": 0,
    "Param": 2097152
  }
}
```

```ts
type EditorKeyEvent = {
  Type: "editorKey";
  Ok: boolean;
  TimestampUtc: string;
  Payload: {
    Key: "Backspace" | "Space" | "Enter" | "ArrowLeft" | "ArrowRight" | "ArrowUp" | "ArrowDown";
    Press: boolean;
  };
  Sam: Sam;
};
```

## 3.5 `chord` event

Shape is the same as `cursor`, but:
- `Type` is `"chord"`
- context represents typed braille chord information

## 3.6 `brailleLine` event

This event uses camelCase keys.

Example log line:

```text
15:48:59  WS OUT  {"type":"brailleLine","ok":true,"sourceText":"Hello braille!                          ","braille":{"unicodeText":"\u2828\u2813\u2811\u2807\u2807\u2815\u2800\u2803\u2817\u2801\u280A\u2807\u2807\u2811\u2816\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800"},"caret":{"textIndex":8,"cellIndex":9},"caretVisible":true,"caretStyle":{"dots":[7,8],"blink":false,"blinkPeriodMs":500},"meta":{"activeTable":"nl-NL-g0.utb","charSize":4,"lineId":502,"createdUtc":"2026-03-13T14:48:59.5300729Z","lineLength":15,"brailleDisplayCells":40,"sscoTextLength":14,"sscoBrailleLength":15,"caretTextPosition":8,"caretCellPosition":9}}
```

```json
{
  "type": "brailleLine",
  "ok": true,
  "sourceText": "Hello braille                           ",
  "braille": {
    "unicodeText": "\u2828\u2813\u2811\u2807\u2807\u2815\u2800\u2803\u2817\u2801\u280a\u2807\u2807\u2811\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800\u2800"
  },
  "caret": {
    "textIndex": 12,
    "cellIndex": 13
  },
  "caretVisible": true,
  "caretStyle": {
    "dots": [7, 8],
    "blink": false,
    "blinkPeriodMs": 500
  },
  "meta": {
    "activeTable": "nl-NL-g0.utb",
    "charSize": 0,
    "lineId": 123,
    "createdUtc": "2026-03-12T08:26:01.0000000Z",
    "lineLength": 14,
    "brailleDisplayCells": 40,
    "sscoTextLength": 12,
    "sscoBrailleLength": 14,
    "caretTextPosition": 12,
    "caretCellPosition": 13
  }
}
```

```ts
type BrailleLineEvent = {
  type: "brailleLine";
  ok: boolean;
  sourceText?: string;
  braille: {
    unicodeText: string;
  };
  caret: {
    textIndex: number;
    cellIndex?: number;
  };
  caretVisible: boolean;
  caretStyle: {
    dots: number[];
    blink: boolean;
    blinkPeriodMs: number;
  };
  meta: {
    activeTable: string;
    charSize: number;
    lineId: number;
    createdUtc: string;
    lineLength: number;
    brailleDisplayCells: 40;
    sscoTextLength: number;
    sscoBrailleLength: number;
    caretTextPosition?: number;
    caretCellPosition?: number;
  };
};
```

Caret notes:
- `caret.textIndex` is canonical and authoritative.
- `caret.cellIndex` reflects display cell-space insertion position (0..40).
- `meta.caretTextPosition` reflects text-space caret.
- `meta.caretCellPosition` reflects cell-space caret.
- `meta.caretTextPosition` and `meta.caretCellPosition` can differ and should both be processed by clients.
- `caretVisible: true` means caret is on/visible; `caretVisible: false` means caret is off/hidden.
- `sourceText` and `braille.unicodeText` are sent as fixed-width 40-cell projections (padded/truncated).
- precharacters/signs (for example capital sign/number sign) are part of braille representation and are not separate text-character steps for logical caret movement.

## 3.7 Optional raw key event (debug/diagnostics)

Only sent when the BrailleBridge UI checkbox **sent raw keys** is enabled.

## 4) Client -> Server commands

Commands can be sent in either form:
- envelope form: `{"type":"command","command":"..."}`
- direct form: `{"type":"setCaret", ...}`

## 4.1 Set editor mode

```json
{
  "type": "command",
  "command": "setEditorMode",
  "enabled": true
}
```

## 4.2 Editor input

`kind` can be: `text`, `braille`, `key`

Behavior:
- `editorInput` is accepted in both editor mode and non-editor mode.
- `setEditorInsertMode: true` -> `text` is inserted at current caret position.
- `setEditorInsertMode: false` -> `text` overwrites from current caret position.
- edit position is derived from canonical SSoC text caret position (`caret.textIndex` / `meta.caretTextPosition`).
- `text` with `replace: true` (or `"true"`) replaces current SSoC text.
- `braille` is back-translated and appended to current SSoC text.

Text:
```json
{
  "type": "command",
  "command": "editorInput",
  "input": {
    "kind": "text",
    "text": "hello",
    "replace": true
  }
}
```

Braille:
```json
{
  "type": "command",
  "command": "editorInput",
  "input": {
    "kind": "braille",
    "unicode": "\u2801"
  }
}
```

Key:
```json
{
  "type": "command",
  "command": "editorInput",
  "input": {
    "kind": "key",
    "key": "Backspace"
  }
}
```

Key behavior:
- `ArrowLeft` / `ArrowRight` / `ArrowUp` / `ArrowDown`: caret navigation in both editor mode and non-editor mode.
- `Backspace` / `Space` / `Enter`: SSoC mutation, editor mode only.

Valid `key` values:
- `Backspace`
- `Space`
- `Enter`
- `ArrowLeft`
- `ArrowRight`
- `ArrowUp`
- `ArrowDown`

## 4.2b setEditorInsertMode (editor text insert at caret)

Direct:
```json
{
  "type": "setEditorInsertMode",
  "enabled": true
}
```

Direct (OFF):
```json
{
  "type": "setEditorInsertMode",
  "enabled": false
}
```

Envelope:
```json
{
  "type": "command",
  "command": "setEditorInsertMode",
  "enabled": true
}
```

Envelope (OFF):
```json
{
  "type": "command",
  "command": "setEditorInsertMode",
  "enabled": false
}
```

Behavior:
- controls handling of `editorInput` with `kind: "text"` in both editor mode and non-editor mode.
- `enabled: true`: insert text at caret position.
- `enabled: false`: overwrite text from caret position.

## 4.3 setCaret (absolute text index)

Direct:
```json
{
  "type": "setCaret",
  "textIndex": 12
}
```

Envelope:
```json
{
  "type": "command",
  "command": "setCaret",
  "textIndex": 12
}
```

Behavior:
- `textIndex` is clamped to `[0..sourceText.length]`.
- server updates canonical caret.
- server derives cell index from latest mapping.
- server broadcasts a fresh authoritative `brailleLine`.
- works in both editor mode and non-editor mode.

## 4.4 moveCaret (relative)

Direct:
```json
{
  "type": "moveCaret",
  "by": -1,
  "unit": "character"
}
```

Envelope:
```json
{
  "type": "command",
  "command": "moveCaret",
  "by": 1,
  "unit": "character"
}
```

Behavior:
- supported units: `character`, `cell`.
- `unit: "character"`:
  - movement is relative to canonical `caret.textIndex`.
  - result is clamped to `[0..sourceText.length]`.
  - sign/precharacter aware: braille precharacters (capital sign/number sign) are treated as modifiers, not standalone text-character steps.
- `unit: "cell"`:
  - movement is relative to display `caret.cellIndex`.
  - result is clamped to `[0..40]`.
  - `40` means caret at end-of-display insertion position (after the 40th cell).
- server broadcasts a fresh authoritative `brailleLine`.
- works in both editor mode and non-editor mode.

## 4.5 setCaretFromCell (optional, implemented)

Direct:
```json
{
  "type": "setCaretFromCell",
  "cellIndex": 8
}
```

Envelope:
```json
{
  "type": "command",
  "command": "setCaretFromCell",
  "cellIndex": 8
}
```

Behavior:
- `cellIndex` is clamped to `[0..40]`.
- `cellIndex: 40` places caret at display end insertion position.
- server maps cell -> canonical text index using latest mapping.
- server recomputes derived cell index and broadcasts `brailleLine`.
- works in both editor mode and non-editor mode.

## 4.6 setCaretVisibility

Direct:
```json
{
  "type": "setCaretVisibility",
  "visible": true
}
```

Envelope:
```json
{
  "type": "command",
  "command": "setCaretVisibility",
  "visible": true
}
```

Behavior:
- controls caret visibility independently from editor mode.
- when `visible` is true, caret is rendered at its cell position even on empty cells.

## 4.7 setCaretStyle

Direct:
```json
{
  "type": "setCaretStyle",
  "dots": [7, 8],
  "blink": true,
  "blinkPeriodMs": 500
}
```

Envelope:
```json
{
  "type": "command",
  "command": "setCaretStyle",
  "dots": [7, 8],
  "blink": true,
  "blinkPeriodMs": 500
}
```

Behavior:
- `dots`: braille dots used for caret overlay indicator.
- `blink`: enable/disable caret blinking.
- `blinkPeriodMs`: blink interval in milliseconds.

Validation and resilience:
- malformed caret messages are ignored safely (no process crash).
- unknown caret commands are ignored safely.

## 4.8 getBrailleLine (fetch current physical braille display line)

Direct:
```json
{
  "type": "getBrailleLine"
}
```

Envelope:
```json
{
  "type": "command",
  "command": "getBrailleLine"
}
```

Behavior:
- returns one authoritative `brailleLine` event for the current display state.
- does not mutate content, caret, or editor mode.

Typical log sequence:

```text
16:02:32  WS IN   {"type":"getBrailleLine"}
16:02:32  WS OUT  {"type":"brailleLine", ...}
```

Note:
- `WS IN {"type":"getBrailleLine"}` is the client request.
- the server response is `WS OUT {"type":"brailleLine", ...}`.

## 4.9 setCaretToEnd (End-key style caret placement)

Direct:
```json
{
  "type": "setCaretToEnd"
}
```

Envelope:
```json
{
  "type": "command",
  "command": "setCaretToEnd"
}
```

Behavior:
- places caret at the logical end of current SSoC content.
- canonical target is `caret.textIndex = meta.sscoTextLength`.
- display cell is derived from latest text<->braille mapping for that text end (sign/precharacter aware, including extra number/capital sign cells), using insertion-position semantics (`0..40`).
- this command is independent from editor mode.
- server broadcasts a fresh authoritative `brailleLine`.

## 4.10 setCaretToBegin (Home-key style caret placement)

Direct:
```json
{
  "type": "setCaretToBegin"
}
```

Envelope:
```json
{
  "type": "command",
  "command": "setCaretToBegin"
}
```

Behavior:
- places caret at the logical begin of current SSoC content.
- canonical target is `caret.textIndex = 0`.
- display cell is derived from latest text<->braille mapping.
- server broadcasts a fresh authoritative `brailleLine`.

## 4.11 Postman WebSocket examples (copy/paste)

In Postman:
1. Open a WebSocket request to `ws://localhost:5000/ws`
2. Click **Connect**
3. Send one JSON message per frame (examples below)

Enable editor mode:
```json
{"type":"command","command":"setEditorMode","enabled":true}
```

Enable editor insert mode (text inserts at caret):
```json
{"type":"command","command":"setEditorInsertMode","enabled":true}
```

Disable editor insert mode (text overwrite behavior):
```json
{"type":"command","command":"setEditorInsertMode","enabled":false}
```

Replace line content:
```json
{"type":"command","command":"editorInput","input":{"kind":"text","text":"Hello braille","replace":true}}
```

Append text:
```json
{"type":"command","command":"editorInput","input":{"kind":"text","text":" world"}}
```

Insert braille cell input:
```json
{"type":"command","command":"editorInput","input":{"kind":"braille","unicode":"\u2801"}}
```

Send editor key:
```json
{"type":"command","command":"editorInput","input":{"kind":"key","key":"Backspace"}}
```

Set caret by canonical text index:
```json
{"type":"setCaret","textIndex":5}
```

Move caret left by one character:
```json
{"type":"moveCaret","by":-1,"unit":"character"}
```

Move caret right by one character:
```json
{"type":"moveCaret","by":1,"unit":"character"}
```

Move caret right by one display cell:
```json
{"type":"moveCaret","by":1,"unit":"cell"}
```

Set caret to display end (cell 40):
```json
{"type":"setCaretFromCell","cellIndex":40}
```

Set caret from braille cell index:
```json
{"type":"setCaretFromCell","cellIndex":3}
```

Simulate cursor-routing key press on cell 3 (same behavior as hardware cursor-routing press):
```json
{"type":"cursorRouting","cellIndex":3}
```

Show caret:
```json
{"type":"setCaretVisibility","visible":true}
```

Set caret style:
```json
{"type":"setCaretStyle","dots":[7,8],"blink":true,"blinkPeriodMs":500}
```

Move caret to end (End-key style):
```json
{"type":"setCaretToEnd"}
```

Move caret to begin (Home-key style):
```json
{"type":"setCaretToBegin"}
```

Fetch current authoritative braille line (without changing state):
```json
{"type":"getBrailleLine"}
```

Equivalent caret command using command-envelope form:
```json
{"type":"command","command":"setCaret","textIndex":5}
```

Equivalent getBrailleLine using command-envelope form:
```json
{"type":"command","command":"getBrailleLine"}
```

Equivalent setCaretToEnd using command-envelope form:
```json
{"type":"command","command":"setCaretToEnd"}
```

Equivalent setEditorInsertMode using command-envelope form:
```json
{"type":"command","command":"setEditorInsertMode","enabled":true}
```

Equivalent setCaretToBegin using command-envelope form:
```json
{"type":"command","command":"setCaretToBegin"}
```

After each valid content/caret/query command, expect an authoritative `brailleLine` event from server including:
- `sourceText`
- `braille.unicodeText`
- `caret.textIndex`
- `caret.cellIndex`

## 5) Practical client parsing recommendation

Because current wire format is mixed-case (`Type` vs `type`), parse by checking both:

- `const eventType = msg.Type ?? msg.type;`

Then branch:
- `thumbKey`
- `editorKey`
- `cursor`
- `chord`
- `brailleLine`

## 6) Stability notes

- `keyActionContext` is not sent anymore.
- raw key events are opt-in (`sent raw keys` checkbox).
- `thumbKey` is envelope-style to align with context events.
- `brailleLine` uses a fixed 40-cell display projection for text and braille strings.
