(() => {
  const FOOTER_ID = 'brailleStudioSiteFooter';
  const BARTIMEUS_URL = 'https://www.bartimeus.nl';
  const LOGO_URL = 'https://www.tastenbraille.com/braillestudio-data/assets/bartimeus.png';

  function findPageContainer() {
    return document.querySelector('[id$="AppPage"]')
      || Array.from(document.querySelectorAll('.page')).find((page) => !page.classList.contains('page-center'))
      || document.body;
  }

  function renderSiteFooter() {
    document.querySelectorAll('footer').forEach((footer) => footer.remove());
    document.getElementById(FOOTER_ID)?.remove();

    const footer = document.createElement('footer');
    footer.id = FOOTER_ID;
    footer.className = 'footer footer-transparent d-print-none mt-auto';
    footer.innerHTML = `
      <div class="container-xl">
        <div class="row text-center align-items-center flex-row-reverse">
          <div class="col-lg-auto ms-lg-auto">
            <a href="${BARTIMEUS_URL}" target="_blank" rel="noopener noreferrer" aria-label="Bezoek de website van Bartiméus">
              <img src="${LOGO_URL}" width="132" alt="Bartiméus">
            </a>
          </div>
          <div class="col-12 col-lg-auto mt-3 mt-lg-0">
            <span class="text-secondary">Powered by Bartiméus en de Braille Expertise Groep</span>
          </div>
        </div>
      </div>
    `;
    findPageContainer().appendChild(footer);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderSiteFooter, { once: true });
  } else {
    renderSiteFooter();
  }
})();
