# BrailleBridge API & WebSocket Reference

This document describes **all HTTP API endpoints and WebSocket messages**
exposed by `BrailleWebSocketBridge` (default port **5000**).

BrailleBridge is the **Single Source of Content (SSoC)** for braille output.
All braille rendering and cursor mapping originate here.



## Base URLs

- HTTP: `http://localhost:5000`
- WebSocket: `ws://localhost:5000/ws`



## General Notes

- CORS enabled (`Access-Control-Allow-Origin: *`)
- Supports `OPTIONS` preflight
- Unknown routes return `404`
- All JSON is UTF-8



# HTTP API



## Editor Mode

### POST `/editor/enable`

Enable editor mode.

```bash
curl -X POST http://localhost:5000/editor/enable
````

Response:

```json
{
  "ok": true,
  "editorModeEnabled": true
}
```



### POST `/editor/disable`

Disable editor mode.

```bash
curl -X POST http://localhost:5000/editor/disable
```

Response:

```json
{
  "ok": true,
  "editorModeEnabled": false
}
```



### GET `/editor/status`

Get editor mode status.

```bash
curl http://localhost:5000/editor/status
```

Response:

```json
{
  "ok": true,
  "editorModeEnabled": true
}
```



## Editor Input (Incremental SSoC Editing)

### POST `/editor/input`

Apply **one editor action** (text, braille cell, or key).

Editor mode should be enabled for effect.



### `kind: "text"`

Insert normal text (Liblouis translation applies).

```bash
curl -X POST http://localhost:5000/editor/input \
  -H "Content-Type: application/json; charset=utf-8" \
  --data '{"kind":"text","text":"aap"}'
```



### `kind: "braille"`

Insert an **exact Unicode braille cell** (no translation).

```bash
curl -X POST http://localhost:5000/editor/input \
  -H "Content-Type: application/json; charset=utf-8" \
  --data '{"kind":"braille","unicode":"⠃"}'
```



### `kind: "key"`

Logical editor key.

Supported keys:

* `Backspace`
* `Enter`
* `Space`

```bash
curl -X POST http://localhost:5000/editor/input \
  -H "Content-Type: application/json; charset=utf-8" \
  --data '{"kind":"key","key":"Backspace"}'
```



### Command envelope (optional)

```json
{
  "type": "command",
  "command": "editorInput",
  "input": {
    "kind": "text",
    "text": "a"
  }
}
```



### Response

```json
{ "ok": true }
```

> The updated `brailleLine` is **broadcast via WebSocket**, not returned in HTTP.



## Braille Table (Liblouis)

### POST `/brailletable`

Set the active Liblouis table.

#### JSON body

```bash
curl -X POST http://localhost:5000/brailletable \
  -H "Content-Type: application/json; charset=utf-8" \
  --data '{"brailleTable":"nl-NL-g0.utb"}'
```

#### Plain text body

```bash
curl -X POST http://localhost:5000/brailletable \
  -H "Content-Type: text/plain; charset=utf-8" \
  --data 'nl-NL-g0.utb'
```

Response:

```json
{
  "ok": true,
  "brailleTable": "nl-NL-g0.utb"
}
```



## List Available Tables

### GET `/tables`

```bash
curl http://localhost:5000/tables
```

Response:

```json
{
  "ok": true,
  "count": 123,
  "tables": [ ... ]
}
```



## Show / Replace Content (SSoC Render)

### POST `/braille`

Replace the entire SSoC with new text and render it.

* Body: **plain text**
* No JSON parsing
* Returns full `brailleLine`

```bash
curl -X POST http://localhost:5000/braille \
  -H "Content-Type: text/plain; charset=utf-8" \
  --data "aap Aap AAP 123"
```

Response:

```json
{
  "ok": true,
  "unicodeText": "⠁⠁⠏ ..."
}
```



## Utility Endpoints

### GET `/clear`

Clear current content.

```bash
curl http://localhost:5000/clear
```

Response:

```
/clear received
```



### GET `/ping`

Health check.

```bash
curl http://localhost:5000/ping
```

Response:

```
/ping received
```



## Devices

### GET `/devices`

List detected braille units (SAM).

```bash
curl http://localhost:5000/devices
```

Example response:

```json
[
  "1: Freedom Scientific Focus (40 cell)",
  "2: Freedom Scientific Focus (40 cell)",
  "3: Virtual on-screen display"
]
```



# WebSocket API

## Endpoint

```
ws://localhost:5000/ws
```



## Incoming (Client → BrailleBridge)

### Set editor mode

```json
{
  "type": "command",
  "command": "setEditorMode",
  "enabled": true
}
```



### Editor input

```json
{
  "type": "command",
  "command": "editorInput",
  "input": {
    "kind": "text",
    "text": "a"
  }
}
```



### Legacy plain text

If the message is **not JSON**, it is treated as text-to-display
(legacy behavior).



## Outgoing (BrailleBridge → Client)

### brailleLine

Broadcast when SSoC changes.

```json
{
  "unicodeText": "⠃⠗⠁⠊⠇⠇⠑",
  "cells": [...],
  "mapping": {...}
}
```



### BrailleKeyEventMessage

Broadcast on every device key event.

```json
{
  "unitId": 1,
  "kind": "BrailleChord",
  "dotsMask": 3,
  "unicodeCell": "⠃"
}
```



### CursorContextMessage

Broadcast on cursor routing press.

```json
{
  "cursor": {
    "cellIndex": 5,
    "textIndex": 2
  },
  "braille": {
    "cellChar": "⠇"
  }
}
```



### Legacy thumbkey message

```json
{
  "kind": "Thumbkey",
  "name": "Left",
  "press": true
}
```



## Typical Test Flow

```bash
# Show text
POST /braille

# Enable editor
POST /editor/enable

# Type
POST /editor/input (kind:text)

# Backspace
POST /editor/input (kind:key)

# Observe updates via WebSocket
```



## Mental Model

* `/braille` → **replace SSoC**
* `/editor/input` → **edit SSoC**
* WebSocket → **observe & control**
* BrailleBridge → **single source of truth**

```


## run local
Start in folder BrailleBridge
cd ../braillebridge

python3 -m http.server 8000

in browser open 
http://localhost:8000



