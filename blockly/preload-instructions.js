(function () {
  const REMOTE_URL = 'https://www.tastenbraille.com/braillestudio/instructions-api/instructions_catalog_js.php?status=active';

  if (!Array.isArray(window.BrailleStudioInstructionCatalog)) {
    window.BrailleStudioInstructionCatalog = [];
  }
  window.BrailleStudioInstructionCatalogMeta = {
    ...(window.BrailleStudioInstructionCatalogMeta || {}),
    requestedRemoteUrl: REMOTE_URL
  };

  async function fetchInstructionList() {
    try {
      await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `${REMOTE_URL}?_=${Date.now()}`;
        script.onload = resolve;
        script.onerror = () => reject(new Error('Script load failed'));
        document.head.appendChild(script);
      });
      return Array.isArray(window.BrailleStudioInstructionCatalog)
        ? window.BrailleStudioInstructionCatalog
        : [];
    } catch (lastError) {
      window.BrailleStudioInstructionCatalogMeta = {
        sourceUrl: 'fallback',
        count: window.BrailleStudioInstructionCatalog.length,
        loadedAt: new Date().toISOString(),
        error: lastError && lastError.message ? lastError.message : String(lastError || 'unknown error')
      };
      return window.BrailleStudioInstructionCatalog;
    }
  }

  window.BrailleStudioInstructionCatalogReady = fetchInstructionList();
})();
