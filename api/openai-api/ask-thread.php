<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'docent'], 'page');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

$configCandidates = [
    rtrim($_SERVER['HOME'] ?? '', '/') . '/private/openai_config.php',
    '/home3/kydjgrmy/private/openai_config.php',
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/private/openai_config.php',
];

$configPath = null;

foreach ($configCandidates as $candidate) {

    if (
        is_string($candidate)
        && $candidate !== ''
        && is_file($candidate)
        && is_readable($candidate)
    ) {
        $configPath = $candidate;
        break;
    }

}

if ($configPath === null) {
    die('<h1>Config bestand niet gevonden</h1>');
}

$config = require $configPath;

$apiKey = trim((string)($config['OPENAI_API_KEY'] ?? ''));

if ($apiKey === '') {
    die('<h2>OPENAI_API_KEY ontbreekt in config bestand.</h2>');
}

function maskApiKey(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '(empty)';
    }
    if (strlen($trimmed) <= 10) {
        return substr($trimmed, 0, 2) . str_repeat('*', max(0, strlen($trimmed) - 2));
    }
    return substr($trimmed, 0, 6) . str_repeat('*', max(0, strlen($trimmed) - 10)) . substr($trimmed, -4);
}

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

$apiUrl = 'https://api.openai.com/v1/responses';

$vectorStoreId = 'vs_69df252bb3d48191b27fec94575bc341';

$model = $_POST['model'] ?? 'gpt-4o-mini';

$maxResults = 8;

$blocklySchemaPath = __DIR__ . '/blockly_schema.php';
$blocklySchema = is_file($blocklySchemaPath) ? require $blocklySchemaPath : [];

function extractBlocklyBlockTypes(string $source): array
{
    if ($source === '') {
        return [];
    }

    preg_match_all("/Blockly\\.Blocks\\['([^']+)'\\]/", $source, $matches);
    $types = array_values(array_unique(array_map(
        static fn($value): string => trim((string)$value),
        $matches[1] ?? []
    )));
    sort($types);
    return array_values(array_filter($types, static fn(string $value): bool => $value !== ''));
}

function extractBlocklyOutputBlockTypes(string $source): array
{
    if ($source === '') {
        return [];
    }

    preg_match_all("/Blockly\\.Blocks\\['([^']+)'\\]\\s*=\\s*\\{.*?setOutput\\(true\\);/s", $source, $matches);
    $types = array_values(array_unique(array_map(
        static fn($value): string => trim((string)$value),
        $matches[1] ?? []
    )));
    sort($types);
    return array_values(array_filter($types, static fn(string $value): bool => $value !== ''));
}

function buildPromptContext(array $blocklySchema): array
{
    $contractPath = dirname(__DIR__, 2) . '/blockly/contract.txt';
    $contractText = '';
    if (is_file($contractPath) && is_readable($contractPath)) {
        $contractText = trim((string)file_get_contents($contractPath));
    }

    $blocksReferencePath = dirname(__DIR__, 2) . '/blockly/blocks-reference.txt';
    $blocksReferenceText = '';
    if (is_file($blocksReferencePath) && is_readable($blocksReferencePath)) {
        $blocksReferenceText = trim((string)file_get_contents($blocksReferencePath));
    }

    $blocksJsPath = dirname(__DIR__, 2) . '/blockly/blocks.js';
    $blocksJsText = '';
    if (is_file($blocksJsPath) && is_readable($blocksJsPath)) {
        $blocksJsText = (string)file_get_contents($blocksJsPath);
    }

    $builtinCoreBlockTypes = array_values(array_filter(
        $blocklySchema['builtin_core_block_types'] ?? [],
        static fn(mixed $value): bool => is_string($value) && trim($value) !== ''
    ));
    $builtinOutputBlockTypes = array_values(array_filter(
        $blocklySchema['builtin_output_block_types'] ?? [],
        static fn(mixed $value): bool => is_string($value) && trim($value) !== ''
    ));
    $schemaPromptRules = array_values(array_filter(
        $blocklySchema['prompt_rules'] ?? [],
        static fn(mixed $value): bool => is_string($value) && trim($value) !== ''
    ));
    $deprecatedBlockTypes = array_values(array_filter(
        $blocklySchema['deprecated_block_types'] ?? [],
        static fn(mixed $value): bool => is_string($value) && trim($value) !== ''
    ));
    $deprecatedBlockTypeLookup = array_fill_keys($deprecatedBlockTypes, true);

    $validBlocklyBlockTypes = array_values(array_filter(array_unique(array_merge(
        extractBlocklyBlockTypes($blocksJsText),
        $builtinCoreBlockTypes
    )), static fn(string $type): bool => !isset($deprecatedBlockTypeLookup[$type])));
    sort($validBlocklyBlockTypes);

    $outputOnlyBlocklyBlockTypes = array_values(array_unique(array_merge(
        extractBlocklyOutputBlockTypes($blocksJsText),
        $builtinOutputBlockTypes
    )));
    sort($outputOnlyBlocklyBlockTypes);

    $validBlocklyBlockTypesText = implode(', ', $validBlocklyBlockTypes);

    $instructions =
        "Gebruik contract.txt en de andere geüploade bestanden in de vector store als bron.\n\n"
        . "Gebruik geüploade Blockly JSON voorbeeldscripts in de vector store als canonieke voorbeelden voor structuur, nesting, fields, inputs en next-ketens.\n"
        . "Als een voorbeeldscript in de vector store hetzelfde patroon gebruikt, volg dat patroon zo letterlijk mogelijk.\n\n"
        . "Maak exact één geldig JSON-object met precies deze drie hoofdvelden:\n"
        . "1. leerling_opdracht\n"
        . "2. uitleg\n"
        . "3. blockly_json\n\n"
        . "Harde regels:\n"
        . "- leerling_opdracht bevat de tekst voor de leerling.\n"
        . "- uitleg bevat uitleg voor de docent of maker.\n"
        . "- blockly_json moet importeerbare Blockly workspace JSON zijn.\n"
        . "- blockly_json mag NIET de save-wrapper zijn met id/title/meta/overwrite.\n"
        . "- blockly_json moet dus beginnen met deze shape:\n"
        . "  {\n"
        . "    \"blocks\": {\n"
        . "      \"languageVersion\": 0,\n"
        . "      \"blocks\": [ ... ]\n"
        . "    },\n"
        . "    \"variables\": [ ... ]\n"
        . "  }\n"
        . "- Geef nooit één los block terug zoals {\"type\": ... }.\n"
        . "- Als er JSON voorbeeldscripts in de vector store staan, geef dan JSON terug in exact dezelfde soort workspace-vorm als die voorbeelden.\n"
        . "- Gebruik alleen blocktypes, velden, inputs en structuren die echt bestaan in contract.txt of andere geüploade projectbestanden.\n"
        . "- Verzin geen blocktypes zoals event_when_right_thumb_pressed als die niet bestaan.\n"
        . "- Voor rechterduim moet je event_when_thumb_key gebruiken met field KEY = right.\n"
        . "- Gebruik voor algemene toetsen event_when_key_name of event_when_editor_key als dat past.\n"
        . "- Gebruik event_when_key_name alleen voor echte keyboardwaarden zoals F1, Enter, Escape, Tab, ArrowLeft, PageUp.\n"
        . "- Gebruik event_when_thumb_key voor thumbwaarden zoals left, left-middle, right-middle, right, up, down.\n"
        . "- Gebruik dus nooit left/right/up/down als KEY-waarde van event_when_key_name.\n"
        . "- Gebruik NIET event_when_key_pressed, want dat blocktype bestaat niet in dit project.\n"
        . "- Event-blokken zoals event_when_started en event_when_key_name zijn top-level blocks in blocks.blocks[].\n"
        . "- Event-blokken gebruiken hun body alleen via inputs.DO.block.\n"
        . "- Event-blokken mogen nooit in een next.block-keten staan.\n"
        . "- event_when_thumb_key moet altijd een niet-lege fields.KEY hebben, bijvoorbeeld left of right.\n"
        . "- FOUT: {\"type\":\"event_when_started\",\"next\":{...}}\n"
        . "- GOED: {\"type\":\"event_when_started\",\"inputs\":{\"DO\":{\"block\":{...}}}}\n"
        . "- Neem ids, x/y, fields, inputs, next en shadow-structuur over in dezelfde stijl als werkende JSON voorbeelden.\n"
        . "- Gebruik bij statement-ketens altijd Blockly workspace JSON structuur, dus next.block en inputs.DO.block waar nodig.\n"
        . "- Geen markdown.\n"
        . "- Geen codeblokken.\n"
        . "- Geen uitleg buiten de JSON.\n"
        . "- Als de opdracht een spel of oefening beschrijft, maak een volledig werkend script en niet alleen losse beslisblokken.\n"
        . "- Als de opdracht expliciet zowel rechterduimtoets als linkerduimtoets noemt, genereer logica voor beide thumbkeys.\n"
        . "- Als de opdracht zegt dat er meerdere letters of items komen, maak ook voortgangslogica voor meerdere items.\n"
        . "- Bij leesregel-opdrachten moet het script expliciet tekst op de leesregel zetten, antwoord verwerken en daarna naar een volgend item kunnen gaan.\n";

    foreach ($schemaPromptRules as $schemaPromptRule) {
        $instructions .= '- ' . $schemaPromptRule . "\n";
    }

    $instructions .= "- Output moet pure parsebare JSON zijn.";

    $systemText =
        "Je bent een gespecialiseerde Blockly JSON-generator voor BrailleStudio.\n\n"
        . "Je primaire taak is: maak blockly_json dat direct via Blockly import gebruikt kan worden.\n"
        . "Dat betekent:\n"
        . "- geen fictieve of vereenvoudigde blokdefinities\n"
        . "- geen losse fragments zoals {\"type\": \"...\"}\n"
        . "- geen Blockly block definition JSON met message0/previousStatement/nextStatement\n"
        . "- wel echte Blockly workspace state JSON\n\n"
        . "Gebruik geüploade JSON voorbeeldscripts als eerste referentie voor vorm en patroon.\n"
        . "Gebruik contract.txt, blocks-reference.txt en blocks.js als aanvullende waarheid voor geldige blocktypes en regels.\n\n"
        . "Output altijd exact deze structuur:\n\n"
        . "{\n"
        . "  \"leerling_opdracht\": \"...\",\n"
        . "  \"uitleg\": \"...\",\n"
        . "  \"blockly_json\": {\n"
        . "    \"blocks\": {\n"
        . "      \"languageVersion\": 0,\n"
        . "      \"blocks\": []\n"
        . "    },\n"
        . "    \"variables\": []\n"
        . "  }\n"
        . "}\n\n"
        . "Validatie voordat je antwoord geeft:\n"
        . "- Bestaat elk gebruikt blocktype echt in de projectbestanden?\n"
        . "- Is blockly_json een workspace state object?\n"
        . "- Heeft elke statement-link de vorm next.block?\n"
        . "- Gebruiken statement inputs de vorm inputs.DO.block als relevant?\n"
        . "- Is het script functioneel volledig voor de opdracht en niet slechts een gedeeltelijk beslisfragment?\n"
        . "- Is het antwoord pure JSON zonder extra tekst?\n\n"
        . "Extra structuurregel voor event-blokken:\n"
        . "- event_when_started staat top-level in blockly_json.blocks.blocks[].\n"
        . "- event_when_started heeft geen next.\n"
        . "- de uitvoer van event_when_started staat in inputs.DO.block.\n"
        . "- event_when_key_name volgt exact hetzelfde patroon.\n"
        . "- event_when_thumb_key volgt exact hetzelfde patroon.\n"
        . "- plaats event_when_key_name dus niet binnen next.block van een ander block.\n\n"
        . "- plaats event_when_thumb_key dus ook nooit binnen procedures of inputs.STACK van een ander block.\n\n"
        . "Top-level regel:\n"
        . "- top-level blocks zijn events of procedure-definities.\n"
        . "- blocks zoals controls_if, variables_set of bb_set_text horen binnen een event of procedure body, niet top-level.\n\n"
        . "Semantische regel voor keys versus thumbs:\n"
        . "- event_when_key_name = keyboard keys only.\n"
        . "- event_when_thumb_key = thumb buttons only.\n"
        . "- left/right/up/down zijn thumb events, geen keyboard keys.\n\n"
        . "Regel voor beslislogica:\n"
        . "- Gebruik controls_if voor if/else-patronen.\n"
        . "- Gebruik nooit if_else als blocktype.\n\n"
        . "- controls_if gebruikt IF0, DO0 en eventueel ELSE of extra genummerde inputs.\n"
        . "- gebruik niet de verkorte vormen IF of DO voor controls_if.\n\n"
        . "Regel voor outputblokken:\n"
        . "- outputblokken horen in value-inputs, niet direct in statement bodies.\n"
        . "- list_get_item is een waarde-blok, geen statement-blok.\n\n"
        . "- Een veelgebruikt correct patroon is: variables_set(current_word <- list_get_item(...)) en daarna bb_set_text(variables_get(current_word)).\n\n"
        . "Volledigheidsregel voor oefeningen:\n"
        . "- Als de gebruiker zegt dat een leerling letters ziet en moet kiezen tussen linker- en rechterduim, moet het script minimaal bevatten:\n"
        . "  1. initialisatie bij event_when_started\n"
        . "  2. data of lijst met letters/items\n"
        . "  3. een actuele letter of item op de leesregel\n"
        . "  4. een top-level event_when_thumb_key voor right\n"
        . "  5. een top-level event_when_thumb_key voor left\n"
        . "  6. logica voor goed/fout of match/niet-match\n"
        . "  7. voortgang naar het volgende item\n"
        . "- Geef dus geen half script terug met alleen één event of alleen een if-blok zonder voortgang.\n\n"
        . "Belangrijk voorbeeldprincipe:\n"
        . "- Als werkende voorbeeldscripts ids, x/y, shadows of specifieke field-vormen hebben, volg die stijl.\n"
        . "- Verzin geen alternatieve Blockly-structuur als een voorbeeld al laat zien hoe het moet.\n\n"
        . "Belangrijke event-blokken in deze build:\n"
        . "- event_when_started\n"
        . "- event_when_program_ended\n"
        . "- event_when_timer\n"
        . "- event_when_thumb_key\n"
        . "- event_when_any_thumb_key\n"
        . "- event_when_cursor_routing\n"
        . "- event_when_cursor_position_changed\n"
        . "- event_when_chord\n"
        . "- event_when_editor_key\n"
        . "- event_when_key_name\n"
        . "Gebruik dus nooit verzonnen varianten zoals event_when_key_pressed.\n\n"
        . "Geldige blocktypes uit blockly/blocks.js:\n"
        . ($validBlocklyBlockTypesText !== '' ? $validBlocklyBlockTypesText : 'No block types extracted.') . "\n\n"
        . "Contract bron:\n"
        . ($contractText !== '' ? $contractText : 'contract.txt not available in prompt context.') . "\n\n"
        . "Blocks reference bron:\n"
        . ($blocksReferenceText !== '' ? $blocksReferenceText : 'blocks-reference.txt not available in prompt context.');

    return [
        'instructions' => $instructions,
        'systemText' => $systemText,
        'validBlocklyBlockTypes' => $validBlocklyBlockTypes,
        'outputOnlyBlocklyBlockTypes' => $outputOnlyBlocklyBlockTypes,
    ];
}

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['last_student_task'])) {
    $_SESSION['last_student_task'] = '';
}

if (!isset($_SESSION['last_explanation'])) {
    $_SESSION['last_explanation'] = '';
}

if (!isset($_SESSION['last_blockly_json'])) {
    $_SESSION['last_blockly_json'] = '';
}

if (isset($_POST['reset_chat'])) {

    $_SESSION['last_student_task'] = '';
    $_SESSION['last_explanation'] = '';
    $_SESSION['last_blockly_json'] = '';

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$errorMessage = '';

$userText = trim((string)(
    $_POST['user_text'] ?? ''
));

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function h(string $text): string
{
    return htmlspecialchars(
        $text,
        ENT_QUOTES,
        'UTF-8'
    );
}

function extractOutputText(array $response): string
{
    $texts = [];

    foreach ($response['output'] ?? [] as $item) {

        foreach ($item['content'] ?? [] as $content) {

            if (
                ($content['type'] ?? '') === 'output_text'
                && isset($content['text'])
            ) {

                $texts[] = $content['text'];

            }

        }

    }

    return trim(
        implode("\n\n", $texts)
    );
}

function unwrapJsonText(string $text): string
{
    $text = trim($text);

    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/^```\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $text = trim($text);

    $firstObject = strpos($text, '{');
    $lastObject = strrpos($text, '}');

    if (
        $firstObject !== false
        && $lastObject !== false
        && $lastObject > $firstObject
    ) {

        return trim(
            substr(
                $text,
                $firstObject,
                $lastObject - $firstObject + 1
            )
        );

    }

    return $text;
}

function prettyJsonIfValid(mixed $value): string
{
    return json_encode(
        $value,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ) ?: '';
}

function collectWorkspaceBlockTypes(mixed $node, array &$types): void
{
    if (!is_array($node)) {
        return;
    }

    if (isset($node['type']) && is_string($node['type'])) {
        $type = trim($node['type']);
        if ($type !== '') {
            $types[] = $type;
        }
    }

    foreach ($node as $value) {
        if (is_array($value)) {
            collectWorkspaceBlockTypes($value, $types);
        }
    }
}

function collectBlocklyStructureIssues(
    mixed $node,
    array &$issues,
    array $outputOnlyBlockTypes,
    string $path = 'blocks',
    bool $isTopLevel = false
): void {
    if (!is_array($node)) {
        return;
    }

    $type = isset($node['type']) && is_string($node['type'])
        ? trim($node['type'])
        : '';

    if ($type !== '') {
        if (str_starts_with($type, 'event_when_')) {
            if (!$isTopLevel) {
                $issues[] = sprintf('Event block "%s" must be top-level, found at %s.', $type, $path);
            }
            if (array_key_exists('next', $node)) {
                $issues[] = sprintf('Event block "%s" must not use next; put statements in inputs.DO.block.', $type);
            }
            $doBlock = $node['inputs']['DO']['block'] ?? null;
            if (!is_array($doBlock)) {
                $issues[] = sprintf('Event block "%s" must have inputs.DO.block.', $type);
            }
        }

        if (in_array($type, $outputOnlyBlockTypes, true)) {
            if (array_key_exists('next', $node)) {
                $issues[] = sprintf('Output block "%s" must not use next.', $type);
            }
            if (array_key_exists('previous', $node)) {
                $issues[] = sprintf('Output block "%s" must not use previous.', $type);
            }
            if (isset($node['inputs']['DO'])) {
                $issues[] = sprintf('Output block "%s" must not use inputs.DO.', $type);
            }
        }

        if ($type === 'controls_if') {
            if (isset($node['inputs']['IF']) || isset($node['inputs']['DO'])) {
                $issues[] = sprintf('Block "%s" must use IF0/DO0 instead of IF/DO at %s.', $type, $path);
            }
            if (!isset($node['inputs']['IF0']) || !is_array($node['inputs']['IF0'])) {
                $issues[] = sprintf('Block "%s" must have input IF0 at %s.', $type, $path);
            }
            if (!isset($node['inputs']['DO0']) || !is_array($node['inputs']['DO0'])) {
                $issues[] = sprintf('Block "%s" must have input DO0 at %s.', $type, $path);
            }
        }
    }

    foreach ($node as $key => $value) {
        if ($key === 'type' || !is_array($value)) {
            continue;
        }

        if ($key === 'next' && isset($value['block']) && is_array($value['block'])) {
            collectBlocklyStructureIssues($value['block'], $issues, $outputOnlyBlockTypes, $path . '.next.block', false);
            continue;
        }

        if ($key === 'inputs') {
            foreach ($value as $inputName => $inputValue) {
                if (!is_array($inputValue)) {
                    continue;
                }
                if (isset($inputValue['block']) && is_array($inputValue['block'])) {
                    $childType = isset($inputValue['block']['type']) && is_string($inputValue['block']['type'])
                        ? trim($inputValue['block']['type'])
                        : '';
                    if (
                        $childType !== ''
                        && in_array($childType, $outputOnlyBlockTypes, true)
                        && (preg_match('/^DO\\d*$/', $inputName) === 1 || $inputName === 'STACK')
                    ) {
                        $issues[] = sprintf(
                            'Output block "%s" must not be placed directly in statement input "%s" at %s.',
                            $childType,
                            $inputName,
                            $path . '.inputs.' . $inputName . '.block'
                        );
                    }
                    collectBlocklyStructureIssues($inputValue['block'], $issues, $outputOnlyBlockTypes, $path . '.inputs.' . $inputName . '.block', false);
                }
                if (isset($inputValue['shadow']) && is_array($inputValue['shadow'])) {
                    collectBlocklyStructureIssues($inputValue['shadow'], $issues, $outputOnlyBlockTypes, $path . '.inputs.' . $inputName . '.shadow', false);
                }
            }
            continue;
        }

        collectBlocklyStructureIssues($value, $issues, $outputOnlyBlockTypes, $path . '.' . $key, false);
    }
}

function collectBlocklyRequiredFieldIssues(
    mixed $node,
    array &$issues,
    array $blocklySchema,
    string $path = 'blocks'
): void {
    if (!is_array($node)) {
        return;
    }

    $type = isset($node['type']) && is_string($node['type'])
        ? trim($node['type'])
        : '';

    $requiredFieldsByType = is_array($blocklySchema['required_fields_by_type'] ?? null)
        ? $blocklySchema['required_fields_by_type']
        : [];
    $allowedFieldValuesByType = is_array($blocklySchema['allowed_field_values_by_type'] ?? null)
        ? $blocklySchema['allowed_field_values_by_type']
        : [];

    if ($type !== '' && isset($requiredFieldsByType[$type])) {
        $fields = isset($node['fields']) && is_array($node['fields']) ? $node['fields'] : [];
        foreach ($requiredFieldsByType[$type] as $fieldName) {
            $fieldValue = $fields[$fieldName] ?? null;
            if (!is_string($fieldValue) || trim($fieldValue) === '') {
                $issues[] = sprintf('Block "%s" must have non-empty field "%s" at %s.', $type, $fieldName, $path);
            }
        }
        if (isset($allowedFieldValuesByType[$type])) {
            foreach ($allowedFieldValuesByType[$type] as $fieldName => $allowedValues) {
                $fieldValue = $fields[$fieldName] ?? null;
                if (is_string($fieldValue) && trim($fieldValue) !== '' && !in_array($fieldValue, $allowedValues, true)) {
                    $issues[] = sprintf(
                        'Block "%s" has invalid field "%s" value "%s" at %s. Allowed values: %s.',
                        $type,
                        $fieldName,
                        $fieldValue,
                        $path,
                        implode(', ', $allowedValues)
                    );
                }
            }
        }
    }

    foreach ($node as $key => $value) {
        if ($key === 'type' || !is_array($value)) {
            continue;
        }

        if ($key === 'next' && isset($value['block']) && is_array($value['block'])) {
            collectBlocklyRequiredFieldIssues($value['block'], $issues, $blocklySchema, $path . '.next.block');
            continue;
        }

        if ($key === 'inputs') {
            foreach ($value as $inputName => $inputValue) {
                if (!is_array($inputValue)) {
                    continue;
                }
                if (isset($inputValue['block']) && is_array($inputValue['block'])) {
                    collectBlocklyRequiredFieldIssues($inputValue['block'], $issues, $blocklySchema, $path . '.inputs.' . $inputName . '.block');
                }
                if (isset($inputValue['shadow']) && is_array($inputValue['shadow'])) {
                    collectBlocklyRequiredFieldIssues($inputValue['shadow'], $issues, $blocklySchema, $path . '.inputs.' . $inputName . '.shadow');
                }
            }
            continue;
        }

        collectBlocklyRequiredFieldIssues($value, $issues, $blocklySchema, $path . '.' . $key);
    }
}

function applyDefaultBlocklyFields(array &$node, array $blocklySchema): void
{
    $type = isset($node['type']) && is_string($node['type'])
        ? trim($node['type'])
        : '';

    if ($type !== '') {
        if (!isset($node['fields']) || !is_array($node['fields'])) {
            $node['fields'] = [];
        }

        $normalizers = is_array($blocklySchema['normalizers'] ?? null)
            ? $blocklySchema['normalizers']
            : [];
        if (
            $type === 'controls_if'
            && isset($node['inputs'])
            && is_array($node['inputs'])
            && isset($normalizers['controls_if_input_aliases'])
            && is_array($normalizers['controls_if_input_aliases'])
        ) {
            foreach ($normalizers['controls_if_input_aliases'] as $from => $to) {
                if (isset($node['inputs'][$from]) && !isset($node['inputs'][$to])) {
                    $node['inputs'][$to] = $node['inputs'][$from];
                    unset($node['inputs'][$from]);
                }
            }
        }

        if (
            in_array($type, $normalizers['operator_to_field_op_types'] ?? [], true)
            && isset($node['operator'])
            && is_string($node['operator'])
            && !isset($node['fields']['OP'])
        ) {
            $node['fields']['OP'] = $node['operator'];
            unset($node['operator']);
        }

        $defaultFieldsByType = is_array($blocklySchema['default_fields_by_type'] ?? null)
            ? $blocklySchema['default_fields_by_type']
            : [];
        if (isset($defaultFieldsByType[$type]) && is_array($defaultFieldsByType[$type])) {
            foreach ($defaultFieldsByType[$type] as $fieldName => $defaultValue) {
                if (
                    (!isset($node['fields'][$fieldName]) || trim((string)$node['fields'][$fieldName]) === '')
                    && is_string($defaultValue)
                    && $defaultValue !== ''
                ) {
                    $node['fields'][$fieldName] = $defaultValue;
                }
            }
        }
    }

    foreach ($node as $key => &$value) {
        if ($key === 'type' || !is_array($value)) {
            continue;
        }

        if ($key === 'next' && isset($value['block']) && is_array($value['block'])) {
            applyDefaultBlocklyFields($value['block'], $blocklySchema);
            continue;
        }

        if ($key === 'inputs') {
            foreach ($value as &$inputValue) {
                if (!is_array($inputValue)) {
                    continue;
                }
                if (isset($inputValue['block']) && is_array($inputValue['block'])) {
                    applyDefaultBlocklyFields($inputValue['block'], $blocklySchema);
                }
                if (isset($inputValue['shadow']) && is_array($inputValue['shadow'])) {
                    applyDefaultBlocklyFields($inputValue['shadow'], $blocklySchema);
                }
            }
            unset($inputValue);
            continue;
        }

        applyDefaultBlocklyFields($value, $blocklySchema);
    }
    unset($value);
}

function hoistNestedEventBlocksFromNode(array &$node, array &$hoisted): void
{
    foreach ($node as $key => &$value) {
        if ($key === 'type' || !is_array($value)) {
            continue;
        }

        if ($key === 'next' && isset($value['block']) && is_array($value['block'])) {
            $childType = isset($value['block']['type']) && is_string($value['block']['type'])
                ? trim($value['block']['type'])
                : '';
            if ($childType !== '' && str_starts_with($childType, 'event_when_')) {
                $hoisted[] = $value['block'];
                unset($node['next']);
                continue;
            }
            hoistNestedEventBlocksFromNode($value['block'], $hoisted);
            continue;
        }

        if ($key === 'inputs') {
            foreach ($value as $inputName => &$inputValue) {
                if (!is_array($inputValue)) {
                    continue;
                }
                if (isset($inputValue['block']) && is_array($inputValue['block'])) {
                    $childType = isset($inputValue['block']['type']) && is_string($inputValue['block']['type'])
                        ? trim($inputValue['block']['type'])
                        : '';
                    if ($childType !== '' && str_starts_with($childType, 'event_when_')) {
                        $hoisted[] = $inputValue['block'];
                        unset($value[$inputName]['block']);
                    } else {
                        hoistNestedEventBlocksFromNode($inputValue['block'], $hoisted);
                    }
                }
                if (isset($inputValue['shadow']) && is_array($inputValue['shadow'])) {
                    hoistNestedEventBlocksFromNode($inputValue['shadow'], $hoisted);
                }
            }
            unset($inputValue);
            continue;
        }

        hoistNestedEventBlocksFromNode($value, $hoisted);
    }
    unset($value);
}

function normalizeGeneratedBlocklyJson(array $generated, array $blocklySchema): array
{
    if (!isset($generated['blockly_json']) || !is_array($generated['blockly_json'])) {
        return $generated;
    }
    if (!isset($generated['blockly_json']['variables']) || !is_array($generated['blockly_json']['variables'])) {
        $generated['blockly_json']['variables'] = [];
    }
    if (
        !isset($generated['blockly_json']['blocks'])
        || !is_array($generated['blockly_json']['blocks'])
        || !isset($generated['blockly_json']['blocks']['blocks'])
        || !is_array($generated['blockly_json']['blocks']['blocks'])
    ) {
        return $generated;
    }

    $hoisted = [];
    foreach ($generated['blockly_json']['blocks']['blocks'] as &$topLevelBlock) {
        if (!is_array($topLevelBlock)) {
            continue;
        }
        applyDefaultBlocklyFields($topLevelBlock, $blocklySchema);
        hoistNestedEventBlocksFromNode($topLevelBlock, $hoisted);
    }
    unset($topLevelBlock);

    foreach ($hoisted as &$eventBlock) {
        if (!is_array($eventBlock)) {
            continue;
        }
        applyDefaultBlocklyFields($eventBlock, $blocklySchema);
    }
    unset($eventBlock);

    if ($hoisted !== []) {
        $generated['blockly_json']['blocks']['blocks'] = array_values(array_merge(
            $generated['blockly_json']['blocks']['blocks'],
            $hoisted
        ));
    }

    return $generated;
}

function validateGeneratedBlocklyJson(
    array $generated,
    array $validBlockTypes,
    array $outputOnlyBlockTypes,
    array $blocklySchema
): array
{
    if (!array_key_exists('blockly_json', $generated)) {
        throw new RuntimeException('Het modelantwoord mist blockly_json.');
    }

    $blockly = $generated['blockly_json'];
    if (!is_array($blockly)) {
        throw new RuntimeException('blockly_json is geen object.');
    }

    if (!isset($blockly['blocks']) || !is_array($blockly['blocks'])) {
        throw new RuntimeException('blockly_json.blocks ontbreekt of is ongeldig.');
    }

    if (($blockly['blocks']['languageVersion'] ?? null) !== 0) {
        throw new RuntimeException('blockly_json.blocks.languageVersion moet 0 zijn.');
    }

    if (!isset($blockly['blocks']['blocks']) || !is_array($blockly['blocks']['blocks'])) {
        throw new RuntimeException('blockly_json.blocks.blocks ontbreekt of is ongeldig.');
    }

    if (!isset($blockly['variables']) || !is_array($blockly['variables'])) {
        throw new RuntimeException('blockly_json.variables ontbreekt of is ongeldig.');
    }

    $usedTypes = [];
    collectWorkspaceBlockTypes($blockly['blocks']['blocks'], $usedTypes);
    $usedTypes = array_values(array_unique($usedTypes));
    sort($usedTypes);

    $invalidTypes = [];
    if ($validBlockTypes !== []) {
      $validTypeLookup = array_fill_keys($validBlockTypes, true);
      foreach ($usedTypes as $type) {
          if (!isset($validTypeLookup[$type])) {
              $invalidTypes[] = $type;
          }
      }
    }

    $structureIssues = [];
    foreach ($blockly['blocks']['blocks'] as $index => $topLevelBlock) {
        $topLevelType = isset($topLevelBlock['type']) && is_string($topLevelBlock['type'])
            ? trim($topLevelBlock['type'])
            : '';
        if (
            $topLevelType !== ''
            && !str_starts_with($topLevelType, 'event_when_')
            && !in_array($topLevelType, $blocklySchema['top_level_allowed_types'] ?? [], true)
        ) {
            $structureIssues[] = sprintf(
                'Top-level block "%s" is not allowed at blocks.blocks.%d. Use events or procedure definitions as top-level blocks.',
                $topLevelType,
                $index
            );
        }

        collectBlocklyStructureIssues(
            $topLevelBlock,
            $structureIssues,
            $outputOnlyBlockTypes,
            'blocks.blocks.' . $index,
            true
        );
        collectBlocklyRequiredFieldIssues(
            $topLevelBlock,
            $structureIssues,
            $blocklySchema,
            'blocks.blocks.' . $index
        );
    }

    return [
        'usedTypes' => $usedTypes,
        'invalidTypes' => $invalidTypes,
        'structureIssues' => array_values(array_unique($structureIssues)),
    ];
}

function requestOpenAIResponse(
    string $apiUrl,
    string $apiKey,
    string $model,
    string $instructions,
    string $systemText,
    string $vectorStoreId,
    int $maxResults,
    string $userText,
    ?string $extraUserText = null
): array {
    $input = [
        [
            'role' => 'system',
            'content' => $systemText,
        ],
        [
            'role' => 'user',
            'content' => $userText,
        ],
    ];

    if ($extraUserText !== null && trim($extraUserText) !== '') {
        $input[] = [
            'role' => 'user',
            'content' => $extraUserText,
        ];
    }

    $payload = [
        'model' => $model,
        'instructions' => $instructions,
        'tools' => [
            [
                'type' => 'file_search',
                'vector_store_ids' => [
                    $vectorStoreId,
                ],
                'max_num_results' => $maxResults,
            ],
        ],
        'input' => $input,
    ];

    $ch = curl_init($apiUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
        CURLOPT_TIMEOUT => 120,
    ]);

    $responseBody = curl_exec($ch);

    if ($responseBody === false) {
        throw new RuntimeException(
            'cURL fout: ' . curl_error($ch)
        );
    }

    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decodedResponse = json_decode($responseBody, true);

    if (!is_array($decodedResponse)) {
        throw new RuntimeException('Geen geldige JSON ontvangen.');
    }

    if ($httpCode >= 400) {
        $message = $decodedResponse['error']['message'] ?? ('HTTP fout ' . $httpCode);
        throw new RuntimeException($message);
    }

    $assistantText = extractOutputText($decodedResponse);

    if ($assistantText === '') {
        throw new RuntimeException('Geen antwoord ontvangen.');
    }

    $assistantText = unwrapJsonText($assistantText);
    $generated = json_decode($assistantText, true);

    if (!is_array($generated)) {
        throw new RuntimeException('Het model gaf geen parsebare JSON terug.');
    }

    return $generated;
}

/*
|--------------------------------------------------------------------------
| HANDLE REQUEST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $userText !== ''
) {

    try {
        $promptContext = buildPromptContext($blocklySchema);
        $instructions = $promptContext['instructions'];
        $systemText = $promptContext['systemText'];
        $validBlocklyBlockTypes = $promptContext['validBlocklyBlockTypes'];
        $outputOnlyBlocklyBlockTypes = $promptContext['outputOnlyBlocklyBlockTypes'];

        $generated = null;
        $validation = null;
        $maxAttempts = 3;
        $extraUserText = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $generated = requestOpenAIResponse(
                $apiUrl,
                $apiKey,
                (string)$model,
                $instructions,
                $systemText,
                $vectorStoreId,
                $maxResults,
                $userText,
                $extraUserText
            );
            $generated = normalizeGeneratedBlocklyJson($generated, $blocklySchema);

            $validation = validateGeneratedBlocklyJson(
                $generated,
                $validBlocklyBlockTypes,
                $outputOnlyBlocklyBlockTypes,
                $blocklySchema
            );

            if (($validation['invalidTypes'] ?? []) === [] && ($validation['structureIssues'] ?? []) === []) {
                break;
            }

            if ($attempt >= $maxAttempts) {
                break;
            }

            $retryProblems = [];
            if (($validation['invalidTypes'] ?? []) !== []) {
                $retryProblems[] = 'ongeldige blocktypes: ' . implode(', ', $validation['invalidTypes']);
            }
            if (($validation['structureIssues'] ?? []) !== []) {
                $retryProblems[] = 'ongeldige Blockly-structuur: ' . implode(' | ', $validation['structureIssues']);
            }

            $previousJson = json_encode($generated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (!is_string($previousJson) || trim($previousJson) === '') {
                $previousJson = '{}';
            }

            $extraUserText =
                "Je vorige antwoord bevatte fouten: "
                . implode(' ; ', $retryProblems)
                . ".\n"
                . "Herstel het vorige JSON-object in plaats van een nieuw patroon te verzinnen.\n"
                . "Gebruik alleen bestaande blocktypes uit blockly/blocks.js.\n"
                . "Gebruik voor key events bijvoorbeeld event_when_key_name of event_when_editor_key.\n"
                . "Gebruik NIET event_when_key_pressed.\n"
                . "Gebruik voor if/else-logica controls_if en nooit if_else.\n"
                . "Als de bedoeling left/right/up/down thumbbediening is, gebruik dan event_when_thumb_key met KEY left/right/up/down.\n"
                . "Gebruik event_when_key_name alleen voor echte keyboard keys zoals F1, Enter, Escape, Tab of ArrowLeft.\n"
                . "Event-blokken zoals event_when_started, event_when_key_name en event_when_thumb_key moeten top-level staan in blocks.blocks[].\n"
                . "Top-level blocks moeten events of procedure-definities zijn; zet dus geen controls_if top-level.\n"
                . "Event-blokken mogen nooit genest staan in procedures, inputs.STACK, inputs.DO van andere events of andere statement bodies.\n"
                . "event_when_thumb_key moet altijd een niet-lege fields.KEY hebben, bijvoorbeeld left, right, up of down.\n"
                . "Goed patroon: top-level event block in blocks.blocks[] met inputs.DO.block als body.\n"
                . "Zet outputblokken zoals list_get_item, variables_get, text en math_number nooit direct in DO0 of STACK.\n"
                . "Gebruik list_get_item alleen als waarde in een input of sla de uitkomst eerst op met variables_set.\n"
                . "Gebruik voor een volgend woord het patroon: variables_set(current_word = list_get_item(...)) en daarna bb_set_text(variables_get(current_word)).\n"
                . "Als de opdracht een letteroefening met linker- en rechterduim beschrijft, maak dan een compleet script met startlogica, leesregel-uitvoer, right-thumb event, left-thumb event en voortgang naar een volgend item.\n"
                . "Output-blokken zoals text, text_join, math_number en klanken_word_get_sounds mogen geen next- of previous-keten hebben.\n"
                . "Geef exact alleen gecorrigeerde JSON terug.\n\n"
                . "Vorige ongeldige JSON:\n"
                . $previousJson;
        }

        if (($validation['invalidTypes'] ?? []) !== [] || ($validation['structureIssues'] ?? []) !== []) {
            $finalProblems = [];
            if (($validation['invalidTypes'] ?? []) !== []) {
                $finalProblems[] = 'ongeldige Blockly blocktypes: ' . implode(', ', $validation['invalidTypes']);
            }
            if (($validation['structureIssues'] ?? []) !== []) {
                $finalProblems[] = 'ongeldige Blockly-structuur: ' . implode(' | ', $validation['structureIssues']);
            }
            throw new RuntimeException(
                implode(' ; ', $finalProblems)
            );
        }

        $_SESSION['last_student_task'] =
            (string)(
                $generated['leerling_opdracht']
                ?? ''
            );

        $_SESSION['last_explanation'] =
            (string)(
                $generated['uitleg']
                ?? ''
            );

        $_SESSION['last_blockly_json'] =
            prettyJsonIfValid(
                $generated['blockly_json']
                ?? new stdClass()
            );

        header('Location: ' . $_SERVER['PHP_SELF']);

        exit;

    } catch (Throwable $e) {

        $errorMessage = $e->getMessage();

        $_SESSION['last_student_task'] = '';

        $_SESSION['last_explanation'] =
            'Fout: ' . $errorMessage;

        $_SESSION['last_blockly_json'] =
            prettyJsonIfValid([
                'error' => $errorMessage,
            ]);
    }

}

$lastStudentTask =
    $_SESSION['last_student_task'] ?? '';

$lastExplanation =
    $_SESSION['last_explanation'] ?? '';

$lastBlocklyJson =
    $_SESSION['last_blockly_json'] ?? '';

?>
<!DOCTYPE html>
<html lang="nl">

<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/braillestudio/apple-touch-icon.png">
  <link rel="manifest" href="/braillestudio/site.webmanifest">

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>BrailleStudio Blockly JSON Generator</title>

<style>

body{
    margin:0;
    padding:30px;
    background:#f3f6fb;
    font-family:Arial,sans-serif;
    color:#1f2937;
}

.wrapper{
    max-width:1000px;
    margin:0 auto;
}

.card{
    background:#fff;
    border-radius:20px;
    padding:24px;
    margin-bottom:24px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

h1,h2{
    margin-top:0;
}

.small{
    font-size:13px;
    color:#6b7280;
}

.field{
    margin-bottom:20px;
}

label{
    display:block;
    margin-bottom:8px;
    font-weight:bold;
}

input,
textarea{
    width:100%;
    box-sizing:border-box;
    padding:14px;
    border-radius:14px;
    border:1px solid #d1d5db;
    font-size:15px;
}

textarea{
    min-height:140px;
    resize:vertical;
}

.button-row{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

button{
    background:#2563eb;
    color:white;
    border:0;
    padding:14px 18px;
    border-radius:12px;
    font-size:15px;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#1d4ed8;
}

.reset{
    background:#6b7280;
}

.reset:hover{
    background:#4b5563;
}

.copy-btn{
    background:#10b981;
}

.copy-btn:hover{
    background:#059669;
}

.output-box{
    background:#f9fafb;
    color:#111827;
    border:1px solid #e5e7eb;
    border-radius:16px;
    padding:18px;
    overflow:auto;
    white-space:pre-wrap;
    font-size:15px;
    line-height:1.6;
    min-height:90px;
}

.json-box{
    background:#0f172a;
    color:#e5e7eb;
    font-family:Consolas, Monaco, monospace;
    white-space:pre;
    min-height:260px;
}

.output-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:12px;
}

.error{
    background:#fef2f2;
    border:1px solid #fecaca;
    color:#991b1b;
    padding:16px;
    border-radius:12px;
    margin-bottom:20px;
}

/*
|--------------------------------------------------------------------------
| LOADING OVERLAY
|--------------------------------------------------------------------------
*/

.loading-overlay{
    position:fixed;
    inset:0;
    background:rgba(255,255,255,.82);
    backdrop-filter:blur(4px);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
}

.loading-box{
    background:white;
    padding:28px 34px;
    border-radius:20px;
    box-shadow:0 10px 40px rgba(0,0,0,.15);
    text-align:center;
    min-width:260px;
}

.spinner{
    width:56px;
    height:56px;
    border:6px solid #dbeafe;
    border-top:6px solid #2563eb;
    border-radius:50%;
    animation:spin 1s linear infinite;
    margin:0 auto 18px auto;
}

.loading-title{
    font-size:18px;
    font-weight:bold;
    margin-bottom:8px;
}

.loading-sub{
    color:#6b7280;
    font-size:14px;
}

.loading-steps{
    margin:16px 0 0 0;
    padding:0;
    list-style:none;
    text-align:left;
    font-size:14px;
    color:#4b5563;
}

.loading-steps li{
    padding:6px 0;
    display:flex;
    align-items:center;
    gap:10px;
}

.loading-dot{
    width:10px;
    height:10px;
    border-radius:999px;
    background:#cbd5e1;
    flex:0 0 auto;
}

.loading-steps li.active .loading-dot{
    background:#2563eb;
    box-shadow:0 0 0 4px rgba(37,99,235,.14);
}

.loading-steps li.done .loading-dot{
    background:#10b981;
}

.loading-elapsed{
    margin-top:16px;
    font-size:13px;
    color:#6b7280;
}

@keyframes spin{
    from{
        transform:rotate(0deg);
    }
    to{
        transform:rotate(360deg);
    }
}

</style>

  <meta property="og:type" content="website">
  <meta property="og:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:secure_url" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1729">
  <meta property="og:image:height" content="910">
  <meta property="og:image:alt" content="BrailleStudio">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta name="twitter:image:alt" content="BrailleStudio">
</head>

<body>

<div
    id="loadingOverlay"
    class="loading-overlay"
>

    <div class="loading-box">

        <div class="spinner"></div>

        <div class="loading-title">
            Blockly JSON genereren...
        </div>

        <div class="loading-sub">
            Bezig met opbouwen en controleren van Blockly JSON
        </div>

        <ul class="loading-steps" id="loadingSteps">
            <li class="active">
                <span class="loading-dot"></span>
                <span>Lokale context laden</span>
            </li>
            <li>
                <span class="loading-dot"></span>
                <span>Vector store doorzoeken</span>
            </li>
            <li>
                <span class="loading-dot"></span>
                <span>Modelantwoord genereren</span>
            </li>
            <li>
                <span class="loading-dot"></span>
                <span>Blockly JSON valideren en herstellen</span>
            </li>
        </ul>

        <div class="loading-elapsed" id="loadingElapsed">
            0 s
        </div>

    </div>

</div>

<div class="wrapper">

    <div class="card">

        <h1>BrailleStudio Blockly JSON Generator</h1>

        <p class="small">
            Genereert leerlingtekst, uitleg en Blockly JSON-script.
        </p>

    </div>

    <div class="card">

        <form
            method="post"
            id="generatorForm"
        >

            <div class="field">

                <label>Model</label>

                <input
                    type="text"
                    name="model"
                    value="<?= h((string)$model) ?>"
                >

            </div>

            <div class="field">

                <label>Opdracht voor generator</label>

                <textarea
                    name="user_text"
                    placeholder="Bijvoorbeeld: Maak een script waarin de leerling het woord maan leest en daarna op cursor-routingtoets 3 drukt."
                    autofocus
                ></textarea>

            </div>

            <div class="button-row">

                <button type="submit">
                    Genereer
                </button>

                <button
                    type="submit"
                    name="reset_chat"
                    value="1"
                    class="reset"
                >
                    Nieuwe thread
                </button>

            </div>

        </form>

    </div>

    <?php if ($errorMessage): ?>

        <div class="error">
            <?= h($errorMessage) ?>
        </div>

    <?php endif; ?>

    <div class="card">

        <div class="output-header">

            <h2>Opdracht voor leerling</h2>

            <button
                type="button"
                class="copy-btn"
                onclick="copyBox('studentTask', this)"
            >
                Kopieer
            </button>

        </div>

        <div
            id="studentTask"
            class="output-box"
        ><?= h(
            $lastStudentTask !== ''
                ? $lastStudentTask
                : 'Nog geen opdracht gegenereerd.'
        ) ?></div>

    </div>

    <div class="card">

        <div class="output-header">

            <h2>Uitleg</h2>

            <button
                type="button"
                class="copy-btn"
                onclick="copyBox('explanationBox', this)"
            >
                Kopieer
            </button>

        </div>

        <div
            id="explanationBox"
            class="output-box"
        ><?= h(
            $lastExplanation !== ''
                ? $lastExplanation
                : 'Nog geen uitleg gegenereerd.'
        ) ?></div>

    </div>

    <div class="card">

        <div class="output-header">

            <h2>Blockly JSON script</h2>

            <button
                type="button"
                class="copy-btn"
                onclick="copyBox('blocklyJsonBox', this)"
            >
                Kopieer JSON
            </button>

        </div>

        <pre
            id="blocklyJsonBox"
            class="output-box json-box"
        ><?= h(
            $lastBlocklyJson !== ''
                ? $lastBlocklyJson
                : '{ "status": "nog_geen_script" }'
        ) ?></pre>

    </div>

</div>

<script>

function copyBox(id, button) {

    const text =
        document
        .getElementById(id)
        .innerText;

    navigator.clipboard.writeText(text);

    const original =
        button.innerText;

    button.innerText =
        'Gekopieerd';

    setTimeout(() => {

        button.innerText =
            original;

    }, 1500);

}

document
    .getElementById('generatorForm')
    .addEventListener('submit', function(event){

        const form = event.currentTarget;
        const overlay =
            document.getElementById('loadingOverlay');
        const elapsed =
            document.getElementById('loadingElapsed');
        const steps =
            Array.from(document.querySelectorAll('#loadingSteps li'));

        overlay.style.display = 'flex';

        const submitButtons =
            Array.from(form.querySelectorAll('button[type="submit"]'));

        submitButtons.forEach((button) => {
            button.disabled = true;
        });

        let seconds = 0;
        elapsed.innerText = '0 s';

        const setActiveStep = (index) => {
            steps.forEach((step, stepIndex) => {
                step.classList.remove('active', 'done');
                if (stepIndex < index) {
                    step.classList.add('done');
                } else if (stepIndex === index) {
                    step.classList.add('active');
                }
            });
        };

        setActiveStep(0);

        const elapsedTimer = setInterval(() => {
            seconds += 1;
            elapsed.innerText = seconds + ' s';
        }, 1000);

        const phaseTimers = [
            setTimeout(() => setActiveStep(1), 400),
            setTimeout(() => setActiveStep(2), 1400),
            setTimeout(() => setActiveStep(3), 4200),
        ];

        window.addEventListener('pageshow', function(){
            clearInterval(elapsedTimer);
            phaseTimers.forEach(clearTimeout);
        }, { once: true });

});

</script>

</body>
</html>
