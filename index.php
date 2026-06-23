<?php
declare(strict_types=1);

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$baseUrl = $scriptDir === '' ? './' : $scriptDir . '/';
$loginHref = $baseUrl . 'authentication.php?returnTo=' . rawurlencode($baseUrl . 'index.php');

require_once __DIR__ . '/auth/bootstrap.php';

$authUser = null;
try {
    $authUser = bs_auth_current_user();
} catch (Throwable $e) {
    $authUser = null;
}

$modules = [
    [
        'title' => 'Oefenen',
        'eyebrow' => 'Leerling',
        'description' => 'Start brailleleesactiviteiten met woorden, verhalen, auditieve feedback en tactiele herkenning.',
        'icon' => 'ti-book',
        'theme' => 'primary',
        'public' => true,
        'roles' => ['leerling', 'docent', 'admin', 'developer'],
        'links' => [
            ['label' => 'MPOP starten', 'href' => $baseUrl . 'runmethod.php?id=mpop-1775631274214', 'icon' => 'ti-player-play'],
            ['label' => 'Braille sessie', 'href' => $baseUrl . 'api/session-api/laptop.html', 'icon' => 'ti-device-laptop'],
        ],
    ],
    [
        'title' => 'Lessen maken',
        'eyebrow' => 'Docent',
        'description' => 'Bouw interactieve lessen, methodes en sessies voor begeleide brailletraining.',
        'icon' => 'ti-layout-dashboard',
        'theme' => 'green',
        'roles' => ['docent', 'admin', 'developer'],
        'links' => [
            ['label' => 'Teacher Dashboard', 'href' => $baseUrl . 'api/xapi-api/teacher-dashboard.php', 'icon' => 'ti-chart-bar'],
            ['label' => 'Klanken', 'href' => $baseUrl . 'klanken/index.php', 'icon' => 'ti-music'],
        ],
    ],
    [
        'title' => 'Blockly ontwikkelen',
        'eyebrow' => 'Ontwikkeling',
        'description' => 'Ontwikkel en beheer Blockly-activiteiten voor interactieve braillelessen.',
        'icon' => 'ti-puzzle',
        'theme' => 'orange',
        'roles' => ['admin', 'developer'],
        'links' => [
            ['label' => 'Lesson Builder', 'href' => $baseUrl . 'api/lessonbuilder/lessonbuilder.php', 'icon' => 'ti-list-details'],
            ['label' => 'Session Builder', 'href' => $baseUrl . 'api/session-api/admin.php', 'icon' => 'ti-users-group'],
            ['label' => 'Blockly editor', 'href' => $baseUrl . 'blockly/index.php', 'icon' => 'ti-puzzle'],
        ],
    ],
    [
        'title' => 'Tools',
        'eyebrow' => 'Techniek',
        'description' => 'Koppel leesregels, bekijk tabellen en gebruik hulpmiddelen voor testen en beheer.',
        'icon' => 'ti-tools',
        'theme' => 'purple',
        'roles' => ['admin', 'developer'],
        'links' => [
            ['label' => 'BrailleBridge', 'href' => $baseUrl . 'tools/braillebridge-com.php', 'icon' => 'ti-plug-connected'],
            ['label' => 'Brailletabellen', 'href' => $baseUrl . 'tools/tables.php', 'icon' => 'ti-table'],
            ['label' => 'Fonemen', 'href' => $baseUrl . 'api/phonemes-api/index.php', 'icon' => 'ti-wave-saw-tool'],
            ['label' => 'Sounds optimaliseren', 'href' => $baseUrl . 'tools/optimize-sounds.php', 'icon' => 'ti-file-music'],
            ['label' => 'QR-code', 'href' => $baseUrl . 'api/qr-api/qr.php', 'icon' => 'ti-qrcode'],
            ['label' => 'Download', 'href' => $baseUrl . 'download/download.php', 'icon' => 'ti-download'],
            ['label' => 'Git pull', 'postHref' => $baseUrl . 'tools/git-pull.php', 'icon' => 'ti-git-pull-request'],
        ],
    ],
];

$visibleModules = array_values(array_filter($modules, static function (array $module) use ($authUser): bool {
    if ($authUser === null) {
        return !empty($module['public']);
    }

    return !isset($module['roles']) || in_array($authUser['role'], $module['roles'], true);
}));

$stats = [
    ['label' => 'Brailleleesregel', 'value' => 'USB of Bluetooth', 'icon' => 'ti-device-desktop'],
    ['label' => 'Runtime', 'value' => 'Browser en BrailleBridge', 'icon' => 'ti-browser'],
    ['label' => 'Didactiek', 'value' => 'Learning Through Play', 'icon' => 'ti-bulb'],
];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="nl">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/braillestudio/apple-touch-icon.png">
  <link rel="manifest" href="/braillestudio/site.webmanifest">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">

    <title>BrailleStudio</title>

    <link rel="icon" href="<?= e($baseUrl) ?>favicon.ico" sizes="any">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>tabler/core/dist/css/tabler.min.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>tabler/icons-webfont/dist/tabler-icons.min.css">

    <script defer src="https://cloud.umami.is/script.js" data-website-id="e5e6688f-dec8-44f0-8b83-b223bda340af"></script>

    <style>
        @font-face {
            font-family: "Noto Sans";
            src: url("<?= e($baseUrl) ?>fonts/NotoSans-Regular.woff2") format("woff2");
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: "Noto Sans";
            src: url("<?= e($baseUrl) ?>fonts/NotoSans-SemiBold.woff2") format("woff2");
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --tblr-font-sans-serif: "Noto Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .site-logo {
            width: auto;
            max-width: 10rem;
            height: 2.5rem;
            object-fit: contain;
        }

        .hero-panel {
            border: 1px solid var(--tblr-border-color);
            background:
                linear-gradient(180deg, rgba(var(--tblr-primary-rgb), .06), rgba(var(--tblr-primary-rgb), 0)),
                var(--tblr-bg-surface);
        }

        .module-icon,
        .stat-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .module-icon {
            width: 3rem;
            height: 3rem;
            font-size: 1.5rem;
        }

        .stat-icon {
            width: 2.25rem;
            height: 2.25rem;
            font-size: 1.15rem;
        }

        .module-card .list-group-item {
            min-height: 3.25rem;
        }

        .doc-content {
            max-width: 72ch;
            line-height: 1.7;
        }

        .doc-toolbar {
            max-width: 72ch;
        }

        .doc-content h1,
        .doc-content h2,
        .doc-content h3 {
            margin-top: 1.5rem;
            margin-bottom: .75rem;
            font-weight: 600;
        }

        .doc-content h1:first-child,
        .doc-content h2:first-child,
        .doc-content h3:first-child {
            margin-top: 0;
        }

        .doc-content code {
            color: var(--tblr-code-color);
            background: var(--tblr-bg-surface-secondary);
            border-radius: var(--tblr-border-radius);
            padding: .125rem .375rem;
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
<a class="visually-hidden-focusable" href="#main-content">Naar hoofdinhoud</a>

<div id="indexLoadingScreen" class="page page-center">
    <div class="container-tight py-4">
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="mb-3">
                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                </div>
                <h1 class="h2 mb-2">BrailleStudio laden</h1>
                <p id="indexLoadingMessage" class="text-secondary mb-0">De leeromgeving wordt voorbereid.</p>
            </div>
        </div>
    </div>
</div>

<div id="indexAppPage" class="page d-none" hidden>
    <header class="navbar navbar-expand-md d-print-none">
        <div class="container-xl">
            <div class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
        <img src="style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
            </div>

            <div class="navbar-nav flex-row align-items-center order-md-last ms-auto">
                <?php if ($authUser !== null): ?>
                    <div class="nav-item me-2">
                        <span class="navbar-text text-secondary">
                            Ingelogd als <?= e($authUser['display']) ?> (<?= e($authUser['role']) ?>)
                        </span>
                    </div>
                    <?php if ($authUser['role'] === 'admin'): ?>
                        <div class="nav-item me-2">
                            <a class="btn btn-outline-secondary" href="<?= e($baseUrl) ?>users.php">
                                <i class="ti ti-users me-2" aria-hidden="true"></i>
                                Gebruikers
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="nav-item">
                        <form method="post" action="<?= e($baseUrl) ?>authentication.php" class="mb-0">
                            <input type="hidden" name="csrf" value="<?= e(bs_auth_csrf_token()) ?>">
                            <input type="hidden" name="action" value="logout">
                            <input type="hidden" name="returnTo" value="<?= e($baseUrl) ?>index.php">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="ti ti-logout me-2" aria-hidden="true"></i>
                                Uitloggen
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="nav-item">
                        <a class="btn btn-primary" href="<?= e($loginHref) ?>">
                            <i class="ti ti-login me-2" aria-hidden="true"></i>
                            Inloggen
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="page-wrapper" id="main-content">
        <div class="page-body">
            <div class="container-xl">
                <section class="card hero-panel mb-4">
                    <div class="card-body p-4 p-lg-5">
                        <div class="row g-4 align-items-center">
                            <div class="col-lg-8">
                                <h2 class="h1 mb-3">Braille oefenen en lessen bouwen in een rustige werkomgeving.</h2>
                                <p class="text-secondary mb-4">
                                    BrailleStudio ondersteunt leerlingen, docenten en specialisten met korte activiteiten,
                                    directe feedback, sessiebeheer en koppeling met brailleleesregels via BrailleBridge.
                                </p>
                                <?php if ($authUser !== null): ?>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-sm-8 col-md-6">
                                            <label class="form-label" for="studentCodeInput">Leerlingcode</label>
                                            <input
                                                id="studentCodeInput"
                                                class="form-control"
                                                type="text"
                                                value=""
                                                autocomplete="off"
                                                placeholder="Voer je leerlingcode in"
                                            >
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-lg-4">
                                <div class="row g-3">
                                    <?php foreach ($stats as $stat): ?>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center">
                                                <span class="stat-icon rounded bg-primary-lt text-primary me-3">
                                                    <i class="ti <?= e($stat['icon']) ?>" aria-hidden="true"></i>
                                                </span>
                                                <div>
                                                    <div class="text-secondary small"><?= e($stat['label']) ?></div>
                                                    <div class="fw-semibold"><?= e($stat['value']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="modules">
                    <div class="row row-cards">
                        <?php foreach ($visibleModules as $module): ?>
                            <div class="col-12 col-md-6 <?= $authUser === null ? '' : 'col-xl-3' ?>">
                                <article class="card module-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            <span class="module-icon rounded bg-<?= e($module['theme']) ?>-lt text-<?= e($module['theme']) ?> me-3">
                                                <i class="ti <?= e($module['icon']) ?>" aria-hidden="true"></i>
                                            </span>
                                            <div>
                                                <div class="subheader"><?= e($module['eyebrow']) ?></div>
                                                <h3 class="card-title mb-0"><?= e($module['title']) ?></h3>
                                            </div>
                                        </div>
                                        <p class="text-secondary mb-0"><?= e($module['description']) ?></p>
                                    </div>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($module['links'] as $link): ?>
                                            <?php if (isset($link['postHref'])): ?>
                                                <form method="post" action="<?= e($link['postHref']) ?>" class="list-group-item list-group-item-action p-0 m-0">
                                                    <input type="hidden" name="csrf" value="<?= e(bs_auth_csrf_token()) ?>">
                                                    <button class="btn w-100 border-0 rounded-0 d-flex align-items-center justify-content-start px-3 py-3 text-start" type="submit">
                                                        <i class="ti <?= e($link['icon']) ?> me-3 text-secondary" aria-hidden="true"></i>
                                                        <span class="fw-medium"><?= e($link['label']) ?></span>
                                                        <i class="ti ti-refresh ms-auto text-secondary" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <a class="list-group-item list-group-item-action d-flex align-items-center" href="<?= e($link['href']) ?>">
                                                    <i class="ti <?= e($link['icon']) ?> me-3 text-secondary" aria-hidden="true"></i>
                                                    <span class="fw-medium"><?= e($link['label']) ?></span>
                                                    <i class="ti ti-chevron-right ms-auto text-secondary" aria-hidden="true"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </section>

                <section class="mt-4" id="documentatie" aria-labelledby="documentatie-title">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title" id="documentatie-title">Documentatie</h2>
                                <div class="card-subtitle">Snel overzicht voor gebruik en achtergrond.</div>
                            </div>
                            <button
                                class="btn btn-icon btn-outline-secondary ms-auto"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#documentatie-collapse"
                                aria-expanded="false"
                                aria-controls="documentatie-collapse"
                                aria-label="Documentatie uitklappen"
                            >
                                <i class="ti ti-chevron-down" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="collapse" id="documentatie-collapse">
                            <div class="card-body">
                                <ul class="nav nav-tabs mb-3" data-bs-toggle="tabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link active" href="#tab-starten" data-bs-toggle="tab" aria-selected="true" role="tab">
                                            <i class="ti ti-rocket me-2" aria-hidden="true"></i>
                                            Starten
                                        </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link" href="#tab-expertise" data-bs-toggle="tab" aria-selected="false" tabindex="-1" role="tab">
                                            <i class="ti ti-school me-2" aria-hidden="true"></i>
                                            Expertise
                                        </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link" href="#tab-handleiding" data-bs-toggle="tab" aria-selected="false" tabindex="-1" role="tab">
                                            <i class="ti ti-chalkboard me-2" aria-hidden="true"></i>
                                            Handleiding
                                        </a>
                                    </li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane active show" id="tab-starten" role="tabpanel">
                                        <div class="doc-content" data-markdown-source="<?= e($baseUrl) ?>content/README.nl.md">
                                            <div class="placeholder-glow">
                                                <span class="placeholder col-9"></span>
                                                <span class="placeholder col-12"></span>
                                                <span class="placeholder col-10"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane" id="tab-expertise" role="tabpanel">
                                        <div class="doc-content" data-markdown-source="/braillestudio-data/assets/tastenbraille.md">
                                            <div class="placeholder-glow">
                                                <span class="placeholder col-8"></span>
                                                <span class="placeholder col-12"></span>
                                                <span class="placeholder col-7"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane" id="tab-handleiding" role="tabpanel">
                                        <img
                                            class="img-fluid d-block mx-auto"
                                            src="<?= e($baseUrl) ?>style/kaart.png"
                                            alt="Handleiding"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <footer class="footer footer-transparent d-print-none">
        <div class="container-xl">
            <div class="row text-center align-items-center flex-row-reverse">
                <div class="col-lg-auto ms-lg-auto">
                    <img class="site-logo" src="https://www.tastenbraille.com/braillestudio-data/assets/bartimeus.png" alt="Bartimeus">
                </div>
                <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                    <span class="text-secondary">Powered by Bartimeus en de Braille Expertise Groep</span>
                </div>
            </div>
        </div>
    </footer>
</div>

<script src="<?= e($baseUrl) ?>tabler/core/dist/js/tabler.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
    const indexLoadingScreen = document.getElementById('indexLoadingScreen');
    const indexLoadingMessage = document.getElementById('indexLoadingMessage');
    const indexAppPage = document.getElementById('indexAppPage');
    const studentCodeInput = document.getElementById('studentCodeInput');
    const globalStudentCodeStorageKey = 'braillestudio_global_student_code';

    sessionStorage.removeItem(globalStudentCodeStorageKey);
    studentCodeInput?.addEventListener('input', () => {
        const studentCode = studentCodeInput.value.trim();
        if (studentCode) {
            sessionStorage.setItem(globalStudentCodeStorageKey, studentCode);
        } else {
            sessionStorage.removeItem(globalStudentCodeStorageKey);
        }
    });

    function setIndexLoadingMessage(message) {
        if (indexLoadingMessage) {
            indexLoadingMessage.textContent = message;
        }
    }

    function showIndexPage() {
        if (indexLoadingScreen) {
            indexLoadingScreen.hidden = true;
            indexLoadingScreen.classList.add('d-none');
        }
        if (indexAppPage) {
            indexAppPage.hidden = false;
            indexAppPage.classList.remove('d-none');
        }
    }

    async function loadMarkdownBlock(target) {
        if (!target || target.dataset.markdownLoaded === 'true') {
            return;
        }

        try {
            setIndexLoadingMessage('Documentatie laden.');
            const response = await fetch(target.dataset.markdownSource);

            if (!response.ok) {
                throw new Error(`Markdown request failed: ${response.status}`);
            }

            target.innerHTML = marked.parse(await response.text());
            target.dataset.markdownLoaded = 'true';
        } catch (error) {
            console.error(error);
            target.innerHTML = `
                <div class="alert alert-danger mb-0" role="alert">
                    <div class="d-flex">
                        <div>
                            <i class="ti ti-alert-circle alert-icon" aria-hidden="true"></i>
                        </div>
                        <div>Documentatie kon niet worden geladen.</div>
                    </div>
                </div>
            `;
        }
    }

    (async () => {
        setIndexLoadingMessage('Pagina voorbereiden.');
        const documentationCollapse = document.getElementById('documentatie-collapse');
        const loadActiveDocumentation = () => {
            const activeMarkdownTarget = document.querySelector('.tab-pane.active [data-markdown-source]');
            return loadMarkdownBlock(activeMarkdownTarget);
        };

        await loadActiveDocumentation();

        if (documentationCollapse) {
            documentationCollapse.addEventListener('shown.bs.collapse', () => {
                loadActiveDocumentation();
            });
        }

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => {
                const selector = tab.getAttribute('href');
                if (!selector) {
                    return;
                }
                const pane = document.querySelector(selector);
                loadMarkdownBlock(pane?.querySelector('[data-markdown-source]'));
            });
        });

        setIndexLoadingMessage('Alles staat klaar.');
        showIndexPage();
    })();
</script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
