# BrailleBridge WebSocket Client Contract

Last updated: 2026-03-06

## 1) Endpoint

- WebSocket URL: `ws://localhost:5000/ws`
- Transport: UTF-8 text frames containing JSON

## 2) Message directions

- Server -> Client:
  - `thumbKey` (device thumb key press)
  - `editorKey` (device editor command key press)
  - `cursor` (cursor-routing context)
  - `chord` (braille chord context)
  - `brailleLine` (current display line/state)
  - optional raw key event (only when UI checkbox **sent raw keys** is enabled)
- Client -> Server:
  - command messages (`setEditorMode`, `editorInput`)

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
- `cursor` and `chord` currently keep their historical shape without `Payload` (see sections 3.3 and 3.4).
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
    "CellChar": "⠨",
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
- Context represents typed braille chord information

```json
{
  "Type": "chord",
  "Ok": true,
  "TimestampUtc": "2026-03-06T08:06:02.1026420Z",
  "SourceText": "",
  "Table": "nl-NL-g0.utb",
  "Cursor": {
    "CellIndex": -1,
    "TextIndex": -1,
    "Character": "a",
    "CharacterCodePoint": "U+0061",
    "Word": ""
  },
  "Braille": {
    "CellChar": "⠁",
    "CellCodePoint": "U+2801",
    "IsCapitalSign": false,
    "IsNumberSign": false,
    "IsCapitalWordSign": false,
    "CapitalSignActive": false,
    "CapitalWordSignActive": false,
    "NumberSignActive": false
  },
  "Sam": {
    "MsgType": 8,
    "UnitId": 1,
    "Strip": 0,
    "Param": 1
  }
}
```

## 3.6 `brailleLine` event

This event currently uses camelCase keys.

```json
{
  "type": "brailleLine",
  "ok": true,
  "sourceText": "Hello braille",
  "braille": {
    "unicodeText": "⠨⠓⠑⠇⠇⠕ ⠃⠗⠁⠊⠇⠇⠑"
  },
  "meta": {
    "activeTable": "nl-NL-g0.utb",
    "charSize": 0,
    "lineId": 123,
    "createdUtc": "2026-03-06T08:26:01.0000000Z",
    "lineLength": 14,
    "caretPosition": 5
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
  meta: {
    activeTable: string;
    charSize: number;
    lineId: number;
    createdUtc: string;
    lineLength: number;
    caretPosition?: number;
  };
};
```

## 3.7 Optional raw key event (debug/diagnostics)

Only sent when the BrailleBridge UI checkbox **sent raw keys** is enabled.

```json
{
  "MsgType": 8,
  "UnitId": 1,
  "Strip": 0,
  "ButtonIndex": 15,
  "RawParam": 15,
  "IsPress": true,
  "Kind": 3,
  "CursorIndex": 0,
  "Name": null,
  "DotsMask": 15,
  "UnicodeCell": "⠏",
  "EditorCommand": null
}
```

## 4) Client -> Server commands

All commands use lowercase `type: "command"` and lowercase `command`.

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

Text:
```json
{
  "type": "command",
  "command": "editorInput",
  "input": {
    "kind": "text",
    "text": "hello"
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
    "unicode": "⠁"
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

Valid `key` values:
- `Backspace`
- `Space`
- `Enter`
- `ArrowLeft`
- `ArrowRight`
- `ArrowUp`
- `ArrowDown`

## 5) Practical client parsing recommendation

Because the current wire format is mixed-case (`Type` vs `type`), parse by checking both:

- `const eventType = msg.Type ?? msg.type;`

Then branch:
- `thumbKey`
- `editorKey`
- `cursor`
- `chord`
- `brailleLine`

## 6) Stability notes

- `keyActionContext` is not sent anymore.
- Raw key events are opt-in (`sent raw keys` checkbox).
- `thumbKey` is now envelope-style to align with context events.
