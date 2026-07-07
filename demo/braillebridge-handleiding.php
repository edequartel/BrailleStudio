<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/language.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$baseUrl = preg_replace('~/demo$~', '', $scriptDir) ?? '';
$assetBase = ($baseUrl === '' ? '..' : $baseUrl);
$markdownPath = __DIR__ . '/braillebridge-handleiding.md';

function bridge_manual_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function bridge_manual_inline_markdown(string $text): string
{
    $escaped = bridge_manual_h($text);
    $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;
    return $escaped;
}

function bridge_manual_render_markdown(string $raw): string
{
    if (trim($raw) === '') {
        return '<p>Er is nog geen handleiding toegevoegd.</p>';
    }

    $lines = preg_split('/\R/', $raw) ?: [];
    $html = [];
    $listType = null;
    $paragraph = [];
    $inCodeBlock = false;
    $codeLines = [];

    $flushParagraph = static function () use (&$paragraph, &$html): void {
        if (!$paragraph) {
            return;
        }
        $html[] = '<p>' . bridge_manual_inline_markdown(trim(implode(' ', $paragraph))) . '</p>';
        $paragraph = [];
    };

    $closeList = static function () use (&$listType, &$html): void {
        if ($listType) {
            $html[] = sprintf('</%s>', $listType);
            $listType = null;
        }
    };

    $flushCodeBlock = static function () use (&$inCodeBlock, &$codeLines, &$html): void {
        if (!$inCodeBlock) {
            return;
        }
        $html[] = '<pre><code>' . bridge_manual_h(implode("\n", $codeLines)) . '</code></pre>';
        $inCodeBlock = false;
        $codeLines = [];
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '```')) {
            $flushParagraph();
            $closeList();
            if ($inCodeBlock) {
                $flushCodeBlock();
            } else {
                $inCodeBlock = true;
                $codeLines = [];
            }
            continue;
        }

        if ($inCodeBlock) {
            $codeLines[] = rtrim($line, "\r");
            continue;
        }

        if ($trimmed === '') {
            $flushParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^(#{1,4})\s+(.*)$/', $trimmed, $matches) === 1) {
            $flushParagraph();
            $closeList();
            $level = min(4, strlen($matches[1]));
            $html[] = sprintf(
                '<h%d>%s</h%d>',
                $level,
                bridge_manual_inline_markdown(trim($matches[2])),
                $level
            );
            continue;
        }

        if (preg_match('/^[-*]\s+(.*)$/', $trimmed, $matches) === 1) {
            $flushParagraph();
            if ($listType !== 'ul') {
                $closeList();
                $html[] = '<ul>';
                $listType = 'ul';
            }
            $html[] = '<li>' . bridge_manual_inline_markdown(trim($matches[1])) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.*)$/', $trimmed, $matches) === 1) {
            $flushParagraph();
            if ($listType !== 'ol') {
                $closeList();
                $html[] = '<ol>';
                $listType = 'ol';
            }
            $html[] = '<li>' . bridge_manual_inline_markdown(trim($matches[1])) . '</li>';
            continue;
        }

        if (preg_match('/^>\s?(.*)$/', $trimmed, $matches) === 1) {
            $flushParagraph();
            $closeList();
            $html[] = '<blockquote>' . bridge_manual_inline_markdown(trim($matches[1])) . '</blockquote>';
            continue;
        }

        $paragraph[] = $trimmed;
    }

    $flushParagraph();
    $flushCodeBlock();
    $closeList();

    return implode("\n", $html);
}

$markdownRaw = is_file($markdownPath) ? (file_get_contents($markdownPath) ?: '') : '';
$contentHtml = bridge_manual_render_markdown($markdownRaw);
?>
<!doctype html>
<html <?= bs_language_html_attrs() ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BrailleBridge handleiding</title>
  <link rel="icon" href="<?= bridge_manual_h($assetBase) ?>/favicon.ico" sizes="any">
  <link rel="stylesheet" href="<?= bridge_manual_h($assetBase) ?>/tabler/core/dist/css/tabler.min.css">
  <link rel="stylesheet" href="<?= bridge_manual_h($assetBase) ?>/tabler/icons-webfont/dist/tabler-icons.min.css">
  <style>
    .manual-content {
      max-width: 56rem;
    }
    .manual-content h1,
    .manual-content h2,
    .manual-content h3,
    .manual-content h4 {
      margin: 1.75rem 0 .75rem;
      font-weight: 700;
      line-height: 1.2;
    }
    .manual-content h1 {
      margin-top: 0;
      font-size: 2rem;
    }
    .manual-content h2 {
      border-top: 1px solid var(--tblr-border-color);
      padding-top: 1.25rem;
      font-size: 1.45rem;
    }
    .manual-content h3 {
      font-size: 1.2rem;
    }
    .manual-content p,
    .manual-content ul,
    .manual-content ol,
    .manual-content blockquote,
    .manual-content pre {
      margin: 0 0 1rem;
      line-height: 1.7;
    }
    .manual-content ul,
    .manual-content ol {
      padding-left: 1.5rem;
    }
    .manual-content li + li {
      margin-top: .35rem;
    }
    .manual-content code {
      border: 1px solid var(--tblr-border-color);
      border-radius: .35rem;
      background: var(--tblr-bg-surface-secondary);
      padding: .08rem .35rem;
      color: var(--tblr-body-color);
      font-size: .9em;
    }
    .manual-content pre {
      overflow-x: auto;
      border-radius: var(--tblr-border-radius);
      background: #182433;
      padding: 1rem;
      color: #f6f8fb;
    }
    .manual-content pre code {
      border: 0;
      background: transparent;
      padding: 0;
      color: inherit;
    }
    .manual-content blockquote {
      border-left: 4px solid var(--tblr-border-color);
      padding-left: 1rem;
      color: var(--tblr-secondary);
    }
  </style>
</head>
<body>
<div class="page">
  <header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
      <a class="navbar-brand navbar-brand-autodark" href="<?= bridge_manual_h($assetBase) ?>/index.php">
        <img src="<?= bridge_manual_h($assetBase) ?>/style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="<?= bridge_manual_h($assetBase) ?>/style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
      </a>
      <div class="navbar-nav flex-row align-items-center ms-auto">
        <?= language_switcher('me-2') ?>
        <a class="btn btn-outline-secondary" href="<?= bridge_manual_h($assetBase) ?>/demo/braillebridge.php">
          <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
          Terug naar demo
        </a>
      </div>
    </div>
  </header>

  <main class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="page-pretitle">BrailleBridge demo</div>
        <h1 class="page-title">Handleiding</h1>
        <div class="text-secondary mt-2">Technische uitleg over WebSocket, API en SSOC-communicatie.</div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <section class="card">
          <div class="card-body">
            <article class="manual-content">
              <?= $contentHtml ?>
            </article>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>
<script src="<?= bridge_manual_h($assetBase) ?>/tabler/core/dist/js/tabler.min.js"></script>
<script src="<?= bridge_manual_h($assetBase) ?>/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
