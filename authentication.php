<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = $scriptDir;

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$jsValue = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BrailleStudio Login</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body d-flex align-items-center justify-content-center min-vh-100">
  <div class="container container-tight py-4 w-100">
      <div class="text-center mb-4">
        <a class="navbar-brand navbar-brand-autodark justify-content-center" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2"><i class="ti ti-braille text-primary" aria-hidden="true"></i></span>
          <span>BrailleStudio</span>
        </a>
      </div>

      <div class="card card-md">
        <div class="card-body">
          <h1 class="h2 text-center mb-2">BrailleStudio Login</h1>
          <p id="pageSubtitle" class="text-secondary text-center mb-4">Log in om beveiligde onderdelen van BrailleStudio te gebruiken.</p>

          <div class="mb-3">
            <label class="form-label" for="authUsernameInput">Username</label>
            <input id="authUsernameInput" class="form-control" type="text" placeholder="Username" autocomplete="username">
          </div>
          <div class="mb-3">
            <label class="form-label" for="authPasswordInput">Password</label>
            <input id="authPasswordInput" class="form-control" type="password" placeholder="Password" autocomplete="current-password">
          </div>

          <div class="btn-list justify-content-center mb-3">
            <button id="authLoginBtn" class="btn btn-primary" type="button">
              <i class="ti ti-login me-2" aria-hidden="true"></i>
              Log in
            </button>
            <button id="authLogoutBtn" class="btn btn-outline-secondary" type="button">
              <i class="ti ti-logout me-2" aria-hidden="true"></i>
              Log out
            </button>
          </div>

          <div id="authStatus" class="alert alert-secondary mb-0">Not logged in.</div>
        </div>
      </div>

      <div class="text-center text-secondary mt-3">
        Na het inloggen ga je automatisch terug naar de pagina die je wilde openen.
      </div>
      <div class="text-center mt-3">
        <a class="btn btn-link" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">Terug naar home</a>
      </div>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script>
  (function () {
    "use strict";

    const AUTH_BASE_PATH = <?= $jsValue($urlFor($appBase, 'authentication-api/')) ?>;
    const AUTH_TOKEN_KEY = "braillestudioAuthToken";
    const LEGACY_ELEVENLABS_AUTH_TOKEN_KEY = "elevenlabsAuthToken";
    const AUTH_AUDIENCE = "braillestudio-api";
    const HOMEPAGE_ORIGIN = "https://www.tastenbraille.com";
    const params = new URLSearchParams(window.location.search);
    const bridgeMode = params.get("mode") === "bridge";
    const requestedOrigin = String(params.get("origin") || "").trim();
    const returnTo = normalizeReturnTo(params.get("returnTo")) || (bridgeMode ? "" : normalizeReturnTo("index.php"));

    function normalizeReturnTo(raw) {
      const value = String(raw || "").trim();
      if (!value) return "";
      try {
        const url = new URL(value, window.location.href);
        if (url.origin !== window.location.origin && url.origin !== HOMEPAGE_ORIGIN) return "";
        return url.toString();
      } catch {
        return "";
      }
    }

    function getAuthBaseUrl() {
      return new URL(AUTH_BASE_PATH, window.location.origin).toString();
    }

    function getAuthEndpointUrl(fileName) {
      return new URL(fileName, getAuthBaseUrl()).toString();
    }

    function getStoredAuthToken() {
      const fromSession = String(sessionStorage.getItem(AUTH_TOKEN_KEY) || "").trim();
      if (fromSession) return fromSession;
      const fromLocal = String(localStorage.getItem(AUTH_TOKEN_KEY) || "").trim();
      if (fromLocal) return fromLocal;
      const legacySession = String(sessionStorage.getItem(LEGACY_ELEVENLABS_AUTH_TOKEN_KEY) || "").trim();
      if (legacySession) return legacySession;
      return String(localStorage.getItem(LEGACY_ELEVENLABS_AUTH_TOKEN_KEY) || "").trim();
    }

    function setStoredAuthToken(token) {
      const normalized = String(token || "").trim();
      if (normalized) {
        sessionStorage.setItem(AUTH_TOKEN_KEY, normalized);
        localStorage.setItem(AUTH_TOKEN_KEY, normalized);
        sessionStorage.setItem(LEGACY_ELEVENLABS_AUTH_TOKEN_KEY, normalized);
        localStorage.setItem(LEGACY_ELEVENLABS_AUTH_TOKEN_KEY, normalized);
      } else {
        sessionStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(AUTH_TOKEN_KEY);
        sessionStorage.removeItem(LEGACY_ELEVENLABS_AUTH_TOKEN_KEY);
        localStorage.removeItem(LEGACY_ELEVENLABS_AUTH_TOKEN_KEY);
      }
    }

    function renderAuthStatus(message = "", isError = false) {
      const status = document.getElementById("authStatus");
      const logoutBtn = document.getElementById("authLogoutBtn");
      if (!status) return;
      const token = getStoredAuthToken();
      status.textContent = message || (token ? "Authenticated." : "Not logged in.");
      status.className = isError
        ? "alert alert-danger mb-0"
        : token
          ? "alert alert-success mb-0"
          : "alert alert-secondary mb-0";
      if (logoutBtn) logoutBtn.disabled = !token;
    }

    function publishBridgeToken(token, audience, username) {
      if (!bridgeMode || !window.opener || !token) return;
      const payload = {
        type: "braillestudio-auth-token",
        token,
        audience: String(audience || "").trim(),
        username: String(username || "").trim()
      };
      const targetOrigin = requestedOrigin || "*";
      window.opener.postMessage(payload, targetOrigin);
      renderAuthStatus("Authentication completed. This window closes automatically.");
      setTimeout(() => {
        window.close();
      }, 250);
    }

    async function loginAuth() {
      const usernameInput = document.getElementById("authUsernameInput");
      const passwordInput = document.getElementById("authPasswordInput");
      const loginBtn = document.getElementById("authLoginBtn");
      const username = String(usernameInput?.value || "").trim();
      const password = String(passwordInput?.value || "");
      const audience = AUTH_AUDIENCE;

      if (!username || !password) {
        renderAuthStatus("Enter username and password first.", true);
        return;
      }

      if (loginBtn) loginBtn.disabled = true;
      renderAuthStatus("Logging in...");

      try {
        const res = await fetch(getAuthEndpointUrl("login.php"), {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            username,
            password,
            audience
          })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data?.ok || !data?.token) {
          throw new Error(data?.error || `HTTP ${res.status}`);
        }
        setStoredAuthToken(data.token);
        if (passwordInput) passwordInput.value = "";
        renderAuthStatus(`Authenticated as ${String(data?.user?.username || username)}.`);
        publishBridgeToken(data.token, audience, String(data?.user?.username || username));
        if (!bridgeMode && returnTo) {
          renderAuthStatus(`Authenticated as ${String(data?.user?.username || username)}. Redirecting...`);
          window.setTimeout(() => {
            window.location.assign(returnTo);
          }, 250);
        }
      } catch (error) {
        console.error("[authentication] login failed", error);
        renderAuthStatus(`Login failed: ${error.message || error}`, true);
      } finally {
        if (loginBtn) loginBtn.disabled = false;
      }
    }

    function logoutAuth() {
      setStoredAuthToken("");
      renderAuthStatus("Logged out.");
    }

    document.getElementById("authLoginBtn")?.addEventListener("click", loginAuth);
    document.getElementById("authLogoutBtn")?.addEventListener("click", logoutAuth);
    document.getElementById("authPasswordInput")?.addEventListener("keydown", async (event) => {
      if (event.key !== "Enter") return;
      event.preventDefault();
      await loginAuth();
    });

    const subtitle = document.getElementById("pageSubtitle");
    if (bridgeMode && subtitle) {
      subtitle.textContent = requestedOrigin
        ? `Log in voor ${requestedOrigin}. Daarna ga je automatisch terug.`
        : "Log in en ga daarna automatisch terug.";
    }

    renderAuthStatus();
  })();
  </script>
</body>
</html>
