# BrailleStudio session-api realtime flow

Deze map gebruikt Supabase Realtime Postgres Changes op `public.sessions`.
De oude polling/session-file flow wordt niet meer gebruikt door `laptop.html`.

## Laptop

Open:

```text
./api/session-api/laptop.html
```

De laptoppagina:

- maakt automatisch een random sessiecode als `session` ontbreekt;
- zet de URL daarna om naar `laptop.html?session=<random>`;
- toont een QR-code voor `phone.html?session=<random>`;
- reset of maakt de sessierij via `create-session.php`;
- gebruikt alleen de publieke Supabase anon key in de browser;
- luistert naar `UPDATE` events op `public.sessions` met filter `session_code=eq.<code>`;
- roept `loadWorkspaceOnline(row.script_id)` aan bij gewone script-opdrachten;
- haalt bij `command === "load_step_link:<code>[:methodId]"` de actuele step-link op en past de opgeslagen `stepInputs` toe voordat de step-link start.

Je kunt ook direct een bekende code openen:

```text
./api/session-api/laptop.html?session=ABC123
```

## Telefoon

Scan de QR-code op de laptop. Die opent:

```text
./api/session-api/phone.html?session=<random>
```

Daarna scan je op de telefoon de step-link QR-code uit het boek. De telefoon stuurt de code naar `send-step-link.php`. Dat endpoint resolve't de step-link naar `scriptId` en patcht `public.sessions` met `load_step_link:<code>[:methodId]`, zodat de laptop de actuele externe variabelen kan ophalen.

Met de knop **Stop step** verstuurt de telefoon via `stop-step.php` het commando `stop_step`. De laptop stopt daarop het actieve Blockly-programma en de actieve audio.

## Script sturen

Test tijdelijk met GET:

```text
./api/session-api/send-script.php?session_code=ABC123&script_id=letters1
```

Productie kan POST gebruiken met `session_code`, `script_id` en optioneel `record_index`.

## Inactieve sessies opruimen

Een laptoppagina verwijdert de actieve sessie automatisch na 30 minuten zonder activiteit. Daarnaast ruimt `create-session.php` bij het starten van een nieuwe sessie oude rijen op uit `public.sessions`, gebaseerd op `updated_at`.

Voor echte achtergrond-cleanup zonder open browser kan een cronjob of Supabase scheduled job dit endpoint periodiek aanroepen:

```text
./api/session-api/cleanup-sessions.php
```

Dat verwijdert sessies waarvan `updated_at` ouder is dan 30 minuten.

## Config

De PHP endpoints lezen:

```text
/home3/kydjgrmy/private/supabase_config.php
```

Verwachte waarden:

- `SUPABASE_URL`
- `SUPABASE_ANON_KEY`
- `SUPABASE_SERVICE_ROLE_KEY`

De service role key wordt alleen server-side gebruikt.
