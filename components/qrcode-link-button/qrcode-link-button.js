/* -------------------------------------------------------
   QRLinkButton
------------------------------------------------------- */

(function () {
  class QRLinkButton {
    constructor(button) {
      this.button = button;
      this.url = button.dataset.url;

      if (!this.url) {
        console.warn("QRLinkButton: data-url is missing", button);
        return;
      }

      this._bind();
    }

    _bind() {
      this.button.addEventListener("click", () => {
        this.open();
      });
    }

    open() {
      window.open(this.url, "_blank", "noopener,noreferrer");
    }
  }

  // Auto-bind all QR link buttons
  window.addEventListener("DOMContentLoaded", () => {
    document
      .querySelectorAll(".qr-link-button")
      .forEach((btn) => new QRLinkButton(btn));
  });

  window.QRLinkButton = QRLinkButton;
})();