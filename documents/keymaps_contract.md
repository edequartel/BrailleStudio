# Keymaps JSON Contract (Developer)

This document describes the JSON contract for braille device keymaps used by BrailleBridge.

## Location

BrailleBridge looks for keymaps in:

- Preferred: `C:\ProgramData\BrailleBridge\keymaps`
- Fallback (legacy): `<app base dir>\keymaps`

Files must have extension `.json`.

## How matching works

BrailleBridge does **not** select by filename.  
It loads all `.json` files and picks the one with the **longest matching** `identifierPrefixes` entry where:

`deviceIdentifier.StartsWith(prefix, StringComparison.OrdinalIgnoreCase)`

If no prefix matches, no keymap is loaded.

## JSON schema (practical)

```json
{
  "deviceId": "string",
  "description": "string",
  "identifierPrefixes": ["string", "..."],

  "cursorRoutingStrip": 0,
  "thumbKeyStrip": 0,
  "brailleDotStrip": 0,

  "thumbKeys": {
    "rawParam(uint as string, dec or 0xhex)": "string event name"
  },

  "brailleDots": {
    "rawBit(uint as string, dec or 0xhex)": "dot number 1..8"
  },

  "editorKeys": {
    "rawParam(uint as string, dec or 0xhex)": "Backspace|Enter|Space|ArrowLeft|ArrowRight|ArrowUp|ArrowDown"
  }
}
```

## Field details

- `deviceId`  
  Free string, informational.

- `description`  
  Free string, informational.

- `identifierPrefixes`  
  Array of non-empty strings used for runtime matching against the SAM device identifier.

- `cursorRoutingStrip`  
  Integer strip id for cursor routing events (`msgType 9/11` in current mapper path).

- `thumbKeyStrip`  
  Integer strip id used for thumb key and editor key mappings.

- `brailleDotStrip`  
  Integer strip id used for braille chord dot mapping.

- `thumbKeys`  
  Map: `rawParam -> name` (name is free text, used for diagnostics/events).

- `brailleDots`  
  Map: `rawBit -> dotNumber`.
  - `rawBit` keys are parsed as `uint` from decimal (`"1024"`) or hex (`"0x400"`).
  - `dotNumber` must be `1..8`; other values are ignored.

- `editorKeys`  
  Map: `rawParam -> editor command string`.
  Valid command strings (case-insensitive):
  - `Backspace`
  - `Enter`
  - `Space`
  - `ArrowLeft`
  - `ArrowRight`
  - `ArrowUp`
  - `ArrowDown`

## Parsing behavior

- Property names are case-insensitive.
- Unknown properties are ignored.
- Missing map properties (`thumbKeys`, `brailleDots`, `editorKeys`) are treated as empty.
- Invalid numeric map keys are ignored.

## Example

```json
{
  "deviceId": "FOCUS40",
  "description": "Freedom Scientific Focus 40 Blue",
  "identifierPrefixes": ["F40", "FOCUS40", "FOCUS 40", "FS-FOCUS40"],

  "cursorRoutingStrip": 0,
  "thumbKeyStrip": 0,
  "brailleDotStrip": 0,

  "thumbKeys": {
    "1024": "LeftThumb",
    "2097152": "MiddleLeftThumb",
    "8388608": "MiddleRightThumb",
    "2048": "RightThumb"
  },

  "brailleDots": {
    "1": 1,
    "2": 2,
    "4": 3,
    "8": 4,
    "16": 5,
    "32": 6,
    "64": 7,
    "128": 8
  },

  "editorKeys": {
    "2097152": "Backspace",
    "2048": "Enter",
    "4096": "Space",
    "8192": "ArrowUp",
    "16384": "ArrowDown",
    "256": "ArrowLeft",
    "512": "ArrowRight"
  }
}
```
