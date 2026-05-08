<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lesson Builder - Basisrecords</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="./lessonbuilder-shared.js?v=20260507-1"></script>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="max-w-7xl mx-auto p-6 space-y-5">
    <div class="flex items-center justify-between gap-4">
      <div>
        <div class="text-sm font-semibold text-blue-700">Stap 2 van 3</div>
        <h1 class="text-3xl font-bold">Basisrecords</h1>
      </div>
      <div class="flex gap-2">
        <a class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-method.php">Vorige stap</a>
        <a class="rounded-xl border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-steps.php">Volgende stap</a>
      </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
      <div class="text-lg font-bold">Actieve methode</div>
      <div id="methodSummary" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-lg font-bold">Lessons</div>
          </div>
          <div class="flex gap-2">
            <button id="newRecordBtn" type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">New</button>
            <button id="saveRecordsBtn" type="button" class="rounded-xl border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
          </div>
        </div>
        <div id="basisRecordsList" class="max-h-[620px] space-y-2 overflow-auto"></div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-lg font-bold">Record editor</div>
            <div id="recordEditorCaption" class="text-sm text-slate-500">Kies een record uit de lijst of maak een nieuw record.</div>
          </div>
          <div class="flex flex-wrap justify-end gap-2">
            <button id="openLessonBtn" type="button" class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">Open lesson</button>
            <button id="moveUpBtn" type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">Move up</button>
            <button id="moveDownBtn" type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">Move down</button>
            <button id="deleteRecordBtn" type="button" class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">Delete</button>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <label class="space-y-2">
            <span class="text-sm font-semibold text-slate-700">Word</span>
            <input id="wordInput" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="bijv. bal">
          </label>
          <label class="space-y-2">
            <span class="text-sm font-semibold text-slate-700">Sounds</span>
            <input id="soundsInput" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="b, a, l">
          </label>
          <label class="space-y-2">
            <span class="text-sm font-semibold text-slate-700">newSounds</span>
            <input id="newSoundsInput" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="b, a, l">
          </label>
          <label class="space-y-2">
            <span class="text-sm font-semibold text-slate-700">knownSounds</span>
            <textarea id="knownSoundsInput" class="min-h-[108px] w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="b, a, l"></textarea>
          </label>
        </div>

        <div id="recordSummary" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">Selecteer een basisrecord. Open daarna met dubbelklik of Enter.</div>
      </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
      <div class="text-lg font-bold">Debug log</div>
      <pre id="statusBox" class="min-h-[180px] rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap"></pre>
    </section>
  </div>

  <script>
    const shared = window.LessonBuilderShared;
    const basisRecordsList = document.getElementById('basisRecordsList');
    const methodSummary = document.getElementById('methodSummary');
    const recordSummary = document.getElementById('recordSummary');
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

    let state = shared.loadState();
    let basisItems = [];
    let originalBasisItems = [];
    let lessonsCache = [];
    const authRedirected = Boolean(shared?.requireAuthOnProduction?.());

    function setStatus(message, data = null) {
      statusBox.textContent = data ? `${message}\n\n${JSON.stringify(data, null, 2)}` : message;
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
      methodSummary.innerHTML = `
        <div><strong>ID:</strong> ${method.id || '-'}</div>
        <div><strong>Title:</strong> ${method.title || '-'}</div>
        <div><strong>Basisbestand:</strong> ${method.basisFile || '-'}</div>
        <div><strong>Data source:</strong> ${method.dataSource || '-'}</div>
        <div><strong>Image:</strong> ${method.imageUrl || '-'}</div>
        ${method.imageUrl ? `<div class="mt-3"><img src="${method.imageUrl}" alt="Method image" class="max-h-32 rounded-lg border border-slate-200 bg-white object-contain"></div>` : ''}
      `;
    }

    function getLessonForBasis(basisIndex) {
      return shared.getLessonsForBasis(lessonsCache, basisIndex)[0] || null;
    }

    function getLessonsForBasisIndex(basisIndex) {
      return shared.getLessonsForBasis(lessonsCache, basisIndex);
    }

    function getLessonStepCount(item) {
      if (!item) return 0;
      if (Array.isArray(item.steps)) return item.steps.length;
      return Number(item.stepsCount ?? 0) || 0;
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
        basisRecordsList.innerHTML = '<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Geen basisrecords geladen.</div>';
        return;
      }

      basisItems.forEach((item, index) => {
        const lessons = getLessonsForBasisIndex(index);
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.index = String(index);
        button.className = `w-full rounded-xl border px-4 py-3 text-left focus:outline-none focus:ring-0 ${index === getSelectedIndex() ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white'}`;
        button.innerHTML = `
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="font-bold">${shared.getBasisWord(item, index) || '(zonder woord)'}</div>
              <div class="mt-1 text-xs text-slate-500">${(item.sounds || []).join(', ') || 'Geen sounds'}</div>
              <div class="mt-1 text-xs text-slate-500">${lessons.length} lesson(s)</div>
            </div>
            <div class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">${String(index + 1).padStart(2, '0')}</div>
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
      const lessons = index >= 0 ? getLessonsForBasisIndex(index) : [];
      const draftLesson = index >= 0 ? buildDraftLessonForBasis(index) : null;
      const lesson = index >= 0 ? (getLessonForBasis(index) || draftLesson) : null;

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
        recordSummary.textContent = 'Selecteer een basisrecord. Open daarna met dubbelklik of Enter.';
        return;
      }

      recordEditorCaption.textContent = `Record ${index + 1} van ${basisItems.length}`;
      wordInput.value = String(item.word || '').trim();
      soundsInput.value = (item.sounds || []).join(', ');
      newSoundsInput.value = (item.newSounds || []).join(', ');
      knownSoundsInput.value = (item.knownSounds || []).join(', ');
      recordSummary.innerHTML = `
        <div><strong>Word:</strong> ${shared.getBasisWord(item, index) || '-'}</div>
        <div><strong>Sounds:</strong> ${(item.sounds || []).join(', ') || '-'}</div>
        <div><strong>newSounds:</strong> ${(item.newSounds || []).join(', ') || '-'}</div>
        <div><strong>knownSounds:</strong> ${(item.knownSounds || []).join(', ') || '-'}</div>
        <div><strong>Lesson:</strong> ${lesson?.title || '-'}</div>
        <div><strong>Steps:</strong> ${getLessonStepCount(lesson)}</div>
        <div><strong>Linked lessons:</strong> ${lessons.length}</div>
      `;
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

    function deleteSelectedRecord() {
      const index = getSelectedIndex();
      if (index < 0) {
        return;
      }
      const linkedLessons = getLessonsForBasisIndex(index);
      const word = shared.getBasisWord(basisItems[index], index);
      const suffix = linkedLessons.length ? ` Dit verwijdert na opslaan ook ${linkedLessons.length} gekoppelde lesson(s).` : '';
      if (!window.confirm(`Record "${word}" verwijderen?${suffix}`)) {
        return;
      }

      basisItems.splice(index, 1);
      const nextIndex = basisItems.length ? Math.min(index, basisItems.length - 1) : -1;
      selectBasisIndex(nextIndex);
      setStatus(`Record "${word}" verwijderd. Sla op om het basisbestand en gekoppelde lessons bij te werken.`);
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
          window.location.href = 'https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-steps.php';
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
        window.location.href = 'https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-steps.php';
      } catch (err) {
        setStatus(`Lesson load error: ${err.message}`);
      }
    }

    async function init() {
      try {
        state = shared.loadState();
        renderMethodSummary();
        const method = shared.getDraftMethodMeta(state);
        basisItems = (await shared.loadBasisData(method.dataSource || shared.DEFAULT_BASIS_DATA_URL)).map(ensureRecordUid);
        originalBasisItems = cloneDeep(basisItems);
        lessonsCache = method.id ? await shared.listLessons(method.id) : [];

        if (Number.isInteger(state.basisIndex) && Number(state.basisIndex) >= 0 && Number(state.basisIndex) < basisItems.length) {
          selectBasisIndex(Number(state.basisIndex));
        } else if (basisItems.length) {
          selectBasisIndex(0);
        } else {
          selectBasisIndex(-1);
        }

        setStatus('Ready.');
      } catch (err) {
        setStatus(`Init error: ${err.message}`);
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
</body>
</html>
