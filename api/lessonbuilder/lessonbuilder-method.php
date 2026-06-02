<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'docent'], 'page');

$authUser = bs_auth_current_user() ?? ['display' => '', 'role' => ''];

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?lessonbuilder$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$lessonBuilderBase = $scriptDir;

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lesson Builder - Methode</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <script src="<?= $htmlUrl($urlFor($appBase, 'api/lessonbuilder/lessonbuilder-shared.js?v=20260602-local-api-2')) ?>"></script>
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-braille text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>
        <div class="navbar-nav flex-row ms-auto">
          <span class="navbar-text text-secondary me-2">
            Ingelogd als <?= $html($authUser['display']) ?> (<?= $html($authUser['role']) ?>)
          </span>
          <form method="post" action="<?= $htmlUrl($urlFor($appBase, 'authentication.php')) ?>" class="mb-0">
            <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="returnTo" value="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
            <button class="btn btn-outline-secondary" type="submit">
              <i class="ti ti-logout me-2" aria-hidden="true"></i>
              Uitloggen
            </button>
          </form>
        </div>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-3 align-items-center">
            <div class="col">
              <div class="page-pretitle">Stap 1 van 3</div>
              <h1 class="page-title">Methode</h1>
              <div class="text-secondary mt-2">Kies een bestaande methode of maak een nieuwe. Een basisbestand is optioneel.</div>
            </div>
            <div class="col-auto">
              <div class="btn-list">
                <a class="btn btn-outline-secondary" href="<?= $htmlUrl($urlFor($lessonBuilderBase, 'lessonbuilder.php')) ?>">
                  <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
                  Overzicht
                </a>
                <a class="btn btn-primary" href="<?= $htmlUrl($urlFor($lessonBuilderBase, 'lessonbuilder-records.php')) ?>">
                  Volgende stap
                  <i class="ti ti-arrow-right ms-2" aria-hidden="true"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="card">
            <div class="card-header">
              <div>
                <h2 class="card-title">Methode</h2>
                <div class="card-subtitle">Beheer de methodegegevens en het gekoppelde basisbestand.</div>
              </div>
            </div>
            <div class="card-body">
              <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-7">
                  <label class="form-label" for="methodsSelect">Method list</label>
                  <select id="methodsSelect" class="form-select"></select>
                </div>
                <div class="col-12 col-lg-5">
                  <div class="btn-list justify-content-lg-end">
                    <a id="openRunmethodLink" class="btn btn-outline-secondary disabled" href="#" target="_blank" rel="noopener noreferrer" aria-disabled="true">
                      <i class="ti ti-external-link me-2" aria-hidden="true"></i>
                      Link
                    </a>
                    <button id="copyRunmethodLinkBtn" type="button" class="btn btn-outline-secondary" disabled>
                      <i class="ti ti-copy me-2" aria-hidden="true"></i>
                      Copy link
                    </button>
                  </div>
                </div>
                <input id="methodIdInput" type="hidden">
              </div>

              <div class="row g-3 mt-1">
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="methodTechnicalIdDisplay">Technical method id</label>
                  <input id="methodTechnicalIdDisplay" class="form-control" type="text" readonly placeholder="Will be generated on save">
                  <div class="form-hint">This id is used for the file name and runmethod link.</div>
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="methodBasisFileSelect">Basisbestand</label>
                  <select id="methodBasisFileSelect" class="form-select"></select>
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="methodTitleInput">Method title</label>
                  <input id="methodTitleInput" class="form-control" type="text" placeholder="Aanvankelijk">
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="methodDescriptionInput">Description</label>
                  <input id="methodDescriptionInput" class="form-control" type="text" placeholder="Woordenlijst voor aanvankelijk lezen">
                </div>
                <div class="col-12 col-lg-8">
                  <label class="form-label" for="methodImageUrlInput">Image URL</label>
                  <div class="row g-2 align-items-center">
                    <div class="col">
                      <input id="methodImageUrlInput" class="form-control" type="text" placeholder="https://www.tastenbraille.com/braillestudio/assets/bartimeus.png">
                    </div>
                    <div id="methodImagePreview" class="col-auto d-none" hidden></div>
                  </div>
                </div>
              </div>

              <input id="methodDataSourceInput" type="hidden">

              <div class="btn-list mt-4">
                <button id="newMethodBtn" class="btn btn-outline-secondary" type="button">
                  <i class="ti ti-plus me-2" aria-hidden="true"></i>
                  New method
                </button>
                <button id="saveMethodBtn" class="btn btn-primary" type="button">
                  <i class="ti ti-device-floppy me-2" aria-hidden="true"></i>
                  Save method
                </button>
                <button id="saveAsNewMethodBtn" class="btn btn-outline-primary" type="button">
                  <i class="ti ti-copy-plus me-2" aria-hidden="true"></i>
                  Save as new
                </button>
                <button id="deleteMethodBtn" class="btn btn-danger" type="button">
                  <i class="ti ti-trash me-2" aria-hidden="true"></i>
                  Delete method
                </button>
                <button id="refreshMethodsBtn" class="btn btn-outline-secondary" type="button">
                  <i class="ti ti-refresh me-2" aria-hidden="true"></i>
                  Refresh methods
                </button>
              </div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
              <h2 class="card-title">Debug log</h2>
              <div class="card-actions">
                <button id="toggleDebugLogBtn" type="button" class="btn btn-outline-secondary btn-sm">Unhide</button>
              </div>
            </div>
            <div class="card-body d-none" id="debugLogBody" hidden>
              <pre id="statusBox" class="form-control font-monospace mb-0" rows="8"></pre>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
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
    const debugLogBody = document.getElementById('debugLogBody');
    const toggleDebugLogBtn = document.getElementById('toggleDebugLogBtn');
    const newMethodBtn = document.getElementById('newMethodBtn');
    const saveMethodBtn = document.getElementById('saveMethodBtn');
    const saveAsNewMethodBtn = document.getElementById('saveAsNewMethodBtn');
    const authRedirected = Boolean(shared?.requireAuthOnProduction?.());

    let methodsCache = [];
    let basisFileOptions = [];
    let basisItems = [];
    let isDebugLogVisible = false;
    const METHODS_API_BASE = new URL('../methods-api', window.location.href).toString().replace(/\/$/, '');
    const METHODS_SAVE_ENDPOINT = `${METHODS_API_BASE}/save_method.php`;
    const METHODS_LIST_ENDPOINT = `${METHODS_API_BASE}/list_methods.php`;
    const METHODS_DELETE_ENDPOINT = `${METHODS_API_BASE}/delete_method.php`;
    const METHODS_STORAGE_DIR = '/braillestudio/data/methods/';

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
      if (debugLogBody) {
        debugLogBody.hidden = !isDebugLogVisible;
        debugLogBody.classList.toggle('d-none', !isDebugLogVisible);
      }
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
        methodImagePreview.hidden = true;
        methodImagePreview.classList.add('d-none');
        methodImagePreview.innerHTML = '';
        return;
      }
      methodImagePreview.hidden = false;
      methodImagePreview.classList.remove('d-none');
      methodImagePreview.innerHTML = '';
      const previewImage = document.createElement('img');
      previewImage.className = 'rounded';
      previewImage.src = imageUrl;
      previewImage.alt = 'Method preview';
      previewImage.width = 144;
      methodImagePreview.appendChild(previewImage);
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
        openRunmethodLink.className = 'btn btn-outline-secondary disabled';
        if (copyRunmethodLinkBtn) {
          copyRunmethodLinkBtn.disabled = true;
          copyRunmethodLinkBtn.className = 'btn btn-outline-secondary';
        }
        return;
      }
      openRunmethodLink.href = `https://www.tastenbraille.com/braillestudio/runmethod.php?id=${encodeURIComponent(methodId)}`;
      openRunmethodLink.setAttribute('aria-disabled', 'false');
      openRunmethodLink.className = 'btn btn-outline-primary';
      if (copyRunmethodLinkBtn) {
        copyRunmethodLinkBtn.disabled = false;
        copyRunmethodLinkBtn.className = 'btn btn-outline-secondary';
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
        endpoint: `${METHODS_API_BASE}/load_method.php?id=${encodeURIComponent(id)}`
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
