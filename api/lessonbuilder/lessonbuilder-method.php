<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lesson Builder - Methode</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="./lessonbuilder-shared.js"></script>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="max-w-6xl mx-auto p-6 space-y-5">
    <div class="flex items-center justify-between gap-4">
      <div>
        <div class="text-sm font-semibold text-blue-700">Stap 1 van 3</div>
        <h1 class="text-3xl font-bold">Methode</h1>
      </div>
      <div class="flex gap-2">
        <a class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder.php">Overzicht</a>
        <a class="rounded-xl border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-records.php">Volgende stap</a>
      </div>
    </div>

    <div class="space-y-5">
      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div>
          <div class="text-lg font-bold">Methode</div>
          <p class="text-sm text-slate-600">Kies een bestaande methode of maak een nieuwe. Selecteer daarna het basisbestand waar de basisrecords uit komen.</p>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="methodsSelect">Method list</label>
            <select id="methodsSelect" class="h-10 w-full rounded-xl border border-slate-300 px-3 py-2"></select>
          </div>
          <input id="methodIdInput" type="hidden">
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="methodTitleInput">Method title</label>
            <input id="methodTitleInput" class="w-full rounded-xl border border-slate-300 px-3 py-2" type="text" placeholder="Aanvankelijk">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="methodBasisFileSelect">Basisbestand</label>
            <select id="methodBasisFileSelect" class="h-10 w-full rounded-xl border border-slate-300 px-3 py-2"></select>
          </div>
        </div>

        <input id="methodDataSourceInput" type="hidden">

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1" for="methodDescriptionInput">Description</label>
          <input id="methodDescriptionInput" class="w-full rounded-xl border border-slate-300 px-3 py-2" type="text" placeholder="Woordenlijst voor aanvankelijk lezen">
        </div>

        <div class="flex flex-wrap gap-2">
          <button id="saveMethodBtn" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save method</button>
          <button id="deleteMethodBtn" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white">Delete method</button>
          <button id="refreshMethodsBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Refresh methods</button>
        </div>
      </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
      <div class="text-lg font-bold">Debug log</div>
      <pre id="statusBox" class="min-h-[180px] rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap"></pre>
    </section>
  </div>

  <script>
    const shared = window.LessonBuilderShared;
    const methodsSelect = document.getElementById('methodsSelect');
    const methodIdInput = document.getElementById('methodIdInput');
    const methodTitleInput = document.getElementById('methodTitleInput');
    const methodBasisFileSelect = document.getElementById('methodBasisFileSelect');
    const methodDataSourceInput = document.getElementById('methodDataSourceInput');
    const methodDescriptionInput = document.getElementById('methodDescriptionInput');
    const statusBox = document.getElementById('statusBox');

    let methodsCache = [];
    let basisFileOptions = [];
    let basisItems = [];
    const METHODS_SAVE_ENDPOINT = 'https://www.tastenbraille.com/braillestudio/methods-api/save_method.php';
    const METHODS_LIST_ENDPOINT = 'https://www.tastenbraille.com/braillestudio/methods-api/list_methods.php';
    const METHODS_DELETE_ENDPOINT = 'https://www.tastenbraille.com/braillestudio/methods-api/delete_method.php';
    const METHODS_STORAGE_DIR = '/braillestudio/api/methods-data/';

    function setStatus(message, data = null) {
      statusBox.textContent = data ? `${message}\n\n${JSON.stringify(data, null, 2)}` : message;
    }

    function appendStatus(message, data = null) {
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      const block = data
        ? `[${timestamp}] ${message}\n${JSON.stringify(data, null, 2)}`
        : `[${timestamp}] ${message}`;
      statusBox.textContent = statusBox.textContent ? `${statusBox.textContent}\n\n${block}` : block;
      statusBox.scrollTop = statusBox.scrollHeight;
    }

    function slugifyMethodIdPart(value) {
      return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/\.json$/i, '')
        .replace(/[^a-z0-9_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
    }

    function buildGeneratedMethodId() {
      const titlePart = slugifyMethodIdPart(methodTitleInput.value);
      const uniqueNumber = Date.now();
      const candidate = [titlePart || 'methode', String(uniqueNumber)].filter(Boolean).join('-');
      return candidate;
    }

    function buildUniqueMethodId() {
      const baseId = buildGeneratedMethodId();
      const existingIds = new Set(methodsCache.map((item) => String(item.id || '').trim()).filter(Boolean));
      if (!existingIds.has(baseId)) return baseId;
      let counter = 2;
      while (existingIds.has(`${baseId}-${counter}`)) {
        counter += 1;
      }
      return `${baseId}-${counter}`;
    }

    function resetMethodForm() {
      methodsSelect.value = '';
      methodIdInput.value = '';
      methodTitleInput.value = '';
      methodDescriptionInput.value = '';
      renderBasisFileOptions(basisFileOptions[0]?.name || 'aanvankelijklijst.json');
      methodDataSourceInput.value = methodBasisFileSelect.value
        ? shared.resolveBasisFileUrl(methodBasisFileSelect.value)
        : shared.DEFAULT_BASIS_DATA_URL;
      shared.updateState({
        methodId: '',
        methodTitle: '',
        methodDescription: '',
        methodBasisFile: methodBasisFileSelect.value || '',
        methodDataSource: methodDataSourceInput.value,
        basisIndex: 0
      });
    }

    function renderMethodOptions(items) {
      methodsSelect.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = '-- Select method --';
      methodsSelect.appendChild(placeholder);
      items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = `${item.id} - ${item.title || item.id}`;
        methodsSelect.appendChild(option);
      });
    }

    function renderBasisFileOptions(selected = '') {
      methodBasisFileSelect.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = '-- Select basis file --';
      methodBasisFileSelect.appendChild(placeholder);
      basisFileOptions.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.name;
        option.textContent = item.label;
        methodBasisFileSelect.appendChild(option);
      });
      methodBasisFileSelect.value = selected || basisFileOptions[0]?.name || '';
    }

    function getDraftMethod() {
      const state = shared.loadState();
      return {
        id: methodIdInput.value.trim() || state.methodId || '',
        title: methodTitleInput.value.trim() || state.methodTitle || '',
        basisFile: methodBasisFileSelect.value.trim() || state.methodBasisFile || '',
        dataSource: methodDataSourceInput.value.trim() || state.methodDataSource || shared.DEFAULT_BASIS_DATA_URL,
        description: methodDescriptionInput.value.trim() || state.methodDescription || ''
      };
    }

    async function loadBasisPreview() {
      const basisFile = methodBasisFileSelect.value.trim();
      const dataSource = basisFile ? shared.resolveBasisFileUrl(basisFile) : shared.DEFAULT_BASIS_DATA_URL;
      methodDataSourceInput.value = dataSource;
      appendStatus('Basisrecords laden gestart.', {
        basisFile: basisFile || 'aanvankelijklijst.json',
        dataSource
      });
      basisItems = await shared.loadBasisData(dataSource);
      appendStatus('Basisrecords geladen.', {
        basisCount: basisItems.length,
        firstWord: basisItems[0]?.word || null
      });
      shared.updateState({
        methodId: methodIdInput.value.trim(),
        methodTitle: methodTitleInput.value.trim(),
        methodBasisFile: basisFile,
        methodDataSource: dataSource,
        methodDescription: methodDescriptionInput.value.trim(),
        basisIndex: basisItems.length ? 0 : -1
      });
    }

    async function loadMethodIntoForm(id) {
      appendStatus('Methode laden gestart.', {
        methodId: id,
        endpoint: `https://www.tastenbraille.com/braillestudio/methods-api/load_method.php?id=${encodeURIComponent(id)}`
      });
      const item = await shared.loadMethod(id);
      if (!item) throw new Error('Method not found');
      methodIdInput.value = item.id || '';
      methodTitleInput.value = item.title || '';
      methodDescriptionInput.value = item.description || '';
      renderBasisFileOptions(String(item.basisFile || '').trim());
      methodDataSourceInput.value = shared.resolveMethodDataSource(item.dataSource || '', item.id || '', item.basisFile || '');
      methodsSelect.value = item.id || '';
      shared.updateState({
        methodId: item.id || '',
        methodTitle: item.title || '',
        methodBasisFile: item.basisFile || '',
        methodDataSource: methodDataSourceInput.value,
        methodDescription: item.description || ''
      });
      await loadBasisPreview();
      setStatus(`Method loaded: ${id}`, item);
    }

    async function init() {
      renderBasisFileOptions([]);
      try {
        basisFileOptions = await shared.listBasisFiles();
        renderBasisFileOptions(shared.loadState().methodBasisFile || basisFileOptions[0]?.name || 'aanvankelijklijst.json');
        methodDataSourceInput.value = methodBasisFileSelect.value ? shared.resolveBasisFileUrl(methodBasisFileSelect.value) : shared.DEFAULT_BASIS_DATA_URL;

        methodsCache = await shared.listMethods();
        renderMethodOptions(methodsCache);

        const state = shared.loadState();
        methodIdInput.value = state.methodId || '';
        methodTitleInput.value = state.methodTitle || '';
        methodDescriptionInput.value = state.methodDescription || '';
        if (state.methodId) {
          await loadMethodIntoForm(state.methodId);
        } else {
          await loadBasisPreview();
          setStatus('Ready.');
        }
      } catch (err) {
        setStatus(`Init error: ${err.message}`);
      }
    }

    methodBasisFileSelect.addEventListener('change', async () => {
      try {
        await loadBasisPreview();
        setStatus(`Basisbestand gekozen: ${methodBasisFileSelect.value}`);
      } catch (err) {
        setStatus(`Basisbestand laden mislukt: ${err.message}`);
      }
    });

    methodsSelect.addEventListener('change', async () => {
      if (!methodsSelect.value) {
        resetMethodForm();
        try {
          await loadBasisPreview();
          setStatus('Nieuwe methode: formulier gereset.');
        } catch (err) {
          setStatus(`Reset error: ${err.message}`);
        }
        return;
      }
      try {
        await loadMethodIntoForm(methodsSelect.value);
      } catch (err) {
        setStatus(`Method load error: ${err.message}`);
      }
    });

    [methodIdInput, methodTitleInput, methodDescriptionInput].forEach((input) => {
      input.addEventListener('input', () => {
        shared.updateState({
          methodId: methodIdInput.value.trim(),
          methodTitle: methodTitleInput.value.trim(),
          methodDescription: methodDescriptionInput.value.trim()
        });
      });
    });

    document.getElementById('saveMethodBtn').addEventListener('click', async () => {
      const basisFile = methodBasisFileSelect.value.trim();
      const generatedId = !methodIdInput.value.trim() ? buildUniqueMethodId() : '';
      if (generatedId) {
        methodIdInput.value = generatedId;
        appendStatus('Method ID automatisch gegenereerd.', {
          generatedId,
          basedOn: {
            title: methodTitleInput.value.trim(),
            basisFile
          }
        });
      }
      const payload = {
        id: methodIdInput.value.trim(),
        title: methodTitleInput.value.trim(),
        description: methodDescriptionInput.value.trim(),
        basisFile,
        dataSource: basisFile ? shared.resolveBasisFileUrl(basisFile) : methodDataSourceInput.value.trim(),
        status: 'active'
      };
      appendStatus('Methode opslaan gestart.', {
        endpoint: METHODS_SAVE_ENDPOINT,
        refreshEndpoint: METHODS_LIST_ENDPOINT,
        expectedStorageFile: payload.id ? `${METHODS_STORAGE_DIR}${payload.id}.json` : null,
        payload
      });
      if (!payload.title || !payload.basisFile) {
        appendStatus('Validatie in browser mislukt.', {
          missing: {
            title: !payload.title,
            basisFile: !payload.basisFile
          },
          payload
        });
        setStatus('Method title en basisbestand zijn verplicht.');
        return;
      }
      try {
        const result = await shared.saveMethod(payload);
        appendStatus('Response van save_method.php ontvangen.', result);
        methodsCache = await shared.listMethods();
        appendStatus('Method list opnieuw geladen.', {
          endpoint: METHODS_LIST_ENDPOINT,
          methodsCount: methodsCache.length,
          containsSavedMethod: methodsCache.some((item) => item.id === payload.id)
        });
        renderMethodOptions(methodsCache);
        methodsSelect.value = payload.id;
        shared.updateState({
          methodId: payload.id,
          methodTitle: payload.title,
          methodBasisFile: payload.basisFile,
          methodDataSource: payload.dataSource,
          methodDescription: payload.description
        });
        setStatus(`Method saved: ${payload.id}`, {
          result,
          expectedStorageFile: `${METHODS_STORAGE_DIR}${payload.id}.json`
        });
      } catch (err) {
        appendStatus('Opslaan mislukt.', {
          error: err.message,
          endpoint: METHODS_SAVE_ENDPOINT,
          payload
        });
        setStatus(`Save error: ${err.message}`);
      }
    });

    document.getElementById('deleteMethodBtn').addEventListener('click', async () => {
      const id = methodsSelect.value || methodIdInput.value.trim();
      if (!id) {
        setStatus('Kies eerst een methode.');
        return;
      }
      appendStatus('Methode verwijderen gestart.', {
        methodId: id,
        endpoint: METHODS_DELETE_ENDPOINT,
        expectedStorageFile: `${METHODS_STORAGE_DIR}${id}.json`
      });
      try {
        const result = await shared.deleteMethod(id);
        methodsCache = await shared.listMethods();
        renderMethodOptions(methodsCache);
        methodsSelect.value = '';
        methodIdInput.value = '';
        methodTitleInput.value = '';
        methodDescriptionInput.value = '';
        shared.updateState({ methodId: '', methodTitle: '', methodDescription: '' });
        setStatus(`Method deleted: ${id}`, result);
      } catch (err) {
        appendStatus('Verwijderen mislukt.', {
          error: err.message,
          methodId: id,
          endpoint: METHODS_DELETE_ENDPOINT
        });
        setStatus(`Delete error: ${err.message}`);
      }
    });

    document.getElementById('refreshMethodsBtn').addEventListener('click', async () => {
      try {
        appendStatus('Method list refresh gestart.', {
          endpoint: METHODS_LIST_ENDPOINT
        });
        methodsCache = await shared.listMethods();
        renderMethodOptions(methodsCache);
        setStatus(`Loaded ${methodsCache.length} method(s).`, {
          endpoint: METHODS_LIST_ENDPOINT,
          methodsCount: methodsCache.length
        });
      } catch (err) {
        setStatus(`Refresh error: ${err.message}`);
      }
    });

    window.addEventListener('load', init);
  </script>
</body>
</html>
