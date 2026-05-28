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
- roept `loadWorkspaceOnline(row.script_id)` aan bij:
  - `command === "load_script"`
  - `status === "pending"`
  - `script_id` gevuld
  - `executed === false`

Je kunt ook direct een bekende code openen:

```text
./api/session-api/laptop.html?session=ABC123
```

## Telefoon

Scan de QR-code op de laptop. Die opent:

```text
./api/session-api/phone.html?session=<random>
```

Daarna scan je op de telefoon de step-link QR-code uit het boek. De telefoon stuurt de code naar `send-step-link.php`. Dat endpoint resolve't de step-link naar `scriptId` en patcht `public.sessions`.

## Script sturen

Test tijdelijk met GET:

```text
./api/session-api/send-script.php?session_code=ABC123&script_id=letters1
```

Productie kan POST gebruiken met `session_code`, `script_id` en optioneel `record_index`.

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
