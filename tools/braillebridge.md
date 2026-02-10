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
  "type": "brailleLine",
  "ok": true,
  "sourceText": "aap Aap AAP 123",
  "braille": {
    "unicodeText": "\u2801\u2801\u280f\u2800\u2820\u2801\u2801\u280f\u2800\u2820\u2820\u2801\u2801\u280f\u2800\u283c\u2801\u2803\u2809"
  },
  "meta": {
    "activeTable": "ko-g1.ctb",
    "charSize": 4,
    "lineId": 5,
    "createdUtc": "2026-02-10T20:04:18.8733379Z",
    "lineLength": 19
  }
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
  "type": "brailleLine",
  "ok": true,
  "sourceText": "aap Aap AAP 123",
  "braille": {
    "unicodeText": "\u2801\u2801\u280f\u2800\u2820\u2801\u2801\u280f\u2800\u2820\u2820\u2801\u2801\u280f\u2800\u283c\u2801\u2803\u2809"
  },
  "meta": {
    "activeTable": "ko-g1.ctb",
    "charSize": 4,
    "lineId": 5,
    "createdUtc": "2026-02-10T20:04:18.8733379Z",
    "lineLength": 19
  }
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

# Observe updates via WebSocket
```



## Mental Model

* `/braille` → **replace SSoC**
* WebSocket → **observe & control**
* BrailleBridge → **single source of truth**

```


## run local
Start in folder BrailleBridge
cd ../braillebridge

python3 -m http.server 8000

in browser open 
http://localhost:8000







