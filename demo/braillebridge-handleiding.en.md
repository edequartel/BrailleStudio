# BrailleBridge technical manual

This page explains how the BrailleBridge demo communicates with BrailleBridge, SAM, and the braille display. The demo is a browser client. BrailleBridge runs locally on the same computer and acts as the bridge to the local runtime, SAM, and the connected braille device.

## Overview

Communication consists of three layers:

1. The browser opens a WebSocket to `ws://localhost:5000/ws`.
2. The browser can optionally use HTTP API endpoints on `http://localhost:5000` to request runtime, device, and table information.
3. BrailleBridge sends SSOC information back as JSON, mainly through the `brailleLine` event.

The core flow is:

```text
Browser demo
  -> WebSocket JSON command
  -> BrailleBridge localhost runtime
  -> SAM
  -> braille display
  -> SAM event
  -> BrailleBridge JSON event
  -> Browser demo
```

## Why localhost

BrailleBridge is a local runtime. The browser page runs in a normal browser, but it talks to software on the same machine through `localhost`. This means the braille display does not have to be accessed directly by the website. The website only knows the local WebSocket and HTTP contract; driver, SAM, and hardware details stay inside BrailleBridge.

Default addresses:

- WebSocket: `ws://localhost:5000/ws`
- HTTP API: `http://localhost:5000`
- Launch link: `braillebridge://`

The launch link opens the local BrailleBridge app through the protocol registered on the computer. After that, the browser checks again whether the WebSocket is reachable.

## WebSocket connection

The WebSocket is the live two-way communication channel. The demo opens this connection automatically when the page loads. There is no separate `Connect` or `Disconnect` button: the status card monitors BrailleBridge availability and the demo socket retries when the connection drops.

Internally, the browser connects with:

```js
const ws = new WebSocket("ws://localhost:5000/ws");
```

When the socket is open, the browser can send JSON messages. Each message is sent as a UTF-8 text frame. The demo does not send binary braille data; it sends ordinary JSON objects. If the socket is not open yet, the command is not sent and this is shown in the log.

Example:

```json
{
  "type": "command",
  "command": "editorInput",
  "input": {
    "kind": "text",
    "text": "BrailleStudio demo"
  }
}
```

BrailleBridge translates this message to the local runtime. Depending on the command, it sets text, forwards a key, moves the caret, or changes editor status.

## Outgoing WebSocket commands

The demo mainly uses these client-to-server messages:

- `getBrailleLine`: request the current braille line and SSOC state.
- `setEditorMode`: enable or disable editor mode.
- `setEditorInsertMode`: enable or disable insert mode.
- `editorInput`: send text or an editor key.
- `setCaret`: place the cursor at a text position.
- `setCaretFromCell`: place the cursor based on a braille cell position.
- `moveCaret`: move the cursor relatively.
- `setCaretToBegin`: place the cursor at the beginning.
- `setCaretToEnd`: place the cursor at the end.
- `setCaretVisibility`: show or hide the cursor on the display.
- `cursorRouting`: simulate or process cursor routing from a braille cell.

Text input:

```json
{
  "type": "command",
  "command": "editorInput",
  "input": {
    "kind": "text",
    "text": "demo"
  }
}
```

Key input:

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

Caret positioning can use text indexes or cell indexes:

```json
{
  "type": "setCaret",
  "textIndex": 4
}
```

```json
{
  "type": "setCaretFromCell",
  "cellIndex": 6
}
```

The distinction matters. A text index points to a position in the source string. A cell index points to a position on the braille display. They are not always equal, because capital signs, number signs, and other prefix signs can consume extra braille cells.

## Incoming WebSocket events

BrailleBridge sends events back when status or input changes. The demo expects, among others:

- `brailleLine`: current text, braille output, caret, status, and metadata.
- `cursor`: cursor routing context.
- `chord`: braille chord context.
- `thumbKey`: thumb key from the display.
- `editorKey`: editor key from the display.
- `status`: runtime or editor status.

A simplified `brailleLine` event:

```json
{
  "type": "brailleLine",
  "ok": true,
  "sourceText": "BrailleStudio demo",
  "braille": {
    "unicodeText": "⠨⠃⠗⠁⠊⠇⠇⠑⠨⠎⠞⠥⠙⠊⠕ ⠙⠑⠍⠕"
  },
  "caret": {
    "textIndex": 16,
    "cellIndex": 18
  },
  "caretVisible": true,
  "status": {
    "editorMode": "on",
    "insertMode": "off"
  },
  "meta": {
    "activeTable": "nl-NL-g0.utb",
    "brailleDisplayCells": 40,
    "caretTextPosition": 16,
    "caretCellPosition": 18
  }
}
```

The demo uses this event for three things:

1. Updating the live braille monitor.
2. Updating the visible status badges.
3. Showing the full JSON payload in the event panel.

## SSOC

In this demo, SSOC is the current state that lets the browser and BrailleBridge understand the same line: source text, braille output, cursor position, display length, table, and status flags. In practice, this state mainly arrives through `brailleLine`.

SSOC is required because a browser cannot reliably infer how text is laid out on a physical braille display. Text-to-braille conversion depends on the active table. Some characters take more than one braille cell. Capital, number, and context signs can also affect the mapping between text and cells.

For that reason, the browser treats `brailleLine` as authoritative. The browser can send a command, but then waits for BrailleBridge to confirm the real line, real braille cells, and real caret positions.

Important fields:

- `sourceText`: text BrailleBridge uses for the line.
- `braille.unicodeText`: braille cells as Unicode braille characters.
- `caret.textIndex`: caret position in text.
- `caret.cellIndex`: caret position in braille cells.
- `status.editorMode`: whether editor commands are processed.
- `status.insertMode`: whether insert mode is active.
- `meta.activeTable`: active braille table.
- `meta.brailleDisplayCells`: number of cells on the display.

## HTTP API

Besides WebSocket, the browser can use HTTP for status and configuration requests. This is not a replacement for WebSocket. HTTP is useful for snapshots; WebSocket is needed for live events.

The status component uses API calls such as:

- `GET /devices/active`
- `GET /brailledisplay/status`

The tables tool uses:

- `GET /tables`

These calls use `http://localhost:5000` or `http://127.0.0.1:5000`. The demo uses the API to determine whether the runtime is reachable, which display is active, and whether SAM and the device return usable status information.

A typical status check works like this:

1. Derive the HTTP base from `ws://localhost:5000/ws`: `http://localhost:5000`.
2. Request device status through an HTTP endpoint.
3. Open or reopen the WebSocket.
4. Show "ready" only when runtime, WebSocket, and display are available together.

## Role of SAM

SAM sits between BrailleBridge and the braille display. The browser does not talk directly to SAM. Incoming events may include a `Sam` object with fields such as:

- `MsgType`
- `UnitId`
- `Strip`
- `Param`

These values are useful for debugging. They show which SAM message an event was built from. In the demo they are mainly visible in the JSON event panel, so developers can inspect which physical action or runtime action caused an event.

## Editor mode and insert mode

Editor mode controls whether BrailleBridge treats input as editor interaction. If editor mode is off, the browser can still be connected, but text and caret commands are not processed in the same way.

Insert mode controls how new text is placed in the existing line. The demo shows both states separately, because a WebSocket connection alone does not say enough about edit state.

## Cursor routing

Cursor routing starts on the braille display. A user presses a routing key at a braille cell. BrailleBridge translates that cell position into context: which text character, which word, which braille cell, and which table belong to it.

Because text index and cell index can differ, cursor routing always depends on the latest SSOC mapping. For example, a capital letter can produce an extra braille cell. The cell the user presses does not necessarily have the same index as the text character.

The demo can also simulate routing:

```json
{
  "type": "cursorRouting",
  "cellIndex": 3
}
```

After that, the browser expects a `cursor` or `brailleLine` event that shows the real context.

## Error handling

There are three common error scenarios:

- BrailleBridge is not running: HTTP and WebSocket on port 5000 are unreachable.
- BrailleBridge is running, but the WebSocket is closed: status may be partially available, but live events are missing.
- WebSocket works, but there is no display or SAM status: the browser may receive runtime events, but no reliable hardware status.

The demo logs each step:

- `WS connect`: the browser tries to connect automatically.
- `WS open`: the socket is open.
- `WS -> ...`: the browser sends a command.
- `WS <- JSON`: BrailleBridge sends an event back.
- `WS close` or `WS error`: the connection was closed or failed; the demo retries automatically.

## Practical test sequence

1. Start BrailleBridge through `braillebridge://` or manually.
2. Open the demo and check whether the status card automatically connects to `ws://localhost:5000/ws`.
3. Click `Get line` or wait for the automatic `getBrailleLine`, then verify that a `brailleLine` event arrives.
4. Enable editor mode.
5. Send text and check `sourceText`, `braille.unicodeText`, and `caret`.
6. Move the caret by text index and then by cell index.
7. Use cursor routing and compare `cellIndex` with `textIndex`.
8. Inspect the `Sam` object for events from the physical display.

If these steps work, the full chain is active: browser, WebSocket, BrailleBridge runtime, SAM, braille display, and SSOC feedback.
