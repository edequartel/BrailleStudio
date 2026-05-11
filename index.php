<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>BrailleStudio Education</title>

<link rel="icon" href="./favicon.ico" sizes="any" />

<link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet"/>

<script defer
        src="https://cloud.umami.is/script.js"
        data-website-id="e5e6688f-dec8-44f0-8b83-b223bda340af"></script>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
@font-face {
    font-family: "Noto Sans";
    src: url("./assets/fonts/NotoSans-Regular.woff2") format("woff2");
    font-weight: 400;
}

@font-face {
    font-family: "Noto Sans";
    src: url("./assets/fonts/NotoSans-SemiBold.woff2") format("woff2");
    font-weight: 600;
}

:root {
    --tblr-font-sans-serif: "Noto Sans", sans-serif;
}

body {
    background:
        radial-gradient(circle at top left, rgba(99,102,241,.10), transparent 35%),
        radial-gradient(circle at bottom right, rgba(34,197,94,.10), transparent 35%),
        #f5f7fb;
}

/* -------------------------------------------------- */
/* HERO */
/* -------------------------------------------------- */

.hero-card {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    min-height: 320px;
    border: none;
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    color: white;
}

.hero-bg {
    position: absolute;
    inset: 0;
    background:
        linear-gradient(rgba(0,0,0,.30), rgba(0,0,0,.30)),
        url('./assets/start.jpeg') center/cover;
}

.hero-content {
    position: relative;
    z-index: 2;
    padding: 3rem;
}

.hero-title {
    font-size: 4rem;
    font-weight: 700;
    letter-spacing: -.04em;
    margin-bottom: 1rem;
}

.hero-text {
    max-width: 700px;
    font-size: 1.15rem;
    line-height: 1.7;
    opacity: .95;
}

.hero-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 2rem;
}

/* -------------------------------------------------- */
/* EDUCATION CARDS */
/* -------------------------------------------------- */

.edu-card {
    transition: all .18s ease;
    border: none;
    border-radius: 22px;
    overflow: hidden;
    height: 100%;
}

.edu-card:hover {
    transform: translateY(-3px);
}

.edu-header {
    padding: 1.2rem 1.4rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: white;
}

.edu-body {
    padding: 1.4rem;
}

.edu-body p {
    color: var(--tblr-muted);
    min-height: 70px;
}

.bg-practice {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
}

.bg-builder {
    background: linear-gradient(135deg, #15803d, #22c55e);
}

.bg-language {
    background: linear-gradient(135deg, #b45309, #f59e0b);
}

.bg-tools {
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
}

/* -------------------------------------------------- */
/* README */
/* -------------------------------------------------- */

.readme-card {
    border-radius: 24px;
    border: none;
}

#readme-box {
    line-height: 1.8;
    font-size: 1rem;
}

#readme-box h1,
#readme-box h2,
#readme-box h3 {
    margin-top: 1.6rem;
    margin-bottom: .8rem;
    font-weight: 700;
}

#readme-box code {
    background: rgba(0,0,0,.06);
    padding: 2px 6px;
    border-radius: 6px;
}

/* -------------------------------------------------- */
/* FOOTER */
/* -------------------------------------------------- */

.footer-logo {
    width: 140px;
}

@media (max-width: 768px) {

    .hero-title {
        font-size: 2.6rem;
    }

    .hero-content {
        padding: 2rem;
    }
}
</style>

</head>

<body>

<div class="page">

<div class="container-xl py-4">

    <!-- HERO -->

    <div class="card hero-card mb-4">

        <div class="hero-bg"></div>

        <div class="hero-content">

            <div class="badge bg-white text-primary mb-3">
                Inclusive Education Platform
            </div>

            <h1 class="hero-title">
                BrailleStudio
            </h1>

            <div class="hero-text">
                Interactive braille education environment for students,
                teachers and specialists. Create lessons, practice tactile
                reading skills, build learning sessions and connect braille
                displays in one accessible platform.
            </div>

            <div class="hero-buttons">

                <a href="./authentication.html"
                   class="btn btn-light btn-lg">
                    Log in
                </a>

                <a href="https://www.tastenbraille.com/braillestudio/blockly/index.html"
                   class="btn btn-outline-light btn-lg">
                    Open Blockly
                </a>

            </div>

        </div>

    </div>

    <!-- EDUCATION MODULES -->

    <div class="row g-4 mb-4">

        <!-- PRACTICE -->

        <div class="col-12 col-md-6 col-xl-3">

            <div class="card edu-card shadow-sm">

                <div class="edu-header bg-practice">
                    Practice & Reading
                </div>

                <div class="edu-body">

                    <p>
                        Practice braille reading, words, sounds
                        and tactile recognition exercises.
                    </p>

                    <div class="d-grid gap-2">

                        <a class="btn btn-primary"
                           href="https://www.tastenbraille.com/braillestudio/runmethod.php?id=mpop-1775631274214">
                            MPOP
                        </a>

                        <a class="btn btn-outline-primary"
                           href="https://www.tastenbraille.com/braillestudio/session-api/laptop.html">
                            Braille Session
                        </a>

                    </div>

                </div>

            </div>

        </div>

        <!-- BUILDERS -->

        <div class="col-12 col-md-6 col-xl-3">

            <div class="card edu-card shadow-sm">

                <div class="edu-header bg-builder">
                    Lesson Creation
                </div>

                <div class="edu-body">

                    <p>
                        Build interactive lessons, workflows
                        and educational braille activities.
                    </p>

                    <div class="d-grid gap-2">

                        <a class="btn btn-success"
                           href="https://www.tastenbraille.com/braillestudio/blockly/index.html">
                            Blockly
                        </a>

                        <a class="btn btn-outline-success"
                           href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder.php">
                            Lesson Builder
                        </a>

                        <a class="btn btn-outline-success"
                           href="https://www.tastenbraille.com/braillestudio/session-api/admin.html">
                            Session Builder
                        </a>

                    </div>

                </div>

            </div>

        </div>

        <!-- LANGUAGE -->

        <div class="col-12 col-md-6 col-xl-3">

            <div class="card edu-card shadow-sm">

                <div class="edu-header bg-language">
                    Language & Sounds
                </div>

                <div class="edu-body">

                    <p>
                        Explore phonemes, sounds and language
                        development tools for braille literacy.
                    </p>

                    <div class="d-grid gap-2">

                        <a class="btn btn-warning"
                           href="https://www.tastenbraille.com/braillestudio/phonemes-api/index.html">
                            Phonemes API
                        </a>

                        <a class="btn btn-outline-warning"
                           href="https://www.tastenbraille.com/braillestudio/klanken/index.html">
                            Klanken
                        </a>

                    </div>

                </div>

            </div>

        </div>

        <!-- TOOLS -->

        <div class="col-12 col-md-6 col-xl-3">

            <div class="card edu-card shadow-sm">

                <div class="edu-header bg-tools">
                    Braille Tools
                </div>

                <div class="edu-body">

                    <p>
                        Connect braille displays and access
                        specialist tools and utilities.
                    </p>

                    <div class="d-grid gap-2">

                        <a class="btn btn-purple"
                           href="https://www.tastenbraille.com/braillestudio/tools/braillebridge-com.html">
                            BrailleBridge
                        </a>

                        <a class="btn btn-outline-secondary"
                           href="https://www.tastenbraille.com/braillestudio/tools/tables.html">
                            Tables
                        </a>

                        <a class="btn btn-outline-secondary"
                           href="https://www.tastenbraille.com/braillestudio/downloads/setup_braillebridge_v20.exe">
                            Download
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- README -->

    <div class="card shadow-sm readme-card">

        <div class="card-header">

            <h2 class="card-title">
                Documentation
            </h2>

        </div>

        <div class="card-body">

            <div id="readme-box">
                Loading documentation…
            </div>

        </div>

    </div>

    <!-- FOOTER -->

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center py-5 gap-3">

        <div class="text-secondary">
            Powered by Bartiméus • Braille Expertise Group
        </div>

        <img src="./assets/bartimeus.png"
             alt="Bartiméus"
             class="footer-logo">

    </div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>

<script>
(async function () {

    const box = document.getElementById("readme-box");

    try {

        const response = await fetch("./content/README.nl.md", {
            cache: "no-store"
        });

        if (!response.ok) {
            throw new Error("README failed");
        }

        const markdown = await response.text();

        box.innerHTML = marked.parse(markdown);

    } catch (error) {

        console.error(error);

        box.innerHTML = `
            <div class="alert alert-danger">
                Documentation could not be loaded.
            </div>
        `;
    }

})();
</script>

</body>
</html></body>
</html>