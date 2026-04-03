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
  <script src="./lessonbuilder-shared.js"></script>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="max-w-6xl mx-auto p-6 space-y-5">
    <div class="flex items-center justify-between gap-4">
      <div>
        <div class="text-sm font-semibold text-blue-700">Stap 2 van 3</div>
        <h1 class="text-3xl font-bold">Lessons</h1>
      </div>
      <div class="flex gap-2">
        <a class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-method.php">Vorige stap</a>
        <a class="rounded-xl border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-steps.php">Volgende stap</a>
      </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
      <div class="text-lg font-bold">Actieve methode</div>
      <div id="methodSummary" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
      <div id="recordSummary" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">Selecteer een basisrecord. Open daarna met dubbelklik of Enter.</div>
      <div id="basisRecordsList" class="max-h-[520px] space-y-2 overflow-auto"></div>
    </section>

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
    const statusBox = document.getElementById('statusBox');

    let state = shared.loadState();
    let basisItems = [];
    let lessonsCache = [];

    function setStatus(message, data = null) {
      statusBox.textContent = data ? `${message}\n\n${JSON.stringify(data, null, 2)}` : message;
    }

    function renderMethodSummary() {
      const method = shared.getDraftMethodMeta(state);
      methodSummary.innerHTML = `
        <div><strong>ID:</strong> ${method.id || '-'}</div>
        <div><strong>Title:</strong> ${method.title || '-'}</div>
        <div><strong>Basisbestand:</strong> ${method.basisFile || '-'}</div>
        <div><strong>Data source:</strong> ${method.dataSource || '-'}</div>
      `;
    }

    function renderBasisList() {
      basisRecordsList.innerHTML = '';
      if (!basisItems.length) {
        basisRecordsList.innerHTML = '<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Geen basisrecords geladen.</div>';
        return;
      }
      basisItems.forEach((item, index) => {
        const lesson = getLessonForBasis(index);
        const word = shared.getBasisWord(item, index);
        const stepCount = lesson ? getLessonStepCount(lesson) : 0;
        const lessonTitle = String(lesson?.meta?.title || lesson?.title || '').trim();
        const lessonDescription = String(lesson?.meta?.description || '').trim();
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.index = String(index);
        button.className = `w-full rounded-xl border px-4 py-3 text-left focus:outline-none focus:ring-0 ${index === Number(state.basisIndex ?? -1) ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white'}`;
        button.innerHTML = `
          <div class="font-bold">${index + 1}. ${word}</div>
          ${lessonTitle ? `<div class="mt-1 text-xs font-semibold text-slate-700">${lessonTitle}</div>` : ''}
          ${lessonDescription ? `<div class="mt-1 text-xs text-slate-600">${lessonDescription}</div>` : ''}
          <div class="mt-1 text-xs text-slate-500">${stepCount} step(s)</div>
        `;
        button.addEventListener('click', () => selectBasisIndex(index));
        button.addEventListener('dblclick', () => {
          selectBasisIndex(index);
          openSelectedLesson();
        });
        basisRecordsList.appendChild(button);
      });
    }

    function getLessonForBasis(basisIndex) {
      return shared.getLessonsForBasis(lessonsCache, basisIndex)[0] || null;
    }

    function getLessonStepCount(item) {
      if (!item) return 0;
      if (Array.isArray(item.stepConfigs)) return item.stepConfigs.length;
      if (Array.isArray(item.steps)) return item.steps.length;
      return 0;
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
        basisRecord: item,
        stepConfigs: [],
        isDraft: true
      };
    }

    function updateRecordSummary() {
      const basisIndex = Number(state.basisIndex ?? -1);
      const item = basisIndex >= 0 ? basisItems[basisIndex] : null;
      if (!item) {
        recordSummary.textContent = 'Selecteer een basisrecord. Open daarna met dubbelklik of Enter.';
        return;
      }
      const lesson = getLessonForBasis(basisIndex) || buildDraftLessonForBasis(basisIndex);
      const lessonTitle = String(lesson?.meta?.title || lesson?.title || '').trim();
      const lessonDescription = String(lesson?.meta?.description || '').trim();
      recordSummary.innerHTML = `
        <div><strong>Word:</strong> ${shared.getBasisWord(item, basisIndex)}</div>
        <div><strong>Sounds:</strong> ${(item.sounds || []).join(', ') || '-'}</div>
        <div><strong>newSounds:</strong> ${(item.newSounds || []).join(', ') || '-'}</div>
        <div><strong>knownSounds:</strong> ${(item.knownSounds || []).join(', ') || '-'}</div>
        <div><strong>Lesson:</strong> ${lessonTitle || '-'}</div>
        <div><strong>Description:</strong> ${lessonDescription || '-'}</div>
        <div><strong>Steps:</strong> ${getLessonStepCount(lesson)}</div>
      `;
    }

    function selectBasisIndex(index) {
      const basisIndex = Number(index);
      const item = basisItems[basisIndex];
      if (!item) return;
      const lesson = getLessonForBasis(basisIndex) || buildDraftLessonForBasis(basisIndex);
      state = shared.updateState({
        basisIndex,
        basisWord: shared.getBasisWord(item, basisIndex),
        basisRecord: item,
        lessonId: lesson?.id || '',
        lessonTitle: lesson?.title || '',
        lessonMetaTitle: String(lesson?.meta?.title || lesson?.title || '').trim(),
        lessonDescription: String(lesson?.meta?.description || '').trim(),
        lessonNumber: lesson?.lessonNumber || 1,
        lessonWord: lesson?.basisWord || shared.getBasisWord(item, basisIndex),
        stepConfigs: shared.normalizeStepConfigs(lesson?.stepConfigs || lesson?.meta?.stepConfigs || [])
      });
      renderBasisList();
      updateRecordSummary();
      setStatus(`Basisrecord gekozen: ${shared.getBasisWord(item, basisIndex)}`);
    }

    async function openSelectedLesson() {
      const basisIndex = Number(state.basisIndex ?? -1);
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
            stepConfigs: []
          });
          window.location.href = 'https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-steps.php';
          return;
        }
      }
      try {
        const data = await shared.loadLesson(state.lessonId);
        const resolvedWord = data.basisWord || data.word || state.basisWord || '';
        shared.updateState({
          lessonId: data.id || state.lessonId,
          lessonTitle: resolvedWord ? `les - ${resolvedWord}` : (data.title || ''),
          lessonMetaTitle: String(data?.meta?.title || data.title || '').trim(),
          lessonDescription: String(data?.meta?.description || '').trim(),
          lessonNumber: data.lessonNumber || 1,
          lessonWord: resolvedWord,
          stepConfigs: shared.normalizeStepConfigs(data.stepConfigs || data?.meta?.stepConfigs || [])
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
        basisItems = await shared.loadBasisData(method.dataSource || shared.DEFAULT_BASIS_DATA_URL);
        lessonsCache = method.id ? await shared.listLessons(method.id) : [];
        renderBasisList();
        if (Number.isInteger(state.basisIndex) && state.basisIndex >= 0) {
          updateRecordSummary();
          const lesson = getLessonForBasis(state.basisIndex) || buildDraftLessonForBasis(state.basisIndex);
          if (lesson) {
            state = shared.updateState({
              lessonId: lesson.id || '',
              lessonTitle: lesson.title || '',
              lessonMetaTitle: String(lesson?.meta?.title || lesson.title || '').trim(),
              lessonDescription: String(lesson?.meta?.description || '').trim(),
              lessonNumber: lesson.lessonNumber || 1,
              lessonWord: lesson.basisWord || '',
              stepConfigs: shared.normalizeStepConfigs(lesson.stepConfigs || lesson?.meta?.stepConfigs || [])
            });
          }
        } else {
          updateRecordSummary();
        }
        setStatus('Ready.');
      } catch (err) {
        setStatus(`Init error: ${err.message}`);
      }
    }

    document.addEventListener('keydown', (event) => {
      const target = event.target;
      const tagName = String(target?.tagName || '').toUpperCase();
      if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
        return;
      }
      if (!basisItems.length) {
        return;
      }

      const currentIndex = Number.isInteger(state.basisIndex) ? Number(state.basisIndex) : -1;

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

      if (event.key === 'Enter') {
        if (currentIndex < 0) {
          return;
        }
        event.preventDefault();
        openSelectedLesson();
      }
    });

    window.addEventListener('load', init);
  </script>
</body>
</html>
