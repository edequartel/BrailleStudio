# Handleiding `pages/editor.html`

## Doel
De editorpagina is een minimale WebSocket-client voor BrailleBridge.
Je kunt tekst typen, editor-keys sturen en live de SSOC `brailleLine` terugkrijgen.

Bestand:
- `pages/editor.html`

Contractbron:
- `documents/contract.md`

## Starten
1. Start BrailleBridge lokaal zodat WebSocket bereikbaar is op `ws://localhost:5000/ws`.
2. Open `pages/editor.html` in de browser.
3. Bij laden verbindt de pagina automatisch en stuurt direct:
   - `{ "type": "command", "command": "setEditorMode", "enabled": true }`

## Wat je ziet
### Braille monitor kaart
Toont waarden uit het laatste geldige `brailleLine` event:
- `UnicodeText` -> `braille.unicodeText`
- `Table` -> `meta.activeTable`
- `CaretPosition` -> `meta.caretPosition`
- `SourceText` -> `sourceText`

De WS status-dot wordt groen bij actieve verbinding.

### Editor input kaart
In `editorInput` wordt input automatisch als contractcommando verstuurd.

## Uitgaande berichten (client -> server)
Alle berichten volgen exact het contract:

### Editor mode aan
```json
{ "type": "command", "command": "setEditorMode", "enabled": true }
```

### Tekst invoer
Bij gewone tekens (input-event):
```json
{ "type": "command", "command": "editorInput", "input": { "kind": "text", "text": "a" } }
```

### Key invoer
Bij toetsen (keydown-event):
```json
{ "type": "command", "command": "editorInput", "input": { "kind": "key", "key": "Backspace" } }
```

Ondersteunde keys:
- `Backspace`
- `Space`
- `Enter`
- `ArrowLeft`
- `ArrowRight`
- `ArrowUp`
- `ArrowDown`

## Inkomende berichten (server -> client)
De pagina verwerkt mixed-case volgens contract:
- event type: `Type` of `type`
- status: `Ok` of `ok`

Alleen geldige `brailleLine` events met `ok/Ok === true` worden toegepast.

## Caret-sync gedrag
Bij elk geldig `brailleLine` event:
1. `editorInput.value` wordt gesynchroniseerd met `sourceText` (als aanwezig).
2. De cursorpositie in `editorInput` wordt gezet op `meta.caretPosition`.
3. De positie wordt geclamped naar een geldige range van de huidige tekstlengte.

Dit zorgt ervoor dat clientweergave en server SSOC in sync blijven.

## Reconnect knop
`Reconnect` sluit de bestaande socket (indien aanwezig) en maakt een nieuwe verbinding.
Na reconnect wordt opnieuw `setEditorMode` met `enabled: true` gestuurd bij `open`.

## Bekende aandachtspunten
- Als de server geen `brailleLine` terugstuurt, blijft de monitor leeg.
- Als `meta.caretPosition` ontbreekt, blijft caret-veld leeg en wordt cursor niet verplaatst.
- De pagina gebruikt hardcoded WS endpoint: `ws://localhost:5000/ws`.

## Snelle test
1. Open editor.
2. Typ `abc`.
3. Verifieer dat `UnicodeText` en `SourceText` mee veranderen.
4. Druk `ArrowLeft` of `Backspace`.
5. Verifieer dat `CaretPosition` wijzigt en de cursor in `editorInput` meebeweegt.
