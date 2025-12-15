Explanation of this usage example:

- `<!— /components/qr-link-button/qr-link-button.html —>`  
  Comment indicating where this snippet belongs in your project.

- `<link rel=“stylesheet” ...>`  
  Loads the CSS that styles the QR link button.

- `<div id=“qrDemo”></div>`  
  Placeholder element.  
  The QR link button will be rendered **inside this div**.

- `<script src=“...qr-link-button.js”></script>`  
  Loads the JavaScript component that creates the QR link button.

- `<script> ... </script>`  
  Runs the example code after the component is loaded.

- `QrLinkButton.mount(“#qrDemo”, { ... })`  
  Creates the QR link button and inserts it into `#qrDemo`.

- `url`  
  The link that:
  - the QR code points to
  - opens when the button is clicked

- `label`  
  Visible title text next to the QR code.

- `caption`  
  Optional helper text shown under the label.

- `size`  
  Size of the QR code in pixels.

Result:
- A styled button appears on the page.
- Clicking the button opens the link.
- Scanning the QR code opens the same link on a phone.