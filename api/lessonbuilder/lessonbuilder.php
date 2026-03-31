<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lesson Builder Flow</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-[linear-gradient(135deg,#0f172a_0%,#1d4ed8_58%,#60a5fa_100%)] p-6 text-white shadow-sm">
      <h1 class="text-3xl font-bold tracking-tight">BrailleStudio Lesson Builder</h1>
      <p class="mt-2 text-sm text-blue-100">Werk in drie aparte stappen. Elke pagina heeft een eigen debuglog zodat je sneller ziet waar iets misgaat.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <a class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-method.php">
        <div class="text-sm font-semibold text-blue-700">Stap 1</div>
        <div class="mt-2 text-xl font-bold">Methode</div>
        <p class="mt-2 text-sm text-slate-600">Kies of maak een methode en selecteer het basisbestand.</p>
      </a>
      <a class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-records.php">
        <div class="text-sm font-semibold text-blue-700">Stap 2</div>
        <div class="mt-2 text-xl font-bold">Basisrecords</div>
        <p class="mt-2 text-sm text-slate-600">Kies een basisrecord en beheer de lessons onder dat record.</p>
      </a>
      <a class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-steps.php">
        <div class="text-sm font-semibold text-blue-700">Stap 3</div>
        <div class="mt-2 text-xl font-bold">Steps</div>
        <p class="mt-2 text-sm text-slate-600">Voeg Blockly scripts toe, vul inputs in en run de lesson.</p>
      </a>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="text-lg font-bold">Flow</div>
      <ol class="mt-3 list-decimal pl-5 text-sm text-slate-700 space-y-1">
        <li>Kies een methode en een basisbestand zoals <code>aanvankelijklijst.json</code>.</li>
        <li>Kies een basisrecord zoals <code>bal</code> of <code>kam</code> en open of maak een lesson.</li>
        <li>Bouw de steps van die lesson met gekoppelde Blockly scripts.</li>
      </ol>
    </div>
  </div>
</body>
</html>
