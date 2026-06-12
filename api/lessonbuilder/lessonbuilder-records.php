<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'docent'], 'page');

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?lessonbuilder$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$lessonBuilderBase = $scriptDir;

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lesson Builder - Lessons</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <script src="<?= $htmlUrl($urlFor($appBase, 'api/lessonbuilder/lessonbuilder-shared.js?v=20260612-fast-methods-1')) ?>"></script>
</head>
<body class="bg-body">
  <div id="recordsLoadingScreen" class="page page-center" aria-live="polite">
    <div class="container-tight py-4">
      <div class="card card-md">
        <div class="card-body text-center py-5">
          <div class="mb-3">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
          </div>
          <h1 class="h3 mb-2">Lesson Builder laden</h1>
          <p id="recordsLoadingMessage" class="text-secondary mb-0">Methodegegevens voorbereiden.</p>
        </div>
      </div>
    </div>
  </div>

  <div id="recordsAppPage" class="page d-none" hidden>
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-braille text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>
        <div id="methodSummary" class="navbar-text ms-3"></div>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-3 align-items-center">
            <div class="col">
              <div class="page-pretitle">Stap 2 van 3</div>
              <h1 class="page-title">Lessons</h1>
            </div>
            <div class="col-auto">
              <div class="btn-list">
                <a class="btn btn-outline-secondary" href="<?= $htmlUrl($urlFor($lessonBuilderBase, 'lessonbuilder-method.php')) ?>">
                  <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
                  Vorige stap
                </a>
                <a class="btn btn-primary" href="<?= $htmlUrl($urlFor($lessonBuilderBase, 'lessonbuilder-steps.php')) ?>">
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
          <div class="btn-list mb-3">
            <button id="newRecordBtn" type="button" class="btn btn-outline-secondary btn-sm">
              <i class="ti ti-plus me-2" aria-hidden="true"></i>
              New
            </button>
            <button id="saveRecordsBtn" type="button" class="btn btn-outline-secondary btn-sm">
              <i class="ti ti-device-floppy me-2" aria-hidden="true"></i>
              Save
            </button>
            <button id="openLessonBtn" type="button" class="btn btn-outline-secondary btn-sm">Open lesson</button>
            <button id="moveUpBtn" type="button" class="btn btn-outline-secondary btn-sm">Move up</button>
            <button id="moveDownBtn" type="button" class="btn btn-outline-secondary btn-sm">Move down</button>
            <button id="deleteRecordBtn" type="button" class="btn btn-outline-secondary btn-sm">Delete</button>
          </div>

          <div class="row row-cards">
            <div class="col-12 col-xl-5">
              <div class="card">
                <div id="basisRecordsList" class="list-group list-group-flush overflow-auto"></div>
              </div>
            </div>

            <div class="col-12 col-xl-7">
              <div class="card">
                <div class="card-header">
                  <div>
                    <h2 class="card-title">Editor</h2>
                    <div id="recordEditorCaption" class="card-subtitle">Kies een record uit de lijst of maak een nieuw record.</div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-12 col-lg-6">
                      <label class="form-label" for="wordInput">Word</label>
                      <input id="wordInput" type="text" class="form-control" placeholder="bijv. bal">
                    </div>
                    <div class="col-12 col-lg-6">
                      <label class="form-label" for="soundsInput">Sounds</label>
                      <input id="soundsInput" type="text" class="form-control" placeholder="b, a, l">
                    </div>
                    <div class="col-12 col-lg-6">
                      <label class="form-label" for="newSoundsInput">newSounds</label>
                      <input id="newSoundsInput" type="text" class="form-control" placeholder="b, a, l">
                    </div>
                    <div class="col-12 col-lg-6">
                      <label class="form-label" for="knownSoundsInput">knownSounds</label>
                      <textarea id="knownSoundsInput" class="form-control" rows="4" placeholder="b, a, l"></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
              <h2 class="card-title">Debug log</h2>
            </div>
            <div class="card-body">
              <div id="statusBox" class="list-group list-group-flush border rounded font-monospace overflow-auto" style="max-height: 24rem"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal modal-blur fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
      <div class="modal-content">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
        <div class="modal-status bg-danger"></div>
        <div class="modal-body text-center py-4">
          <i class="ti ti-alert-triangle icon mb-2 text-danger icon-lg" aria-hidden="true"></i>
          <h3>Record verwijderen?</h3>
          <div id="deleteConfirmText" class="text-secondary"></div>
        </div>
        <div class="modal-footer">
          <div class="w-100">
            <div class="row">
              <div class="col">
                <button type="button" class="btn w-100" data-bs-dismiss="modal">Annuleren</button>
              </div>
              <div class="col">
                <button id="confirmDeleteRecordBtn" type="button" class="btn btn-danger w-100">
                  Verwijderen
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script>
    const shared = window.LessonBuilderShared;
    const recordsLoadingScreen = document.getElementById('recordsLoadingScreen');
    const recordsLoadingMessage = document.getElementById('recordsLoadingMessage');
    const recordsAppPage = document.getElementById('recordsAppPage');
    const basisRecordsList = document.getElementById('basisRecordsList');
    const methodSummary = document.getElementById('methodSummary');
    const recordEditorCaption = document.getElementById('recordEditorCaption');
    const statusBox = document.getElementById('statusBox');
    const wordInput = document.getElementById('wordInput');
    const soundsInput = document.getElementById('soundsInput');
    const newSoundsInput = document.getElementById('newSoundsInput');
    const knownSoundsInput = document.getElementById('knownSoundsInput');
    const newRecordBtn = document.getElementById('newRecordBtn');
    const saveRecordsBtn = document.getElementById('saveRecordsBtn');
    const openLessonBtn = document.getElementById('openLessonBtn');
    const moveUpBtn = document.getElementById('moveUpBtn');
    const moveDownBtn = document.getElementById('moveDownBtn');
    const deleteRecordBtn = document.getElementById('deleteRecordBtn');
    const deleteConfirmModalElement = document.getElementById('deleteConfirmModal');
    const deleteConfirmText = document.getElementById('deleteConfirmText');
    const confirmDeleteRecordBtn = document.getElementById('confirmDeleteRecordBtn');

    let state = shared.loadState();
    let basisItems = [];
    let originalBasisItems = [];
    let lessonsCache = [];
    let pendingDeleteIndex = -1;
    const authRedirected = Boolean(shared?.requireAuthOnProduction?.());

    function setLoadingMessage(message) {
      recordsLoadingMessage.textContent = message;
    }

    function hideLoadingScreen() {
      recordsLoadingScreen.hidden = true;
      recordsLoadingScreen.classList.add('d-none');
      recordsAppPage.hidden = false;
      recordsAppPage.classList.remove('d-none');
    }

    function showLoadingError(message) {
      recordsLoadingMessage.textContent = message;
      recordsLoadingMessage.classList.remove('text-secondary');
      recordsLoadingMessage.classList.add('text-danger');
    }

    function setStatus(message, data = null) {
      statusBox.replaceChildren();
      const item = document.createElement('div');
      item.className = 'list-group-item py-2';
      const title = document.createElement('div');
      title.className = 'fw-medium';
      title.textContent = message;
      item.append(title);
      if (data && typeof data === 'object') {
        Object.entries(data).forEach(([key, value]) => {
          const detail = document.createElement('div');
          detail.className = 'text-secondary small';
          detail.textContent = `${key}: ${Array.isArray(value) ? value.join(', ') : String(value ?? '')}`;
          item.append(detail);
        });
      }
      statusBox.prepend(item);
    }

    function cloneDeep(value) {
      return JSON.parse(JSON.stringify(value));
    }

    function createRecordUid() {
      return `record-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
    }

    function normalizeSoundListInput(value) {
      return String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
    }

    function ensureRecordUid(item) {
      const next = item && typeof item === 'object' ? cloneDeep(item) : shared.createEmptyBasisRecord();
      next._recordUid = String(next._recordUid || createRecordUid());
      return next;
    }

    function serializeBasisItems(items = basisItems) {
      return items.map((item) => {
        const { _recordUid, ...rest } = item || {};
        return rest;
      });
    }

    function createRecordItem() {
      return ensureRecordUid(shared.createEmptyBasisRecord());
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function getSelectedIndex() {
      const basisIndex = Number(state.basisIndex ?? -1);
      if (!Number.isInteger(basisIndex) || basisIndex < 0 || basisIndex >= basisItems.length) {
        return -1;
      }
      return basisIndex;
    }

    function getSelectedRecord() {
      const index = getSelectedIndex();
      return index >= 0 ? basisItems[index] : null;
    }

    function renderMethodSummary() {
      const method = shared.getDraftMethodMeta(state);
      const methodId = String(method.id || '-');
      const methodTitle = String(method.title || '-');
      methodSummary.innerHTML = `
        <span class="fw-medium">${escapeHtml(methodTitle)}</span>
        <span class="text-secondary ms-2">${escapeHtml(methodId)}</span>
      `;
    }

    function getLessonForBasis(basisIndex) {
      return shared.getLessonsForBasis(lessonsCache, basisIndex)[0] || null;
    }

    function getLessonsForBasisIndex(basisIndex) {
      return shared.getLessonsForBasis(lessonsCache, basisIndex);
    }

    function buildDraftLessonForBasis(basisIndex) {
      const item = basisItems[basisIndex];
      const method = shared.getDraftMethodMeta(state);
      if (!item || !method.id) return null;
      return {
        id: shared.buildLessonIdFromBasis(method.id, basisIndex, item, 1),
        title: shared.buildLessonTitleFromBasis(item, 1, basisIndex),
        lessonNumber: 1,
        basisIndex,
        basisWord: shared.getBasisWord(item, basisIndex),
        basisRecord: serializeBasisItems([item])[0],
        steps: [],
        isDraft: true
      };
    }

    function selectBasisIndex(index) {
      const basisIndex = Number(index);
      if (!Number.isInteger(basisIndex) || basisIndex < 0 || basisIndex >= basisItems.length) {
        state = shared.updateState({
          basisIndex: -1,
          basisWord: '',
          basisRecord: null,
          lessonId: '',
          lessonTitle: '',
          lessonMetaTitle: '',
          lessonDescription: '',
          lessonNumber: 1,
          lessonWord: '',
          steps: []
        });
        renderEditor();
        renderBasisList();
        return;
      }

      const item = basisItems[basisIndex];
      const lesson = getLessonForBasis(basisIndex) || buildDraftLessonForBasis(basisIndex);
      state = shared.updateState({
        basisIndex,
        basisWord: shared.getBasisWord(item, basisIndex),
        basisRecord: serializeBasisItems([item])[0],
        lessonId: lesson?.id || '',
        lessonTitle: lesson?.title || '',
        lessonMetaTitle: String(lesson?.title || '').trim(),
        lessonDescription: String(lesson?.description || '').trim(),
        lessonNumber: lesson?.lessonNumber || 1,
        lessonWord: lesson?.basisWord || shared.getBasisWord(item, basisIndex),
        steps: shared.normalizeStepConfigs(lesson?.steps || [])
      });
      renderBasisList();
      renderEditor();
    }

    function renderBasisList() {
      basisRecordsList.innerHTML = '';
      if (!basisItems.length) {
        basisRecordsList.innerHTML = '<div class="list-group-item text-secondary">Geen basisrecords geladen.</div>';
        return;
      }

      basisItems.forEach((item, index) => {
        const lessons = getLessonsForBasisIndex(index);
        const button = document.createElement('button');
        const isSelected = index === getSelectedIndex();
        button.type = 'button';
        button.dataset.index = String(index);
        button.className = `list-group-item list-group-item-action text-start ${isSelected ? 'active bg-primary text-white border-primary' : ''}`;
        button.innerHTML = `
          <div class="row align-items-start">
            <div class="col">
              <div class="fw-bold">${escapeHtml(shared.getBasisWord(item, index) || '(zonder woord)')}</div>
              <div class="small ${isSelected ? 'text-white-50' : 'text-secondary'}">${escapeHtml((item.sounds || []).join(', ') || 'Geen sounds')}</div>
              <div class="small ${isSelected ? 'text-white-50' : 'text-secondary'}">${escapeHtml(lessons.length)} lesson(s)</div>
            </div>
            <div class="col-auto">
              <span class="badge ${isSelected ? 'bg-white text-primary' : 'bg-secondary-lt'}">${String(index + 1).padStart(2, '0')}</span>
            </div>
          </div>
        `;
        button.addEventListener('click', () => selectBasisIndex(index));
        button.addEventListener('dblclick', () => {
          selectBasisIndex(index);
          openSelectedLesson();
        });
        basisRecordsList.appendChild(button);
      });
    }

    function renderEditor() {
      const index = getSelectedIndex();
      const item = getSelectedRecord();

      const hasSelection = Boolean(item);
      wordInput.disabled = !hasSelection;
      soundsInput.disabled = !hasSelection;
      newSoundsInput.disabled = !hasSelection;
      knownSoundsInput.disabled = !hasSelection;
      openLessonBtn.disabled = !hasSelection;
      moveUpBtn.disabled = !hasSelection || index <= 0;
      moveDownBtn.disabled = !hasSelection || index >= basisItems.length - 1;
      deleteRecordBtn.disabled = !hasSelection;

      if (!item) {
        recordEditorCaption.textContent = 'Kies een record uit de lijst of maak een nieuw record.';
        wordInput.value = '';
        soundsInput.value = '';
        newSoundsInput.value = '';
        knownSoundsInput.value = '';
        return;
      }

      recordEditorCaption.textContent = `Record ${index + 1} van ${basisItems.length}`;
      wordInput.value = String(item.word || '').trim();
      soundsInput.value = (item.sounds || []).join(', ');
      newSoundsInput.value = (item.newSounds || []).join(', ');
      knownSoundsInput.value = (item.knownSounds || []).join(', ');
    }

    function updateSelectedRecordFromInputs() {
      const index = getSelectedIndex();
      if (index < 0) {
        return;
      }

      const current = basisItems[index];
      current.word = String(wordInput.value || '').trim();
      current.sounds = normalizeSoundListInput(soundsInput.value);
      current.newSounds = normalizeSoundListInput(newSoundsInput.value);
      current.knownSounds = normalizeSoundListInput(knownSoundsInput.value);

      basisItems[index] = current;
      renderBasisList();
      renderEditor();
    }

    function insertNewRecord() {
      const insertIndex = getSelectedIndex() >= 0 ? getSelectedIndex() + 1 : basisItems.length;
      basisItems.splice(insertIndex, 0, createRecordItem());
      selectBasisIndex(insertIndex);
      setStatus('Nieuw record toegevoegd.');
      wordInput.focus();
    }

    function moveSelectedRecord(direction) {
      const index = getSelectedIndex();
      if (index < 0) {
        return;
      }
      const targetIndex = index + direction;
      if (targetIndex < 0 || targetIndex >= basisItems.length) {
        return;
      }

      const temp = basisItems[index];
      basisItems[index] = basisItems[targetIndex];
      basisItems[targetIndex] = temp;
      selectBasisIndex(targetIndex);
      setStatus(`Record verplaatst naar positie ${targetIndex + 1}.`);
    }

    function removeRecordAtIndex(index) {
      if (index < 0) {
        return;
      }
      const word = shared.getBasisWord(basisItems[index], index);

      basisItems.splice(index, 1);
      const nextIndex = basisItems.length ? Math.min(index, basisItems.length - 1) : -1;
      selectBasisIndex(nextIndex);
      setStatus(`Record "${word}" verwijderd. Sla op om het basisbestand en gekoppelde lessons bij te werken.`);
    }

    function getDeleteModal() {
      if (!deleteConfirmModalElement || !window.bootstrap?.Modal) {
        return null;
      }
      return window.bootstrap.Modal.getOrCreateInstance(deleteConfirmModalElement);
    }

    function deleteSelectedRecord() {
      const index = getSelectedIndex();
      if (index < 0) {
        return;
      }
      const linkedLessons = getLessonsForBasisIndex(index);
      const word = shared.getBasisWord(basisItems[index], index) || `record ${index + 1}`;
      const suffix = linkedLessons.length ? ` Dit verwijdert na opslaan ook ${linkedLessons.length} gekoppelde lesson(s).` : '';
      pendingDeleteIndex = index;
      deleteConfirmText.textContent = `Je verwijdert "${word}".${suffix}`;
      const modal = getDeleteModal();
      if (modal) {
        modal.show();
        return;
      }
      if (window.confirm(`Record "${word}" verwijderen?${suffix}`)) {
        removeRecordAtIndex(index);
      }
    }

    function confirmDeleteRecord() {
      const index = pendingDeleteIndex;
      pendingDeleteIndex = -1;
      getDeleteModal()?.hide();
      removeRecordAtIndex(index);
    }

    async function syncLessonsAfterBasisSave(previousItems, nextItems) {
      const method = shared.getDraftMethodMeta(state);
      if (!method.id) {
        return { updated: 0, deleted: 0 };
      }

      const freshLessons = await shared.listLessons(method.id);
      const oldUidByIndex = previousItems.map((item) => String(item?._recordUid || ''));
      const nextIndexByUid = new Map(nextItems.map((item, index) => [String(item?._recordUid || ''), index]));
      let updated = 0;
      let deleted = 0;

      for (const lessonSummary of freshLessons) {
        const oldBasisIndex = Number(lessonSummary?.basisIndex ?? -1);
        if (oldBasisIndex < 0) {
          continue;
        }

        const recordUid = oldUidByIndex[oldBasisIndex] || '';
        if (!recordUid) {
          continue;
        }

        const nextBasisIndex = nextIndexByUid.has(recordUid) ? Number(nextIndexByUid.get(recordUid)) : -1;
        if (nextBasisIndex < 0) {
          await shared.deleteLesson(String(lessonSummary.id || ''));
          deleted += 1;
          continue;
        }

        const nextRecord = nextItems[nextBasisIndex];
        const loadedLesson = await shared.loadLesson(String(lessonSummary.id || ''));
        await shared.saveLesson({
          ...loadedLesson,
          overwrite: true,
          basisIndex: nextBasisIndex,
          basisWord: shared.getBasisWord(nextRecord, nextBasisIndex),
          basisRecord: serializeBasisItems([nextRecord])[0],
        });
        updated += 1;
      }

      lessonsCache = await shared.listLessons(method.id);
      return { updated, deleted };
    }

    async function saveRecords() {
      const method = shared.getDraftMethodMeta(state);
      if (!method.basisFile) {
        setStatus('Geen basisbestand gekozen in stap 1.');
        return;
      }

      updateSelectedRecordFromInputs();
      const previousItems = cloneDeep(originalBasisItems);
      const payload = serializeBasisItems();

      try {
        saveRecordsBtn.disabled = true;
        const saveResult = await shared.saveBasisData(method.basisFile, payload);
        shared.clearBasisDataCache(method.dataSource);
        const syncResult = await syncLessonsAfterBasisSave(previousItems, basisItems);
        originalBasisItems = cloneDeep(basisItems);

        const selectedIndex = getSelectedIndex();
        if (selectedIndex >= 0) {
          selectBasisIndex(selectedIndex);
        } else {
          renderBasisList();
          renderEditor();
        }

        setStatus('Basisrecords opgeslagen.', {
          saveResult,
          syncedLessons: syncResult,
        });
      } catch (err) {
        setStatus(`Opslaan mislukt: ${err.message}`);
      } finally {
        saveRecordsBtn.disabled = false;
      }
    }

    async function openSelectedLesson() {
      const basisIndex = getSelectedIndex();
      const draftLesson = basisIndex >= 0 ? buildDraftLessonForBasis(basisIndex) : null;
      if (!state.lessonId && !draftLesson) {
        setStatus('Kies eerst een basisrecord.');
        return;
      }

      if (draftLesson && (!state.lessonId || state.lessonId === draftLesson.id)) {
        const existing = getLessonForBasis(basisIndex);
        if (!existing) {
          shared.updateState({
            lessonId: draftLesson.id,
            lessonTitle: draftLesson.title,
            lessonMetaTitle: draftLesson.title,
            lessonDescription: '',
            lessonNumber: 1,
            lessonWord: draftLesson.basisWord,
            basisRecord: draftLesson.basisRecord,
            steps: []
          });
          window.location.href = <?= json_encode($urlFor($lessonBuilderBase, 'lessonbuilder-steps.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
          return;
        }
      }

      try {
        const data = await shared.loadLesson(state.lessonId);
        const resolvedWord = data.basisWord || state.basisWord || '';
        shared.updateState({
          lessonId: data.id || state.lessonId,
          lessonTitle: data.title || (resolvedWord ? `les - ${resolvedWord}` : ''),
          lessonMetaTitle: String(data?.title || '').trim(),
          lessonDescription: String(data?.description || '').trim(),
          lessonNumber: data.lessonNumber || 1,
          lessonWord: resolvedWord,
          steps: shared.normalizeStepConfigs(data.steps || [])
        });
        window.location.href = <?= json_encode($urlFor($lessonBuilderBase, 'lessonbuilder-steps.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      } catch (err) {
        setStatus(`Lesson load error: ${err.message}`);
      }
    }

    async function init() {
      try {
        setLoadingMessage('Methodegegevens voorbereiden.');
        state = shared.loadState();
        renderMethodSummary();
        const method = shared.getDraftMethodMeta(state);

        setLoadingMessage('Basisrecords en lessons laden.');
        const [loadedBasisItems, loadedLessons] = await Promise.all([
          shared.loadBasisData(method.dataSource || shared.DEFAULT_BASIS_DATA_URL),
          method.id ? shared.listLessons(method.id) : Promise.resolve([]),
        ]);

        basisItems = loadedBasisItems.map(ensureRecordUid);
        originalBasisItems = cloneDeep(basisItems);
        lessonsCache = loadedLessons;

        setLoadingMessage('Editor klaarzetten.');
        if (Number.isInteger(state.basisIndex) && Number(state.basisIndex) >= 0 && Number(state.basisIndex) < basisItems.length) {
          selectBasisIndex(Number(state.basisIndex));
        } else if (basisItems.length) {
          selectBasisIndex(0);
        } else {
          selectBasisIndex(-1);
        }

        setStatus('Ready.');
        hideLoadingScreen();
      } catch (err) {
        setStatus(`Init error: ${err.message}`);
        showLoadingError(`Laden mislukt: ${err.message}`);
      }
    }

    [wordInput, soundsInput, newSoundsInput, knownSoundsInput].forEach((input) => {
      input.addEventListener('input', updateSelectedRecordFromInputs);
    });

    newRecordBtn.addEventListener('click', insertNewRecord);
    saveRecordsBtn.addEventListener('click', saveRecords);
    openLessonBtn.addEventListener('click', openSelectedLesson);
    moveUpBtn.addEventListener('click', () => moveSelectedRecord(-1));
    moveDownBtn.addEventListener('click', () => moveSelectedRecord(1));
    deleteRecordBtn.addEventListener('click', deleteSelectedRecord);
    confirmDeleteRecordBtn.addEventListener('click', confirmDeleteRecord);
    deleteConfirmModalElement.addEventListener('hidden.bs.modal', () => {
      pendingDeleteIndex = -1;
    });

    document.addEventListener('keydown', (event) => {
      const target = event.target;
      const tagName = String(target?.tagName || '').toUpperCase();
      if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
        return;
      }
      if (!basisItems.length) {
        return;
      }

      const currentIndex = getSelectedIndex();

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        const nextIndex = currentIndex < 0 ? 0 : Math.min(currentIndex + 1, basisItems.length - 1);
        selectBasisIndex(nextIndex);
        basisRecordsList.querySelector(`[data-index="${nextIndex}"]`)?.focus();
        return;
      }

      if (event.key === 'ArrowUp') {
        event.preventDefault();
        const previousIndex = currentIndex < 0 ? 0 : Math.max(currentIndex - 1, 0);
        selectBasisIndex(previousIndex);
        basisRecordsList.querySelector(`[data-index="${previousIndex}"]`)?.focus();
        return;
      }

      if (event.key === 'Enter' && currentIndex >= 0) {
        event.preventDefault();
        openSelectedLesson();
      }
    });

    window.addEventListener('load', () => {
      if (authRedirected) return;
      init();
    });
  </script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
