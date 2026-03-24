(function () {
  const DEFAULT_BASE_URL = 'https://tastenbraille.com/api/list.php';

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

  function pickRandom(list) {
    if (!Array.isArray(list) || list.length === 0) return null;
    return list[Math.floor(Math.random() * list.length)];
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
    getAudioList,
    pickRandom,
    playUrl,
    setPlayHandler,
    joinCsv
  };
})();
