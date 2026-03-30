(function () {
  const DEFAULT_BASE_URL = 'https://tastenbraille.com/api/list.php';
  const INSTRUCTIONS_API_BASE_URLS = [
    'https://www.tastenbraille.com/braillestudio/instructions-api',
    '/braillestudio/instructions-api',
    '../api/instructions-api'
  ];
  const INSTRUCTIONS_API_PROXY_URLS = [
    '../api/fetch-remote.php',
    '/braillestudio/api/fetch-remote.php',
    'https://www.tastenbraille.com/braillestudio/api/fetch-remote.php'
  ];
  const INSTRUCTIONS_SOUND_BASE_URL = 'https://www.tastenbraille.com/braillestudio/sounds/nl/instructions/';
  const INSTRUCTION_AUDIO_FOLDER_URLS = {
    instructions: 'https://www.tastenbraille.com/braillestudio/sounds/nl/instructions/',
    phonemes: 'https://www.tastenbraille.com/braillestudio/sounds/nl/letters/',
    letters: 'https://www.tastenbraille.com/braillestudio/sounds/nl/letters/',
    feedback: 'https://www.tastenbraille.com/braillestudio/sounds/nl/feedback/',
    story: 'https://www.tastenbraille.com/braillestudio/sounds/nl/stories/',
    stories: 'https://www.tastenbraille.com/braillestudio/sounds/nl/stories/',
    general: 'https://www.tastenbraille.com/braillestudio/sounds/general/',
    speech: 'https://www.tastenbraille.com/braillestudio/sounds/nl/speech/'
  };
  const instructionCache = new Map();

  function getInstructionCatalogItems() {
    return Array.isArray(window.BrailleStudioInstructionCatalog)
      ? window.BrailleStudioInstructionCatalog
      : [];
  }

  function isEmpty(value) {
    return value === null || value === undefined || String(value).trim() === '';
  }

  function normalizeCsv(value) {
    if (Array.isArray(value)) {
      return value
        .map(v => String(v ?? '').trim())
        .filter(Boolean)
        .join(',');
    }
    return String(value ?? '')
      .split(',')
      .map(v => v.trim())
      .filter(Boolean)
      .join(',');
  }

  function joinCsv(parts) {
    return normalizeCsv(Array.isArray(parts) ? parts : [parts]);
  }

  async function getAudioList(options = {}) {
    const baseUrl = options.baseUrl || DEFAULT_BASE_URL;
    const folder = String(options.folder || '').trim();
    if (!folder) {
      throw new Error('BrailleStudioAPI.getAudioList: "folder" is required');
    }

    const params = new URLSearchParams();
    params.set('folder', folder);

    const stringKeys = ['letters', 'klanken', 'onlyletters', 'onlyklanken', 'sort'];
    for (const key of stringKeys) {
      if (!isEmpty(options[key])) {
        params.set(key, normalizeCsv(options[key]));
      }
    }

    const intKeys = ['maxlength', 'length', 'limit', 'randomlimit'];
    for (const key of intKeys) {
      if (!isEmpty(options[key])) {
        const num = Number(options[key]);
        if (!Number.isFinite(num)) {
          throw new Error(`BrailleStudioAPI.getAudioList: "${key}" must be a number`);
        }
        params.set(key, String(Math.floor(num)));
      }
    }

    if (typeof options.onlycombo === 'boolean') {
      params.set('onlycombo', options.onlycombo ? 'true' : 'false');
    }

    const url = `${baseUrl}?${params.toString()}`;

    let response;
    try {
      response = await fetch(url);
    } catch (err) {
      const detail = err && err.message ? err.message : String(err);
      throw new Error(`BrailleStudioAPI.getAudioList: fetch failed for ${url}: ${detail}`);
    }

    const bodyText = await response.text();
    if (!response.ok) {
      throw new Error(`BrailleStudioAPI.getAudioList: HTTP ${response.status} ${response.statusText} for ${url}: ${bodyText.slice(0, 300)}`);
    }

    let parsed;
    try {
      parsed = JSON.parse(bodyText);
    } catch (err) {
      throw new Error(`BrailleStudioAPI.getAudioList: invalid JSON from ${url}`);
    }

    if (!Array.isArray(parsed)) {
      throw new Error('BrailleStudioAPI.getAudioList: expected JSON array');
    }
    return parsed;
  }

  async function fetchJsonFromCandidates(urls, errorPrefix) {
    let lastError = null;
    for (const url of urls) {
      try {
        const response = await fetch(url, { cache: 'no-store' });
        const bodyText = await response.text();
        if (!response.ok) {
          throw new Error(`HTTP ${response.status} ${response.statusText}: ${bodyText.slice(0, 300)}`);
        }
        try {
          return JSON.parse(bodyText);
        } catch {
          throw new Error(`invalid JSON from ${url}`);
        }
      } catch (err) {
        lastError = err;
      }
    }
    const detail = lastError && lastError.message ? lastError.message : String(lastError || 'unknown error');
    throw new Error(`${errorPrefix}: ${detail}`);
  }

  function pickRandom(list) {
    if (!Array.isArray(list) || list.length === 0) return null;
    return list[Math.floor(Math.random() * list.length)];
  }

  function resolveInstructionAudioUrl(input) {
    const raw = String(input ?? '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    const normalized = raw.replace(/^\/+/, '');
    const match = normalized.match(/^([^/]+)\/(.+)$/);
    if (!match) {
      return INSTRUCTIONS_SOUND_BASE_URL + normalized.split('/').map(encodeURIComponent).join('/');
    }
    const [, folder, rest] = match;
    const baseUrl = INSTRUCTION_AUDIO_FOLDER_URLS[String(folder).toLowerCase()] || INSTRUCTIONS_SOUND_BASE_URL;
    return baseUrl + String(rest).split('/').map(encodeURIComponent).join('/');
  }

  function applyInstructionAudioOverrides(input, options = {}) {
    const raw = String(input ?? '').trim();
    if (!raw) return '';
    const phoneme = String(options.phoneme ?? '').trim();
    if (!phoneme) return raw;
    const normalized = raw.replace(/^\/+/, '');
    const match = normalized.match(/^([^/]+)\/(.+)$/);
    if (!match) return raw;
    const [, folder, rest] = match;
    const folderKey = String(folder).toLowerCase();
    if (folderKey !== 'phonemes' && folderKey !== 'phonems') return raw;
    const suffix = String(rest).toLowerCase().endsWith('.mp3') ? '.mp3' : '';
    const nextFile = String(phoneme).toLowerCase().endsWith('.mp3') ? String(phoneme) : `${phoneme}${suffix || '.mp3'}`;
    return `phonemes/${nextFile}`;
  }

  async function getInstructionsList(options = {}) {
    const baseUrls = options.baseUrl
      ? [String(options.baseUrl)]
      : INSTRUCTIONS_API_BASE_URLS.map(base => `${base}/instructions_list.php`);
    const params = new URLSearchParams();
    if (!isEmpty(options.status)) params.set('status', String(options.status).trim());
    if (!isEmpty(options.q)) params.set('q', String(options.q).trim());
    if (!isEmpty(options.tag)) params.set('tag', String(options.tag).trim());
    const remoteUrl = params.toString() ? `${baseUrls[0]}?${params.toString()}` : baseUrls[0];
    const urls = [
      ...INSTRUCTIONS_API_PROXY_URLS.map(url => `${url}?url=${encodeURIComponent(remoteUrl)}`),
      remoteUrl,
      ...baseUrls.slice(1).map(url => params.toString() ? `${url}?${params.toString()}` : url)
    ];
    const parsed = await fetchJsonFromCandidates(urls, 'BrailleStudioAPI.getInstructionsList');

    if (Array.isArray(parsed)) {
      return parsed;
    }
    if (!parsed || typeof parsed !== 'object' || !Array.isArray(parsed.items)) {
      throw new Error('BrailleStudioAPI.getInstructionsList: expected { items: [] }');
    }
    return parsed.items;
  }

  async function getInstructionById(id, options = {}) {
    const instructionId = String(id ?? '').trim();
    if (!instructionId) {
      throw new Error('BrailleStudioAPI.getInstructionById: "id" is required');
    }

    if (instructionCache.has(instructionId) && options.cache !== 'no-store') {
      return instructionCache.get(instructionId);
    }

    const baseUrls = options.baseUrl
      ? [String(options.baseUrl)]
      : INSTRUCTIONS_API_BASE_URLS.map(base => `${base}/instructions_get.php`);
    const remoteUrl = `${baseUrls[0]}?id=${encodeURIComponent(instructionId)}`;
    const urls = [
      ...INSTRUCTIONS_API_PROXY_URLS.map(url => `${url}?url=${encodeURIComponent(remoteUrl)}`),
      remoteUrl,
      ...baseUrls.slice(1).map(url => `${url}?id=${encodeURIComponent(instructionId)}`)
    ];
    let parsed = null;
    try {
      parsed = await fetchJsonFromCandidates(urls, `BrailleStudioAPI.getInstructionById("${instructionId}")`);
    } catch (err) {
      const fallbackItem = getInstructionCatalogItems().find((item) => String(item?.id ?? '').trim() === instructionId);
      if (fallbackItem) {
        instructionCache.set(instructionId, fallbackItem);
        return fallbackItem;
      }
      throw err;
    }

    const item = parsed?.item;
    if (!item || typeof item !== 'object') {
      throw new Error(`BrailleStudioAPI.getInstructionById: instruction not found for "${instructionId}"`);
    }
    instructionCache.set(instructionId, item);
    return item;
  }

  async function playInstructionById(id, options = {}) {
    const item = await getInstructionById(id, options);
    const mode = String(item.audioMode || 'single_mp3').trim();

    if (mode === 'playlist') {
      const playlist = Array.isArray(item.audioPlaylist) ? item.audioPlaylist : [];
      for (const entry of playlist) {
        const url = resolveInstructionAudioUrl(applyInstructionAudioOverrides(entry, options));
        if (!url) continue;
        await playUrl(url);
      }
      return item;
    }

    const url = resolveInstructionAudioUrl(applyInstructionAudioOverrides(item.audioRef, options));
    if (!url) {
      throw new Error(`BrailleStudioAPI.playInstructionById: instruction "${String(id ?? '').trim()}" has no playable audio`);
    }
    await playUrl(url);
    return item;
  }

  let playUrlImpl = null;

  function setPlayHandler(fn) {
    playUrlImpl = typeof fn === 'function' ? fn : null;
  }

  async function playUrl(url) {
    const target = String(url ?? '').trim();
    if (!target) return;

    if (playUrlImpl) {
      await playUrlImpl(target);
      return;
    }

    const audio = new Audio(target);
    await new Promise((resolve, reject) => {
      audio.onended = resolve;
      audio.onerror = () => reject(new Error('Audio playback failed'));
      audio.play().catch(reject);
    });
  }

  window.BrailleStudioAPI = {
    DEFAULT_BASE_URL,
    INSTRUCTIONS_API_BASE_URLS,
    getAudioList,
    getInstructionsList,
    getInstructionById,
    pickRandom,
    applyInstructionAudioOverrides,
    playInstructionById,
    playUrl,
    resolveInstructionAudioUrl,
    setPlayHandler,
    joinCsv
  };
})();
