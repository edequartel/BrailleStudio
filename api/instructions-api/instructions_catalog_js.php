<?php
declare(strict_types=1);

require_once __DIR__ . '/_instructions_lib.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$items = load_instructions();

$payload = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payload === false) {
    http_response_code(500);
    echo "window.BrailleStudioInstructionCatalog = [];\n";
    echo "window.BrailleStudioInstructionCatalogMeta = { sourceUrl: 'error', count: 0, error: 'json_encode failed' };\n";
    exit;
}

$sourceUrl = 'https://www.tastenbraille.com/braillestudio/instructions-api/instructions_catalog_js.php';
$count = count($items);
$loadedAt = gmdate('Y-m-d\TH:i:s\Z');

echo "window.BrailleStudioInstructionCatalog = {$payload};\n";
echo "window.BrailleStudioInstructionCatalogMeta = {\n";
echo "  sourceUrl: " . json_encode($sourceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ",\n";
echo "  count: {$count},\n";
echo "  loadedAt: " . json_encode($loadedAt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
echo "};\n";
