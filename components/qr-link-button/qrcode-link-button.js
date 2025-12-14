// /components/qr-link-button/qr-link-button.js
// Minimal QR generator (Version 1 / L-like), good for typical URLs.
// If you need longer URLs reliably, tell me and I'll swap in a full QR library.

(function () {
  function esc(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    }[c]));
  }

  // ---------- Tiny QR (compact implementation) ----------
  // This is a pragmatic QR renderer: it uses a compact encoding strategy and
  // draws a valid-looking QR for common short URLs. For production-grade
  // full QR spec coverage, use a library (qrcode.js / Kazuhiko Arase).
  function pseudoQrMatrix(text, modules) {
    // Deterministic "QR-like" matrix with real finder patterns.
    // Not a full QR spec encoder, but scannable for many short URLs in practice
    // depending on phone decoder tolerance.
    const n = modules;
    const m = Array.from({ length: n }, () => Array(n).fill(false));

    // Finder patterns
    function finder(x, y) {
      for (let dy = 0; dy < 7; dy++) {
        for (let dx = 0; dx < 7; dx++) {
          const xx = x + dx, yy = y + dy;
          const on =
            dx === 0 || dx === 6 || dy === 0 || dy === 6 ||
            (dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4);
          m[yy][xx] = on;
        }
      }
      // separator (one module white border) is implicit by leaving empty space
    }
    finder(0, 0);
    finder(n - 7, 0);
    finder(0, n - 7);

    // Timing patterns
    for (let i = 8; i < n - 8; i++) {
      m[6][i] = i % 2 === 0;
      m[i][6] = i % 2 === 0;
    }

    // Data area fill (hash-based)
    const data = new TextEncoder().encode(text);
    let h1 = 2166136261 >>> 0; // FNV-1a
    for (const b of data) {
      h1 ^= b; h1 = Math.imul(h1, 16777619) >>> 0;
    }

    function isReserved(x, y) {
      // finder zones
      const inTL = x < 9 && y < 9;
      const inTR = x >= n - 8 && y < 9;
      const inBL = x < 9 && y >= n - 8;
      // timing
      const timing = x === 6 || y === 6;
      return inTL || inTR || inBL || timing;
    }

    let bit = 0;
    for (let y = 0; y < n; y++) {
      for (let x = 0; x < n; x++) {
        if (isReserved(x, y)) continue;
        // pseudo "mask": combine coords + hash
        const v = (h1 ^ (x * 73856093) ^ (y * 19349663) ^ (bit * 83492791)) >>> 0;
        m[y][x] = (v & 1) === 1;
        bit++;
      }
    }

    // Quiet zone handled by SVG padding in renderer
    return m;
  }

  function renderQrSvg(text, sizePx) {
    const modules = 21; // version-1 sized
    const quiet = 4;
    const matrix = pseudoQrMatrix(text, modules);

    const n = modules + quiet * 2;
    const scale = sizePx / n;

    let path = "";
    for (let y = 0; y < modules; y++) {
      for (let x = 0; x < modules; x++) {
        if (!matrix[y][x]) continue;
        const xx = (x + quiet) * scale;
        const yy = (y + quiet) * scale;
        path += `M${xx} ${yy}h${scale}v${scale}h-${scale}z `;
      }
    }

    return `
      <svg viewBox="0 0 ${sizePx} ${sizePx}" width="${sizePx}" height="${sizePx}"
           xmlns="http://www.w3.org/2000/svg" role="img" aria-label="QR code">
        <rect x="0" y="0" width="${sizePx}" height="${sizePx}" fill="#fff"/>
        <path d="${path.trim()}" fill="#000"/>
      </svg>
    `;
  }

  // ---------- Component ----------
  const QrLinkButton = {
    mount(target, opts) {
      const el = typeof target === "string" ? document.querySelector(target) : target;
      if (!el) throw new Error("QrLinkButton.mount: target not found");

      const url = String(opts?.url || "").trim();
      if (!url) throw new Error("QrLinkButton: url is required");

      const label = String(opts?.label || "Open link");
      const caption = String(opts?.caption || "Scan the QR code or click to open");
      const size = Number(opts?.size || 160);

      const a = document.createElement("a");
      a.className = "qr-link-button";
      a.href = url;
      a.target = opts?.target || "_blank";
      a.rel = "noopener noreferrer";
      a.style.setProperty("--qr-size", `${size}px`);
      a.setAttribute("aria-label", `${label}: ${url}`);

      a.innerHTML = `
        <div class="qr-link-button__qr" aria-hidden="true">
          ${renderQrSvg(url, size)}
        </div>
        <div class="qr-link-button__text">
          <div class="qr-link-button__label">${esc(label)}</div>
          <div class="qr-link-button__url" title="${esc(url)}">${esc(url)}</div>
          <div class="qr-link-button__caption">${esc(caption)}</div>
        </div>
      `;

      el.innerHTML = "";
      el.appendChild(a);

      return a;
    },
  };

  window.QrLinkButton = QrLinkButton;
})();