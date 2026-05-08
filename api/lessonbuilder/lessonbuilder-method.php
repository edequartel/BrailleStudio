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
  <script src="./lessonbuilder-shared.js?v=20260407-2"></script>
  <style>
    .press-feedback {
      transition: transform 120ms ease, box-shadow 120ms ease, background-color 120ms ease, border-color 120ms ease;
    }

    .press-feedback:active {
      transform: translateY(1px) scale(0.985);
      box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.18);
      filter: brightness(0.96);
    }
  </style>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="max-w-6xl mx-auto p-6 space-y-5">
    <div class="flex items-center justify-between gap-4">
      <div>
        <div class="text-sm font-semibold text-blue-700">Stap 1 van 3</div>
        <h1 class="text-3xl font-bold">Methode</h1>
      </div>
      <div class="flex gap-2">
        <a class="press-feedback rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder.php">Overzicht</a>
        <a class="press-feedback rounded-xl border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-records.php">Volgende stap</a>
      </div>
    </div>

    <div class="space-y-5">
      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div>
          <div class="text-lg font-bold">Methode</div>
          <p class="text-sm text-slate-600">Kies een bestaande methode of maak een nieuwe. Een basisbestand is optioneel.</p>
        </div>

        <div class="grid gap-6 md:grid-cols-[minmax(0,420px)_auto]">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="methodsSelect">Method list</label>
            <select id="methodsSelect" class="h-10 w-full rounded-xl border border-slate-300 px-3 py-2"></select>
          </div>
          <div class="flex items-end justify-end gap-2">
            <a id="openRunmethodLink" class="press-feedback inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-400 pointer-events-none" href="#" target="_blank" rel="noopener noreferrer" aria-disabled="true">Link</a>
            <button id="copyRunmethodLinkBtn" type="button" class="press-feedback inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-400" disabled>Copy link</button>
          </div>
          <input id="methodIdInput" type="hidden">
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="methodTechnicalIdDisplay">Technical method id</label>
            <input id="methodTechnicalIdDisplay" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-slate-600" type="text" readonly placeholder="Will be generated on save">
            <div class="mt-1 text-xs text-slate-500">This id is used for the file name and runmethod link.</div>
          </div>
          <div></div>
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

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1" for="methodImageUrlInput">Image URL</label>
          <input id="methodImageUrlInput" class="w-full rounded-xl border border-slate-300 px-3 py-2" type="text" placeholder="https://www.tastenbraille.com/braillestudio/assets/bartimeus.png">
          <div id="methodImagePreview" class="mt-2 hidden rounded-xl border border-slate-200 bg-slate-50 p-3"></div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button id="newMethodBtn" class="press-feedback rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">New method</button>
          <button id="saveMethodBtn" class="press-feedback rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save method</button>
          <button id="saveAsNewMethodBtn" class="press-feedback rounded-xl border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700">Save as new</button>
          <button id="deleteMethodBtn" class="press-feedback rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white">Delete method</button>
          <button id="refreshMethodsBtn" class="press-feedback rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Refresh methods</button>
        </div>
      </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
      <div class="flex items-center justify-between gap-3">
        <div class="text-lg font-bold">Debug log</div>
        <button id="toggleDebugLogBtn" type="button" class="press-feedback rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Unhide</button>
      </div>
      <pre id="statusBox" class="hidden min-h-[180px] rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap"></pre>
    </section>
  </div>

  <script>
    const shared = window.LessonBuilderShared;
    const methodsSelect = document.getElementById('methodsSelect');
    const methodIdInput = document.getElementById('methodIdInput');
    const methodTechnicalIdDisplay = document.getElementById('methodTechnicalIdDisplay');
    const methodTitleInput = document.getElementById('methodTitleInput');
    const methodBasisFileSelect = document.getElementById('methodBasisFileSelect');
    const methodDataSourceInput = document.getElementById('methodDataSourceInput');
    const methodDescriptionInput = document.getElementById('methodDescriptionInput');
    const methodImageUrlInput = document.getElementById('methodImageUrlInput');
    const methodImagePreview = document.getElementById('methodImagePreview');
    const openRunmethodLink = document.getElementById('openRunmethodLink');
    const copyRunmethodLinkBtn = document.getElementById('copyRunmethodLinkBtn');
    const statusBox = document.getElementById('statusBox');
    const toggleDebugLogBtn = document.getElementById('toggleDebugLogBtn');
    const newMethodBtn = document.getElementById('newMethodBtn');
    const saveMethodBtn = document.getElementById('saveMethodBtn');
    const saveAsNewMethodBtn = document.getElementById('saveAsNewMethodBtn');
    const authRedirected = Boolean(shared?.requireAuthOnProduction?.());

    let methodsCache = [];
    let basisFileOptions = [];
    let basisItems = [];
    let isDebugLogVisible = false;
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

    function renderDebugLogVisibility() {
      statusBox.classList.toggle('hidden', !isDebugLogVisible);
      toggleDebugLogBtn.textContent = isDebugLogVisible ? 'Hide' : 'Unhide';
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

    function renderMethodImagePreview() {
      const imageUrl = methodImageUrlInput.value.trim();
      if (!imageUrl) {
        methodImagePreview.classList.add('hidden');
        methodImagePreview.innerHTML = '';
        return;
      }
      methodImagePreview.classList.remove('hidden');
      methodImagePreview.innerHTML = `
        <div class="text-xs text-slate-500 break-all mb-2">${imageUrl}</div>
        <img src="${imageUrl}" alt="Method preview" class="max-h-36 rounded-lg border border-slate-200 bg-white object-contain">
      `;
    }

    function renderTechnicalMethodId() {
      if (!methodTechnicalIdDisplay) return;
      methodTechnicalIdDisplay.value = String(methodIdInput.value || '').trim();
    }

    function renderRunmethodLink() {
      if (!openRunmethodLink) return;
      const methodId = String(methodIdInput.value || methodsSelect.value || '').trim();
      if (!methodId) {
        openRunmethodLink.href = '#';
        openRunmethodLink.setAttribute('aria-disabled', 'true');
        openRunmethodLink.className = 'press-feedback rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-400 pointer-events-none';
        if (copyRunmethodLinkBtn) {
          copyRunmethodLinkBtn.disabled = true;
          copyRunmethodLinkBtn.className = 'press-feedback inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-400';
        }
        return;
      }
      openRunmethodLink.href = `https://www.tastenbraille.com/braillestudio/runmethod.php?id=${encodeURIComponent(methodId)}`;
      openRunmethodLink.setAttribute('aria-disabled', 'false');
      openRunmethodLink.className = 'press-feedback rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900';
      if (copyRunmethodLinkBtn) {
        copyRunmethodLinkBtn.disabled = false;
        copyRunmethodLinkBtn.className = 'press-feedback inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900';
      }
    }

    function resetMethodForm() {
      methodsSelect.value = '';
      methodIdInput.value = '';
      renderTechnicalMethodId();
      methodTitleInput.value = '';
      methodDescriptionInput.value = '';
      methodImageUrlInput.value = '';
      renderMethodImagePreview();
      renderRunmethodLink();
      renderBasisFileOptions('');
      methodDataSourceInput.value = '';
      shared.updateState({
        methodId: '',
        methodTitle: '',
        methodDescription: '',
        methodImageUrl: '',
        methodBasisFile: methodBasisFileSelect.value || '',
        methodDataSource: methodDataSourceInput.value,
        basisIndex: -1
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
      methodBasisFileSelect.value = selected || '';
    }

    function getDraftMethod() {
      const state = shared.loadState();
      return {
        id: methodIdInput.value.trim() || state.methodId || '',
        title: methodTitleInput.value.trim() || state.methodTitle || '',
        basisFile: methodBasisFileSelect.value.trim() || state.methodBasisFile || '',
        dataSource: methodDataSourceInput.value.trim() || state.methodDataSource || '',
        description: methodDescriptionInput.value.trim() || state.methodDescription || '',
        imageUrl: methodImageUrlInput.value.trim() || state.methodImageUrl || ''
      };
    }

    function findExistingMethodById(id) {
      const targetId = String(id || '').trim().toLowerCase();
      if (!targetId) return null;
      return methodsCache.find((item) => String(item?.id || '').trim().toLowerCase() === targetId) || null;
    }

    async function loadBasisPreview() {
      const basisFile = methodBasisFileSelect.value.trim();
      const dataSource = basisFile ? shared.resolveBasisFileUrl(basisFile) : '';
      methodDataSourceInput.value = dataSource;
      appendStatus('Basisrecords laden gestart.', {
        basisFile: basisFile || null,
        dataSource
      });
      if (dataSource) {
        basisItems = await shared.loadBasisData(dataSource);
        appendStatus('Basisrecords geladen.', {
          basisCount: basisItems.length,
          firstWord: basisItems[0]?.word || null
        });
      } else {
        basisItems = [];
        appendStatus('Geen basisbestand gekoppeld aan methode.', {
          basisCount: 0
        });
      }
      shared.updateState({
        methodId: methodIdInput.value.trim(),
        methodTitle: methodTitleInput.value.trim(),
        methodBasisFile: basisFile,
        methodDataSource: dataSource,
        methodDescription: methodDescriptionInput.value.trim(),
        methodImageUrl: methodImageUrlInput.value.trim(),
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
      renderTechnicalMethodId();
      methodTitleInput.value = item.title || '';
      methodDescriptionInput.value = item.description || '';
      methodImageUrlInput.value = item.imageUrl || '';
      renderMethodImagePreview();
      renderBasisFileOptions(String(item.basisFile || '').trim());
      methodDataSourceInput.value = shared.resolveMethodDataSource(item.dataSource || '', item.id || '', item.basisFile || '');
      methodsSelect.value = item.id || '';
      renderRunmethodLink();
      shared.updateState({
        methodId: item.id || '',
        methodTitle: item.title || '',
        methodBasisFile: item.basisFile || '',
        methodDataSource: methodDataSourceInput.value,
        methodDescription: item.description || '',
        methodImageUrl: item.imageUrl || ''
      });
      await loadBasisPreview();
      setStatus(`Method loaded: ${id}`, item);
    }

    async function init() {
      renderBasisFileOptions([]);
      try {
        basisFileOptions = await shared.listBasisFiles();
        renderBasisFileOptions(shared.loadState().methodBasisFile || '');
        methodDataSourceInput.value = methodBasisFileSelect.value ? shared.resolveBasisFileUrl(methodBasisFileSelect.value) : '';

        methodsCache = await shared.listMethods();
        renderMethodOptions(methodsCache);

        const state = shared.loadState();
        methodIdInput.value = state.methodId || '';
        renderTechnicalMethodId();
        methodTitleInput.value = state.methodTitle || '';
        methodDescriptionInput.value = state.methodDescription || '';
        methodImageUrlInput.value = state.methodImageUrl || '';
        renderMethodImagePreview();
        if (state.methodId) {
          await loadMethodIntoForm(state.methodId);
        } else {
          renderRunmethodLink();
          await loadBasisPreview();
          setStatus('Ready.');
        }
        renderDebugLogVisibility();
      } catch (err) {
        setStatus(`Init error: ${err.message}`);
        renderDebugLogVisibility();
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

    [methodIdInput, methodTitleInput, methodDescriptionInput, methodImageUrlInput].forEach((input) => {
      input.addEventListener('input', () => {
        renderTechnicalMethodId();
        renderRunmethodLink();
        shared.updateState({
          methodId: methodIdInput.value.trim(),
          methodTitle: methodTitleInput.value.trim(),
          methodDescription: methodDescriptionInput.value.trim(),
          methodImageUrl: methodImageUrlInput.value.trim()
        });
        if (input === methodImageUrlInput) {
          renderMethodImagePreview();
        }
      });
    });

    async function saveMethodWithMode(mode = 'update') {
      const basisFile = methodBasisFileSelect.value.trim();
      const shouldCreateNew = mode === 'create';
      const generatedId = shouldCreateNew
        ? buildUniqueMethodId()
        : (!methodIdInput.value.trim() ? buildUniqueMethodId() : '');
      if (generatedId) {
        methodIdInput.value = generatedId;
        renderTechnicalMethodId();
        appendStatus('Method ID automatisch gegenereerd.', {
          generatedId,
          mode,
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
        imageUrl: methodImageUrlInput.value.trim(),
        basisFile,
        dataSource: basisFile ? shared.resolveBasisFileUrl(basisFile) : methodDataSourceInput.value.trim(),
        status: 'active'
      };
      appendStatus('Methode opslaan gestart.', {
        mode,
        endpoint: METHODS_SAVE_ENDPOINT,
        refreshEndpoint: METHODS_LIST_ENDPOINT,
        expectedStorageFile: payload.id ? `${METHODS_STORAGE_DIR}${payload.id}.json` : null,
        payload
      });
      if (!payload.title) {
        appendStatus('Validatie in browser mislukt.', {
          missing: {
            title: !payload.title
          },
          payload
        });
        setStatus('Method title is verplicht.');
        return;
      }
      const existingMethod = findExistingMethodById(payload.id);
      if (existingMethod) {
        const confirmed = window.confirm(`Method "${payload.id}" exists already. Overwrite it?`);
        if (!confirmed) {
          appendStatus('Opslaan geannuleerd: overwrite niet bevestigd.', {
            mode,
            methodId: payload.id
          });
          setStatus('Save cancelled.');
          return;
        }
      }
      try {
        const result = await shared.saveMethod(payload);
        appendStatus('Response van save_method.php ontvangen.', result);
        methodsCache = await shared.listMethods();
        appendStatus('Method list opnieuw geladen.', {
          mode,
          endpoint: METHODS_LIST_ENDPOINT,
          methodsCount: methodsCache.length,
          containsSavedMethod: methodsCache.some((item) => item.id === payload.id)
        });
        renderMethodOptions(methodsCache);
        methodsSelect.value = payload.id;
        renderTechnicalMethodId();
        renderRunmethodLink();
        shared.updateState({
          methodId: payload.id,
          methodTitle: payload.title,
          methodBasisFile: payload.basisFile,
          methodDataSource: payload.dataSource,
          methodDescription: payload.description,
          methodImageUrl: payload.imageUrl
        });
        setStatus(`Method saved: ${payload.id}`, {
          mode,
          result,
          expectedStorageFile: `${METHODS_STORAGE_DIR}${payload.id}.json`
        });
      } catch (err) {
        appendStatus('Opslaan mislukt.', {
          mode,
          error: err.message,
          endpoint: METHODS_SAVE_ENDPOINT,
          payload
        });
        setStatus(`Save error: ${err.message}`);
      }
    }

    saveMethodBtn.addEventListener('click', async () => {
      await saveMethodWithMode('update');
    });

    saveAsNewMethodBtn.addEventListener('click', async () => {
      await saveMethodWithMode('create');
    });

    newMethodBtn.addEventListener('click', async () => {
      resetMethodForm();
      try {
        await loadBasisPreview();
        setStatus('Nieuwe methode gestart. Vul titel in en klik Save method.');
      } catch (err) {
        setStatus(`New method error: ${err.message}`);
      }
    });

    document.getElementById('deleteMethodBtn').addEventListener('click', async () => {
      const id = methodsSelect.value || methodIdInput.value.trim();
      if (!id) {
        setStatus('Kies eerst een methode.');
        return;
      }
      const confirmed = window.confirm(`Delete method "${id}"?`);
      if (!confirmed) {
        appendStatus('Verwijderen geannuleerd: delete niet bevestigd.', {
          methodId: id
        });
        setStatus('Delete cancelled.');
        return;
      }
      appendStatus('Methode verwijderen gestart.', {
        methodId: id,
        endpoint: METHODS_DELETE_ENDPOINT,
        expectedStorageFile: `${METHODS_STORAGE_DIR}${id}.json`
      });
      try {
        const linkedLessons = await shared.listLessons(id);
        appendStatus('Bijbehorende lessons opgehaald.', {
          methodId: id,
          lessonCount: linkedLessons.length,
          lessonIds: linkedLessons.map((item) => String(item?.id || '').trim()).filter(Boolean)
        });

        const deletedLessonIds = [];
        for (const lesson of linkedLessons) {
          const lessonId = String(lesson?.id || '').trim();
          if (!lessonId) continue;
          await shared.deleteLesson(lessonId);
          deletedLessonIds.push(lessonId);
        }
        appendStatus('Bijbehorende lessons verwijderd.', {
          methodId: id,
          deletedLessonIds
        });

        const result = await shared.deleteMethod(id);
        methodsCache = await shared.listMethods();
        renderMethodOptions(methodsCache);
        methodsSelect.value = '';
        methodIdInput.value = '';
        renderTechnicalMethodId();
        methodTitleInput.value = '';
        methodDescriptionInput.value = '';
        methodImageUrlInput.value = '';
        renderMethodImagePreview();
        renderRunmethodLink();
        shared.updateState({ methodId: '', methodTitle: '', methodDescription: '', methodImageUrl: '' });
        setStatus(`Method deleted: ${id}`, {
          result,
          deletedLessons: deletedLessonIds
        });
      } catch (err) {
        appendStatus('Verwijderen mislukt.', {
          error: err.message,
          methodId: id,
          endpoint: METHODS_DELETE_ENDPOINT
        });
        setStatus(`Delete error: ${err.message}`);
      }
    });

    copyRunmethodLinkBtn?.addEventListener('click', async () => {
      if (copyRunmethodLinkBtn.disabled || !openRunmethodLink?.href || openRunmethodLink.href === '#') {
        setStatus('Geen runmethod link beschikbaar.');
        return;
      }
      try {
        await navigator.clipboard.writeText(openRunmethodLink.href);
        setStatus('Runmethod link copied to clipboard.', { href: openRunmethodLink.href });
      } catch (err) {
        setStatus(`Copy link error: ${err.message || String(err)}`);
      }
    });

    document.getElementById('refreshMethodsBtn').addEventListener('click', async () => {
      try {
        appendStatus('Method list refresh gestart.', {
          endpoint: METHODS_LIST_ENDPOINT
        });
        methodsCache = await shared.listMethods();
        renderMethodOptions(methodsCache);
        renderRunmethodLink();
        setStatus(`Loaded ${methodsCache.length} method(s).`, {
          endpoint: METHODS_LIST_ENDPOINT,
          methodsCount: methodsCache.length
        });
      } catch (err) {
        setStatus(`Refresh error: ${err.message}`);
      }
    });

    document.getElementById('authBtn')?.addEventListener('click', async () => {
      isDebugLogVisible = true;
      renderDebugLogVisibility();
      setStatus('Authentication starten...');
      try {
        if (!shared || typeof shared.openAuthenticationPopup !== 'function') {
          throw new Error('lessonbuilder-shared.js is not up to date or did not load');
        }
        await shared.openAuthenticationPopup();
        setStatus('Authentication completed.');
      } catch (err) {
        setStatus(`Authentication error: ${err.message}`);
      }
    });

    toggleDebugLogBtn.addEventListener('click', () => {
      isDebugLogVisible = !isDebugLogVisible;
      renderDebugLogVisibility();
    });

    window.addEventListener('load', () => {
      if (authRedirected) return;
      init();
    });
  </script>
</body>
</html>
