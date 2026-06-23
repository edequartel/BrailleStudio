(() => {
  'use strict';

  const ACCESS_ATTRIBUTE = 'data-logging-access';
  const HIDDEN_VALUE = 'restricted';
  const ALLOWED_ROLES = new Set(['admin', 'developer']);
  const scriptUrl = new URL(document.currentScript?.src || '', document.baseURI);
  const scriptPathMarker = '/components/log-visibility/';
  const markerIndex = scriptUrl.pathname.indexOf(scriptPathMarker);
  const appBasePath = markerIndex >= 0 ? scriptUrl.pathname.slice(0, markerIndex) : '';
  const SESSION_URL = `${appBasePath}/api/authentication-api/session.php`;
  const LOG_TITLES = new Set(['log', 'logging', 'debug log']);
  const DIRECT_LOG_SELECTORS = [
    '#log',
    '#logBox',
    '#log-panel',
    '#eventLog',
    '#runLogBox',
    '#wsLog',
    '.run-log-panel',
    '[data-admin-developer-only="logging"]',
  ];
  const LOG_CONTROL_SELECTORS = [
    '#toggleLogBtn',
    '#copyLogBtn',
    '#clearLogBtn',
    '#logToggleBtn',
    '#toggleDebugLogBtn',
    '#copyDebugLogBtn',
    '#clearDebugLogBtn',
    '#toggle-log-btn',
    '#copy-log-btn',
    '#clear-log-btn',
  ];

  const style = document.createElement('style');
  style.id = 'brailleStudioLoggingAccessStyle';
  style.textContent = `[${ACCESS_ATTRIBUTE}="${HIDDEN_VALUE}"] { display: none !important; }`;
  (document.head || document.documentElement).appendChild(style);

  function normalizeTitle(value) {
    return String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
  }

  function loggingContainer(element) {
    return element.closest('.card, section, .log-panel, .event-log, .run-log-panel')
      || element;
  }

  function restrict(element) {
    if (element instanceof Element) {
      element.setAttribute(ACCESS_ATTRIBUTE, HIDDEN_VALUE);
    }
  }

  function findLoggingUi() {
    DIRECT_LOG_SELECTORS.forEach((selector) => {
      document.querySelectorAll(selector).forEach((element) => restrict(loggingContainer(element)));
    });

    document.querySelectorAll('h1, h2, h3, h4, .card-title').forEach((heading) => {
      if (LOG_TITLES.has(normalizeTitle(heading.textContent))) {
        restrict(loggingContainer(heading));
      }
    });

    LOG_CONTROL_SELECTORS.forEach((selector) => {
      document.querySelectorAll(selector).forEach(restrict);
    });
  }

  function allowLogging() {
    document.querySelectorAll(`[${ACCESS_ATTRIBUTE}="${HIDDEN_VALUE}"]`).forEach((element) => {
      element.removeAttribute(ACCESS_ATTRIBUTE);
    });
  }

  async function applyRoleAccess() {
    findLoggingUi();

    try {
      const response = await fetch(SESSION_URL, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) return;

      const session = await response.json();
      const role = String(session?.role || '');
      if (session?.can_view_logging === true && ALLOWED_ROLES.has(role)) {
        allowLogging();
      }
    } catch (error) {
      // Fail closed: logging remains hidden when session access cannot be verified.
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyRoleAccess, { once: true });
  } else {
    applyRoleAccess();
  }
})();
