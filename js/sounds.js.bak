let Sounds = {
  config: null,

  async loadConfig() {
    const jsonPath = "config/sounds.json";

    // Detect the root URL where index.html is running
    const rootPath =
      window.location.origin +
      window.location.pathname.replace(/\/[^\/]*$/, "/");

    logMessage("🌐 Root path: " + rootPath);
    logMessage("📄 Loading config from: " + jsonPath);
    logMessage("➡️ Full request URL: " + rootPath + jsonPath);

    try {
      const response = await fetch(jsonPath);

      logMessage("↩️ Response status: " + response.status + " " + response.statusText);

      if (!response.ok) {
        logMessage("❌ ERROR: Could not load sounds.json from " + response.url);
        return;
      }

      this.config = await response.json();
      logMessage("✅ sounds.json loaded successfully.");
      logMessage("🔗 baseUrl: " + this.config.baseUrl);

    } catch (error) {
      logMessage("❌ ERROR reading sounds.json: " + error);
    }
  },

  getUrl(path) {
    return this.config.baseUrl + path;
  },

  play(lang, category, name) {
    if (!this.config) {
      logMessage("❌ ERROR: Sounds not loaded.");
      return;
    }

    const relativePath = this.config.languages[lang][category][name];
    const fullUrl = this.getUrl(relativePath);

    logMessage("▶️ Playing file: " + relativePath);
    logMessage("🌐 Full URL:     " + fullUrl);

    const sound = new Howl({
      src: [fullUrl],
      html5: true,      // stream via HTML5 audio (more robust cross-domain)
      volume: 1.0,
      onload: () => {
        logMessage("✅ Audio loaded OK.");
      },
      onloaderror: (id, err) => {
        logMessage("❌ onloaderror: " + err);
        console.error("Howler onloaderror", id, err);
      },
      onplayerror: (id, err) => {
        logMessage("❌ onplayerror: " + err);
        console.error("Howler onplayerror", id, err);
      }
    });

    sound.play();
  },

  playShared(name) {
    if (!this.config) {
      logMessage("❌ ERROR: Sounds not loaded.");
      return;
    }

    const relativePath = this.config.shared[name];
    const fullUrl = this.getUrl(relativePath);

    logMessage("▶️ Playing shared: " + relativePath);
    logMessage("🌐 Full URL:       " + fullUrl);

    const sound = new Howl({
      src: [fullUrl],
      html5: true,
      volume: 1.0,
      onload: () => {
        logMessage("✅ Shared audio loaded OK.");
      },
      onloaderror: (id, err) => {
        logMessage("❌ Shared onloaderror: " + err);
        console.error("Howler shared onloaderror", id, err);
      },
      onplayerror: (id, err) => {
        logMessage("❌ Shared onplayerror: " + err);
        console.error("Howler shared onplayerror", id, err);
      }
    });

    sound.play();
  }
};

(async () => {
  await Sounds.loadConfig();
})();
