(function () {
  const REMOTE_URL = 'https://www.tastenbraille.com/braillestudio/blockly-api/instructions_list.php';
  const CANDIDATE_URLS = [
    `../api/fetch-remote.php?url=${encodeURIComponent(REMOTE_URL)}`,
    `/braillestudio/api/fetch-remote.php?url=${encodeURIComponent(REMOTE_URL)}`,
    `https://www.tastenbraille.com/braillestudio/api/fetch-remote.php?url=${encodeURIComponent(REMOTE_URL)}`,
    REMOTE_URL,
    '/braillestudio/blockly-api/instructions_list.php',
    '../api/blockly-api/instructions_list.php',
    '/braillestudio/instructions-api/instructions_list.php',
    '../api/instructions-api/instructions_list.php'
  ];

  if (!Array.isArray(window.BrailleStudioInstructionCatalog)) {
    window.BrailleStudioInstructionCatalog = [];
  }

  async function fetchInstructionList() {
    let lastError = null;

    for (const url of CANDIDATE_URLS) {
      try {
        const response = await fetch(url, { cache: 'no-store' });
        const bodyText = await response.text();
        if (!response.ok) {
          throw new Error(`HTTP ${response.status} ${response.statusText}`);
        }
        const parsed = JSON.parse(bodyText);
        const items = Array.isArray(parsed) ? parsed : parsed?.items;
        if (!Array.isArray(items)) {
          throw new Error('Expected instruction list array');
        }
        window.BrailleStudioInstructionCatalog = items;
        window.BrailleStudioInstructionCatalogMeta = {
          sourceUrl: url,
          count: items.length,
          loadedAt: new Date().toISOString()
        };
        return items;
      } catch (err) {
        lastError = err;
      }
    }

    window.BrailleStudioInstructionCatalogMeta = {
      sourceUrl: 'fallback',
      count: window.BrailleStudioInstructionCatalog.length,
      loadedAt: new Date().toISOString(),
      error: lastError && lastError.message ? lastError.message : String(lastError || 'unknown error')
    };
    return window.BrailleStudioInstructionCatalog;
  }

  window.BrailleStudioInstructionCatalogReady = fetchInstructionList();
})();
