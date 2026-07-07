# BrailleBridge technische handleiding

Deze pagina legt uit hoe de BrailleBridge-demo communiceert met BrailleBridge, SAM en de brailleleesregel. De demo is een browserclient. BrailleBridge draait lokaal op dezelfde computer en vormt de brug naar de lokale runtime, SAM en het aangesloten brailleapparaat.

## Overzicht

De communicatie bestaat uit drie lagen:

1. De browser opent een WebSocket naar `ws://localhost:5000/ws`.
2. De browser gebruikt optioneel HTTP API endpoints op `http://localhost:5000` om runtime-, apparaat- en tabelinformatie op te vragen.
3. BrailleBridge stuurt SSOC-informatie terug als JSON, vooral via het `brailleLine` event.

De kernstroom is:

```text
Browser demo
  -> WebSocket JSON command
  -> BrailleBridge localhost runtime
  -> SAM
  -> brailleleesregel
  -> SAM event
  -> BrailleBridge JSON event
  -> Browser demo
```

## Waarom localhost

BrailleBridge is een lokale runtime. De browserpagina draait wel in een gewone browser, maar praat met software op dezelfde machine via `localhost`. Daardoor hoeft de brailleleesregel niet rechtstreeks door de website te worden aangesproken. De website kent alleen het lokale WebSocket- en HTTP-contract; de details van drivers, SAM en hardware blijven binnen BrailleBridge.

De standaardadressen zijn:

- WebSocket: `ws://localhost:5000/ws`
- HTTP API: `http://localhost:5000`
- Startlink: `braillebridge://`

De startlink opent de lokale BrailleBridge-app via het protocol dat op de computer is geregistreerd. Daarna controleert de browser opnieuw of de WebSocket bereikbaar is.

## WebSocket verbinding

De WebSocket is het kanaal voor live tweewegcommunicatie. De browser maakt een verbinding met:

```js
const ws = new WebSocket("ws://localhost:5000/ws");
```

Zodra de socket open is, kan de browser JSON-berichten sturen. Elk bericht wordt als UTF-8 tekstframe verzonden. De demo verstuurt dus geen binaire brailledata, maar gewone JSON-objecten.

Voorbeeld:

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

BrailleBridge vertaalt dit bericht naar de lokale runtime. Afhankelijk van het commando wordt tekst gezet, een toets doorgegeven, de caret verplaatst of de editorstatus aangepast.

## Uitgaande WebSocket commando's

De demo gebruikt vooral deze client-naar-server berichten:

- `getBrailleLine`: vraag de actuele brailleregel en SSOC-toestand op.
- `setEditorMode`: zet editor mode aan of uit.
- `setEditorInsertMode`: zet insert mode aan of uit.
- `editorInput`: stuur tekst of een editor-toets.
- `setCaret`: zet de cursor op een tekstpositie.
- `setCaretFromCell`: zet de cursor op basis van een braillecelpositie.
- `moveCaret`: verplaats de cursor relatief.
- `setCaretToBegin`: zet de cursor aan het begin.
- `setCaretToEnd`: zet de cursor aan het einde.
- `setCaretVisibility`: toon of verberg de cursor op de leesregel.
- `cursorRouting`: simuleer of verwerk cursor routing vanaf een braillecel.

Tekstinvoer ziet er zo uit:

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

Toetsinvoer ziet er zo uit:

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

Caret-positionering gebruikt tekstindexen of celindexen:

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

Het verschil is belangrijk. Een tekstindex verwijst naar de positie in de bronstring. Een celindex verwijst naar de positie op de brailleleesregel. Die twee zijn niet altijd gelijk, omdat hoofdlettertekens, cijfertekens en andere voorlooptekens extra braillecellen kunnen gebruiken.

## Inkomende WebSocket events

BrailleBridge stuurt events terug wanneer de status of invoer verandert. De demo verwacht onder andere:

- `brailleLine`: actuele tekst, brailleweergave, caret, status en metadata.
- `cursor`: context van cursor routing.
- `chord`: context van een brailleakkoord.
- `thumbKey`: duimtoets vanaf de leesregel.
- `editorKey`: editor-toets vanaf de leesregel.
- `status`: runtime- of editorstatus.

Een vereenvoudigd `brailleLine` event:

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

De demo gebruikt dit event voor drie dingen:

1. De live braillemonitor bijwerken.
2. De zichtbare statusbadges aanpassen.
3. De volledige JSON-payload in het eventvenster tonen.

## SSOC

SSOC is in deze demo de actuele toestand waarmee browser en BrailleBridge dezelfde regel begrijpen: brontekst, braille-output, cursorpositie, displaylengte, tabel en statusvlaggen. In de praktijk komt deze toestand vooral binnen via `brailleLine`.

SSOC is nodig omdat een browser niet betrouwbaar zelf kan raden hoe tekst op een fysieke brailleleesregel ligt. De omzetting van tekst naar braille is tabelafhankelijk. Sommige tekens nemen meer dan een braillecel in. Ook kunnen hoofdletter-, cijfer- en contexttekens invloed hebben op de mapping tussen tekst en cellen.

Daarom behandelt de browser `brailleLine` als de gezaghebbende toestand. De browser kan een commando sturen, maar wacht daarna op BrailleBridge om te bevestigen wat de echte regel, echte braillecellen en echte caretposities zijn.

Belangrijke velden:

- `sourceText`: tekst zoals BrailleBridge die voor de regel gebruikt.
- `braille.unicodeText`: braillecellen als Unicode brailletekens.
- `caret.textIndex`: caretpositie in tekst.
- `caret.cellIndex`: caretpositie in braillecellen.
- `status.editorMode`: of editorcommando's actief worden verwerkt.
- `status.insertMode`: of insert mode actief is.
- `meta.activeTable`: gebruikte brailletabel.
- `meta.brailleDisplayCells`: aantal cellen van de leesregel.

## HTTP API

Naast de WebSocket kan de browser HTTP gebruiken voor status- en configuratievragen. Dat is geen vervanging voor de WebSocket. HTTP is handig voor momentopnames; WebSocket is nodig voor live events.

De statuscomponent gebruikt bijvoorbeeld API-aanroepen naar:

- `GET /devices/active`
- `GET /brailledisplay/status`

De tabellentool gebruikt:

- `GET /tables`

Deze calls lopen via `http://localhost:5000` of `http://127.0.0.1:5000`. De demo gebruikt de API om te bepalen of de runtime bereikbaar is, welke leesregel actief is en of SAM en het apparaat bruikbare statusinformatie teruggeven.

Een typische statuscontrole werkt zo:

1. Leid uit `ws://localhost:5000/ws` de HTTP-basis af: `http://localhost:5000`.
2. Vraag apparaatstatus op via een HTTP endpoint.
3. Open of heropen de WebSocket.
4. Toon pas "klaar" wanneer runtime, WebSocket en leesregel samen beschikbaar zijn.

## Rol van SAM

SAM zit tussen BrailleBridge en de brailleleesregel. De browser praat niet direct met SAM. In inkomende events kan wel een `Sam` object staan met velden zoals:

- `MsgType`
- `UnitId`
- `Strip`
- `Param`

Deze waarden zijn belangrijk voor debugging. Ze laten zien uit welk SAM-bericht een event is opgebouwd. Voor de demo zijn ze vooral zichtbaar in het JSON-eventvenster, zodat ontwikkelaars kunnen controleren welke fysieke actie of runtimeactie tot een event leidde.

## Editor mode en insert mode

Editor mode bepaalt of BrailleBridge invoer als editorinteractie behandelt. Als editor mode uit staat, kan de browser wel verbonden zijn, maar worden tekst- en caretcommando's niet op dezelfde manier verwerkt.

Insert mode bepaalt hoe nieuwe tekst in de bestaande regel wordt geplaatst. De demo toont beide statussen apart, omdat een WebSocketverbinding alleen niet genoeg zegt over de bewerkstatus.

## Cursor routing

Cursor routing begint op de brailleleesregel. Een gebruiker drukt op een routingtoets bij een braillecel. BrailleBridge vertaalt die celpositie naar context: welk tekstteken, welk woord, welke braillecel en welke tabel horen daarbij.

Omdat tekstindex en celindex kunnen verschillen, is cursor routing altijd afhankelijk van de laatste SSOC-mapping. Een hoofdletter kan bijvoorbeeld een extra braillecel opleveren. De cel waarop de gebruiker drukt hoeft dan niet dezelfde index te hebben als het tekstteken.

De demo kan routing ook simuleren:

```json
{
  "type": "cursorRouting",
  "cellIndex": 3
}
```

Daarna verwacht de browser een `cursor` of `brailleLine` event terug waarmee de echte context zichtbaar wordt.

## Foutafhandeling

Er zijn drie veelvoorkomende foutscenario's:

- BrailleBridge draait niet: HTTP en WebSocket op poort 5000 zijn onbereikbaar.
- BrailleBridge draait wel, maar de WebSocket is gesloten: status kan gedeeltelijk beschikbaar zijn, maar live events ontbreken.
- WebSocket werkt, maar er is geen leesregel of SAM-status: de browser ontvangt mogelijk runtime-events, maar geen betrouwbare hardwarestatus.

De demo logt daarom elke stap:

- `WS connect`: browser probeert te verbinden.
- `WS open`: socket is geopend.
- `WS -> ...`: browser stuurt een command.
- `WS <- JSON`: BrailleBridge stuurt een event terug.
- `WS close` of `WS error`: verbinding is verbroken of mislukt.

## Praktische testvolgorde

1. Start BrailleBridge via `braillebridge://` of handmatig.
2. Controleer of `ws://localhost:5000/ws` verbonden raakt.
3. Klik op `Regel ophalen` en controleer of er een `brailleLine` event binnenkomt.
4. Zet editor mode aan.
5. Stuur tekst en controleer `sourceText`, `braille.unicodeText` en `caret`.
6. Verplaats de caret met tekstindex en daarna met celindex.
7. Gebruik cursor routing en vergelijk `cellIndex` met `textIndex`.
8. Bekijk het `Sam` object bij events vanaf de fysieke leesregel.

Als deze stappen werken, is de volledige keten actief: browser, WebSocket, BrailleBridge runtime, SAM, brailleleesregel en SSOC-terugkoppeling.
