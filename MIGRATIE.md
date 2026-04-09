# Migratie Naar www.tastenbraille.com

## Doel

BrailleStudio volledig laten draaien op:

- `https://www.tastenbraille.com/braillestudio/`

En niet meer primair op:

- `https://edequartel.github.io/BrailleStudio/`
- `http://127.0.0.1:5500/`

Lokaal blijft alleen voor development.

## Eindbeeld

- frontend draait op `www.tastenbraille.com`
- auth draait op `www.tastenbraille.com`
- API's draaien op `www.tastenbraille.com`
- lessonbuilder, blockly en runmethod gebruiken dezelfde origin
- één login per productie-sessie is genoeg

## Stap 1. Productie als hoofdomgeving

Controleer dat deze pagina's op productie bestaan en werken:

- `/braillestudio/index.html`
- `/braillestudio/blockly/index.html`
- `/braillestudio/runmethod.php`
- `/braillestudio/authentication.html`
- `/braillestudio/info.php`
- `/braillestudio/api/lessonbuilder/lessonbuilder-method.php`
- `/braillestudio/api/lessonbuilder/lessonbuilder-records.php`
- `/braillestudio/api/lessonbuilder/lessonbuilder-steps.php`

Controleer ook dat deze API's werken:

- `/braillestudio/authentication-api/`
- `/braillestudio/blockly-api/`
- `/braillestudio/lessons-api/`
- `/braillestudio/methods-api/`
- `/braillestudio/elevenlabs-api/`

## Stap 2. Assets en data centraliseren

Controleer dat alle benodigde assets online bestaan:

- `/braillestudio/assets/`
- `/braillestudio/klanken/`
- `/braillestudio/sounds/`

Belangrijke bestanden:

- `/braillestudio/assets/tastenbraille.jpeg`
- `/braillestudio/assets/tastenbraille.md`
- `/braillestudio/klanken/aanvankelijklijst.json`
- `/braillestudio/klanken/fonemen_nl_standaard.json`

## Stap 3. Interne links omzetten

Zorg dat alle navigatie en alle knoppen primair verwijzen naar productie:

- `https://www.tastenbraille.com/braillestudio/...`

Gebruik geen GitHub Pages links meer als hoofdroute in:

- homepage
- blockly
- runmethod
- lessonbuilder
- info

## Stap 4. Fetches en runtime paden opschonen

Controleer dat frontend-code alleen nog deze soorten paden gebruikt:

- origin-relatieve paden zoals `/braillestudio/...`
- of expliciet `https://www.tastenbraille.com/braillestudio/...`

Vermijd als primaire runtime:

- `https://edequartel.github.io/BrailleStudio/...`
- `http://127.0.0.1:5500/...`

## Stap 5. Authenticatie centraliseren

Huidige tussenstap:

- auth blijft token-based op `www.tastenbraille.com`
- alle productiepagina's lezen dezelfde token op dezelfde origin

Doel daarna:

- productie omzetten naar secure cookie-auth
- geen losse token-opslag meer nodig voor gewone productieflow

## Stap 6. Productie end-to-end testen

Test in deze volgorde:

1. `authentication.html`
2. `blockly/index.html`
3. scripts laden, opslaan en runnen
4. `lessonbuilder-method.php`
5. `lessonbuilder-records.php`
6. `lessonbuilder-steps.php`
7. lesson opslaan, verwijderen, step run, lesson run
8. `runmethod.php`
9. elevenlabs flow
10. braillemonitor en websocket gedrag

## Stap 7. GitHub Pages terugbrengen tot secundaire rol

Kies één van deze routes:

### Optie A

GitHub Pages alleen nog als simpele landingspagina met link naar productie.

### Optie B

GitHub Pages houden als read-only demo zonder volledige auth/API workflow.

### Optie C

GitHub Pages helemaal uitfaseren.

## Stap 8. Lokaal alleen voor development

Gebruik lokaal alleen nog voor:

- nieuwe blocks testen
- UI aanpassen
- debuggen

Niet als primaire eindgebruiker-omgeving.

## Stap 9. Opruimen

Na stabiele productie:

- dubbele cross-origin logica verminderen
- bridge-auth alleen nog voor localhost laten bestaan
- verouderde GitHub Pages verwijzingen verwijderen
- documentatie bijwerken

## Aanbevolen volgorde

1. productie volledig werkend maken
2. alle links naar productie omzetten
3. auth centraliseren op productie
4. GitHub Pages degraderen tot secundaire rol
5. lokaal alleen development
6. daarna eventueel cookie-auth invoeren

## Praktische beslissing

Voor BrailleStudio is dit de beste hoofdregel:

- `www.tastenbraille.com` is de echte app
- localhost is dev
- GitHub Pages is optioneel en secundair
