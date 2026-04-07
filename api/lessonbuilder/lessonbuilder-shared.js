(function () {
  const SCRIPT_API_BASES = [
    'https://www.tastenbraille.com/braillestudio/blockly-api',
    '/braillestudio/blockly-api'
  ];
  const LESSON_API_BASES = [
    'https://www.tastenbraille.com/braillestudio/lessons-api',
    '/braillestudio/lessons-api'
  ];
  const METHODS_API_BASES = [
    'https://www.tastenbraille.com/braillestudio/methods-api',
    '/braillestudio/methods-api'
  ];
  const DEFAULT_BASIS_DATA_URL = 'https://www.tastenbraille.com/braillestudio/klanken/aanvankelijklijst.json';
  const STATE_KEY = 'braillestudioLessonBuilderStateV2';
  const AUTH_TOKEN_KEYS = ['braillestudioAuthToken', 'elevenlabsAuthToken'];
  const AUTH_BRIDGE_URL = 'https://www.tastenbraille.com/braillestudio/authentication.html?mode=bridge';
  const basisDataCache = new Map();

  function getAuthToken() {
    for (const key of AUTH_TOKEN_KEYS) {
      const fromSession = String(sessionStorage.getItem(key) || '').trim();
      if (fromSession) return fromSession;
      const fromLocal = String(localStorage.getItem(key) || '').trim();
      if (fromLocal) return fromLocal;
    }
    return '';
  }

  function withAuthHeaders(options = {}) {
    const next = { ...(options || {}) };
    const headers = { ...(next.headers || {}) };
    const token = getAuthToken();
    if (token && !headers.Authorization) {
      headers.Authorization = `Bearer ${token}`;
    }
    next.headers = headers;
    return next;
  }

  function setAuthToken(token) {
    const normalized = String(token || '').trim();
    if (normalized) {
      sessionStorage.setItem('braillestudioAuthToken', normalized);
      localStorage.setItem('braillestudioAuthToken', normalized);
      sessionStorage.setItem('elevenlabsAuthToken', normalized);
      localStorage.setItem('elevenlabsAuthToken', normalized);
    } else {
      sessionStorage.removeItem('braillestudioAuthToken');
      localStorage.removeItem('braillestudioAuthToken');
      sessionStorage.removeItem('elevenlabsAuthToken');
      localStorage.removeItem('elevenlabsAuthToken');
    }
  }

  function openAuthenticationPopup() {
    return new Promise((resolve, reject) => {
      const bridgeUrl = new URL(AUTH_BRIDGE_URL);
      bridgeUrl.searchParams.set('origin', window.location.origin);
      const popup = window.open(
        bridgeUrl.toString(),
        'braillestudioAuthBridge',
        'width=560,height=720,resizable=yes,scrollbars=yes'
      );
      if (!popup) {
        reject(new Error('Popup blocked'));
        return;
      }

      let settled = false;
      const cleanup = () => {
        window.removeEventListener('message', onMessage);
        if (pollTimer) window.clearInterval(pollTimer);
      };

      const onMessage = (event) => {
        if (event.origin !== 'https://www.tastenbraille.com') return;
        if (event.data?.type !== 'braillestudio-auth-token') return;
        const token = String(event.data?.token || '').trim();
        if (!token) return;
        setAuthToken(token);
        settled = true;
        cleanup();
        resolve(token);
      };

      window.addEventListener('message', onMessage);

      const pollTimer = window.setInterval(() => {
        if (popup.closed && !settled) {
          cleanup();
          reject(new Error('Authentication popup closed'));
        }
      }, 250);
    });
  }

  function getApiBases(kind = 'lesson') {
    const bases = kind === 'script'
      ? SCRIPT_API_BASES
      : (kind === 'method' ? METHODS_API_BASES : LESSON_API_BASES);
    return [...new Set(bases)];
  }

  async function apiFetchJson(path, options = {}, kind = 'lesson') {
    const bases = getApiBases(kind);
    const requestOptions = withAuthHeaders(options);
    let lastError = null;
    for (const base of bases) {
      const url = `${base}${path}`;
      try {
        const res = await fetch(url, requestOptions);
        const raw = await res.text();
        let data = null;
        try {
          data = JSON.parse(raw);
        } catch {
          if (!res.ok || /^\s*</.test(raw)) {
            throw new Error(`Non-JSON response from ${url} (HTTP ${res.status})`);
          }
        }
        if (!res.ok) {
          throw new Error((data && data.error) ? data.error : `HTTP ${res.status}`);
        }
        return data;
      } catch (err) {
        lastError = err;
      }
    }
    throw lastError || new Error(`API request failed for ${path}`);
  }

  function loadState() {
    try {
      const raw = localStorage.getItem(STATE_KEY);
      if (!raw) return {};
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
      return {};
    }
  }

  function saveState(nextState) {
    const state = nextState && typeof nextState === 'object' ? nextState : {};
    localStorage.setItem(STATE_KEY, JSON.stringify(state));
    return state;
  }

  function updateState(patch) {
    const next = {
      ...loadState(),
      ...(patch && typeof patch === 'object' ? patch : {})
    };
    return saveState(next);
  }

  function resolveBasisFileUrl(fileName = '') {
    const safeName = String(fileName || '').trim();
    if (!safeName) return DEFAULT_BASIS_DATA_URL;
    return `https://www.tastenbraille.com/braillestudio/klanken/${encodeURIComponent(safeName)}`;
  }

  function resolveMethodDataSource(dataSource, methodId = '', basisFile = '') {
    const source = String(dataSource || '').trim();
    if (basisFile) return resolveBasisFileUrl(basisFile);
    if (!source) return DEFAULT_BASIS_DATA_URL;
    if (/^https?:\/\//i.test(source)) return source;
    if (source.startsWith('/')) return `https://www.tastenbraille.com${source}`;
    if (source.includes('aanvankelijklijst.json') || String(methodId || '').trim() === 'aanvankelijk') {
      return DEFAULT_BASIS_DATA_URL;
    }
    return `https://www.tastenbraille.com/braillestudio/${source.replace(/^\.?\/*/, '')}`;
  }

  async function listMethods() {
    const data = await apiFetchJson('/list_methods.php', {}, 'method');
    return Array.isArray(data.items) ? data.items : [];
  }

  async function loadMethod(id) {
    const data = await apiFetchJson(`/load_method.php?id=${encodeURIComponent(id)}`, {}, 'method');
    return data.item || null;
  }

  async function saveMethod(payload) {
    return apiFetchJson('/save_method.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }, 'method');
  }

  async function deleteMethod(id) {
    return apiFetchJson('/delete_method.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    }, 'method');
  }

  async function listBasisFiles() {
    try {
      const data = await apiFetchJson('/list_basis_files.php', {}, 'method');
      const items = Array.isArray(data.items) ? data.items : [];
      if (items.length > 0) return items;
    } catch {}
    return [{
      id: 'aanvankelijklijst.json',
      name: 'aanvankelijklijst.json',
      label: 'aanvankelijklijst.json',
      url: DEFAULT_BASIS_DATA_URL
    }];
  }

  async function listLessons(methodId = '') {
    const suffix = methodId ? `?methodId=${encodeURIComponent(methodId)}` : '';
    const data = await apiFetchJson(`/list_lessons.php${suffix}`, {}, 'lesson');
    return Array.isArray(data.items) ? data.items : [];
  }

  async function loadLesson(id) {
    return apiFetchJson(`/load_lesson.php?id=${encodeURIComponent(id)}`, {}, 'lesson');
  }

  async function saveLesson(payload) {
    return apiFetchJson('/save_lesson.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }, 'lesson');
  }

  async function deleteLesson(id) {
    return apiFetchJson('/delete_lesson.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    }, 'lesson');
  }

  async function listScripts() {
    const data = await apiFetchJson('/list.php', {}, 'script');
    return Array.isArray(data.items) ? data.items : [];
  }

  async function loadScript(id) {
    return apiFetchJson(`/load.php?id=${encodeURIComponent(id)}`, {}, 'script');
  }

  async function loadBasisData(dataSource) {
    const source = String(dataSource || '').trim();
    if (!source) return [];
    if (basisDataCache.has(source)) return basisDataCache.get(source) || [];

    const fileName = source.split('/').pop() || 'aanvankelijklijst.json';
    const candidates = [
      source,
      `/braillestudio/klanken/${fileName}`,
      `../klanken/${fileName}`,
      `./../klanken/${fileName}`,
      `https://www.tastenbraille.com/braillestudio/klanken/${fileName}`
    ];
    let lastError = null;
    for (const candidate of [...new Set(candidates)]) {
      try {
        const res = await fetch(candidate, { cache: 'no-store' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const raw = await res.json();
        const items = Array.isArray(raw) ? raw : (Array.isArray(raw?.items) ? raw.items : []);
        basisDataCache.set(source, items);
        return items;
      } catch (err) {
        lastError = err;
      }
    }
    throw new Error(`Basisbestand laden mislukt (${fileName}): ${lastError?.message || 'unknown error'}`);
  }

  function normalizeLetters(value) {
    if (Array.isArray(value)) {
      return value.map((item) => String(item ?? '').trim()).filter(Boolean);
    }
    return String(value ?? '').split(',').map((item) => item.trim()).filter(Boolean);
  }

  function normalizeInputs(inputs = {}, fallbackVariable = '') {
    const source = inputs && typeof inputs === 'object' ? inputs : {};
    const repeatValue = Math.max(1, Math.floor(Number(source.repeat ?? 1) || 1));
    const normalized = {
      text: String(source.text ?? '').trim(),
      word: String(source.word ?? '').trim(),
      letters: normalizeLetters(source.letters ?? []),
      repeat: repeatValue
    };
    if (!normalized.text && !normalized.word && normalized.letters.length === 0 && repeatValue === 1 && String(fallbackVariable || '').trim()) {
      normalized.text = String(fallbackVariable).trim();
    }
    return normalized;
  }

  function normalizeStepConfigs(configs) {
    if (!Array.isArray(configs)) return [];
    return configs.map((cfg) => ({
      id: String(cfg?.id ?? '').trim(),
      title: String(cfg?.title ?? cfg?.scriptTitle ?? '').trim(),
      description: String(cfg?.description ?? cfg?.scriptDescription ?? cfg?.meta?.description ?? '').trim(),
      inputs: normalizeInputs(cfg?.inputs ?? {}, cfg?.variable ?? '')
    })).filter((cfg) => cfg.id);
  }

  function getBasisWord(item, fallbackIndex = 0) {
    const word = String(item?.word || '').trim();
    return word || `item-${fallbackIndex + 1}`;
  }

  function slugifyLessonPart(value) {
    return String(value ?? '').trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
  }

  function buildLessonIdFromBasis(methodId, basisIndex, basisItem, lessonNumber = 1) {
    const prefix = slugifyLessonPart(methodId || 'lesson');
    const word = slugifyLessonPart(getBasisWord(basisItem, basisIndex));
    const recordNumber = String(Math.max(0, Number(basisIndex) + 1)).padStart(3, '0');
    const slotNumber = String(Math.max(1, Number(lessonNumber) || 1)).padStart(2, '0');
    return `${prefix}-${recordNumber}-${word}-lesson-${slotNumber}`;
  }

  function buildLessonTitleFromBasis(basisItem, lessonNumber = 1, basisIndex = 0) {
    return `les - ${getBasisWord(basisItem, basisIndex)}`;
  }

  function getLessonsForBasis(lessons, basisIndex) {
    return (Array.isArray(lessons) ? lessons : [])
      .filter((item) => Number(item?.basisIndex ?? -1) === Number(basisIndex))
      .sort((a, b) => Number(a?.lessonNumber ?? 1) - Number(b?.lessonNumber ?? 1));
  }

  function getNextLessonNumber(lessons, basisIndex) {
    const existing = getLessonsForBasis(lessons, basisIndex);
    return existing.reduce((max, item) => Math.max(max, Number(item?.lessonNumber ?? 1)), 0) + 1;
  }

  function getDraftMethodMeta(state = loadState()) {
    const basisFile = String(state.methodBasisFile || '').trim();
    return {
      id: String(state.methodId || '').trim(),
      title: String(state.methodTitle || '').trim(),
      description: String(state.methodDescription || '').trim(),
      imageUrl: String(state.methodImageUrl || '').trim(),
      basisFile,
      dataSource: resolveMethodDataSource(String(state.methodDataSource || '').trim(), String(state.methodId || '').trim(), basisFile)
    };
  }

  window.LessonBuilderShared = {
    DEFAULT_BASIS_DATA_URL,
    getAuthToken,
    setAuthToken,
    openAuthenticationPopup,
    loadState,
    saveState,
    updateState,
    apiFetchJson,
    listMethods,
    loadMethod,
    saveMethod,
    deleteMethod,
    listBasisFiles,
    listLessons,
    loadLesson,
    saveLesson,
    deleteLesson,
    listScripts,
    loadScript,
    loadBasisData,
    resolveBasisFileUrl,
    resolveMethodDataSource,
    normalizeInputs,
    normalizeStepConfigs,
    getBasisWord,
    buildLessonIdFromBasis,
    buildLessonTitleFromBasis,
    getLessonsForBasis,
    getNextLessonNumber,
    getDraftMethodMeta
  };
})();
