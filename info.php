<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_inline_markdown(string $text): string
{
    $escaped = h($text);
    $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;
    return $escaped;
}

function render_markdown(string $raw): string
{
    if (trim($raw) === '') {
        return '<p>Er is nog geen inhoud toegevoegd.</p>';
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
        $html[] = '<p>' . render_inline_markdown(trim(implode(' ', $paragraph))) . '</p>';
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
        $html[] = '<pre><code>' . h(implode("\n", $codeLines)) . '</code></pre>';
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

        if (preg_match('/^(#{1,3})\s+(.*)$/', $trimmed, $matches) === 1) {
            $flushParagraph();
            $closeList();
            $level = min(3, strlen($matches[1]));
            $html[] = sprintf(
                '<h%d>%s</h%d>',
                $level,
                render_inline_markdown(trim($matches[2])),
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
            $html[] = '<li>' . render_inline_markdown(trim($matches[1])) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.*)$/', $trimmed, $matches) === 1) {
            $flushParagraph();
            if ($listType !== 'ol') {
                $closeList();
                $html[] = '<ol>';
                $listType = 'ol';
            }
            $html[] = '<li>' . render_inline_markdown(trim($matches[1])) . '</li>';
            continue;
        }

        if (preg_match('/^>\s?(.*)$/', $trimmed, $matches) === 1) {
            $flushParagraph();
            $closeList();
            $html[] = '<blockquote>' . render_inline_markdown(trim($matches[1])) . '</blockquote>';
            continue;
        }

        $paragraph[] = $trimmed;
    }

    $flushParagraph();
    $flushCodeBlock();
    $closeList();

    return implode("\n", $html);
}

function load_markdown_source(string $remoteUrl, string $localPath): string
{
    $remoteRaw = @file_get_contents($remoteUrl);
    if ($remoteRaw !== false && trim($remoteRaw) !== '') {
        return $remoteRaw;
    }

    if (is_file($localPath)) {
        $localRaw = file_get_contents($localPath);
        if ($localRaw !== false && trim($localRaw) !== '') {
            return $localRaw;
        }
    }

    return '';
}

$rootDir = __DIR__;
$markdownPath = $rootDir . '/assets/tastenbraille.md';
$markdownUrl = 'https://www.tastenbraille.com/braillestudio/assets/tastenbraille.md';
$headerImageUrl = '/braillestudio/assets/tastenbraille.jpeg';
$hasHeaderImage = true;
$contentHtml = render_markdown(load_markdown_source($markdownUrl, $markdownPath));
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Info - Braille Expertise Groep</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .markdown-content h1,
    .markdown-content h2,
    .markdown-content h3 {
      margin: 1.6rem 0 0.8rem;
      font-weight: 700;
      line-height: 1.15;
      color: #0f172a;
    }

    .markdown-content h1 {
      margin-top: 0;
      font-size: 2rem;
    }

    .markdown-content h2 {
      font-size: 1.5rem;
    }

    .markdown-content h3 {
      font-size: 1.2rem;
    }

    .markdown-content p,
    .markdown-content ul,
    .markdown-content ol,
    .markdown-content blockquote,
    .markdown-content pre {
      margin: 0 0 1rem;
      line-height: 1.75;
      color: #334155;
    }

    .markdown-content ul,
    .markdown-content ol {
      padding-left: 1.5rem;
    }

    .markdown-content ul {
      list-style: disc;
    }

    .markdown-content ol {
      list-style: decimal;
    }

    .markdown-content li + li {
      margin-top: 0.35rem;
    }

    .markdown-content a {
      color: #2563eb;
      text-decoration: underline;
      text-underline-offset: 2px;
    }

    .markdown-content blockquote {
      border-left: 4px solid #cbd5e1;
      padding-left: 1rem;
      color: #475569;
    }

    .markdown-content code {
      border: 1px solid #e2e8f0;
      border-radius: 0.4rem;
      background: #f8fafc;
      padding: 0.08rem 0.35rem;
      font-size: 0.92em;
      color: #0f172a;
    }

    .markdown-content pre {
      overflow-x: auto;
      border-radius: 0.9rem;
      background: #0f172a;
      padding: 1rem 1.1rem;
      color: #e2e8f0;
    }

    .markdown-content pre code {
      border: 0;
      background: transparent;
      padding: 0;
      color: inherit;
    }
  </style>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="mx-auto max-w-7xl p-6 space-y-6">
    <header class="relative h-[140px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <?php if ($hasHeaderImage): ?>
        <img
          src="<?= h($headerImageUrl) ?>"
          alt="TastenBraille banner"
          class="absolute inset-0 h-full w-full object-cover"
        >
      <?php endif; ?>
      <div class="absolute inset-0 <?= $hasHeaderImage ? 'bg-white/72' : 'bg-gradient-to-r from-slate-100 via-white to-slate-100' ?>"></div>
      <div class="relative z-10 flex h-full items-center px-6">
        <div class="min-w-0">
          <h1 class="truncate text-3xl font-bold text-slate-900">Braille Expertise Groep</h1>
        </div>
      </div>
    </header>

    <main class="flex min-h-[calc(100vh-260px)] items-start justify-center">
      <section class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <article class="markdown-content">
          <?= $contentHtml ?>
        </article>
      </section>
    </main>
  </div>
</body>
</html>
