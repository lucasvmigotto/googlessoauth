/**
 * -------------------------------------------------------------------------
 * googlessoauth plugin for GLPI
 * -------------------------------------------------------------------------
 * Progressive-enhancement module for the login page.
 *
 * Loaded as an ES module (deferred). Adds a small loading state to the
 * "Login with Google" button when clicked so users get immediate feedback
 * while the browser navigates to the OAuth start endpoint.
 *
 * No automatic redirects happen here: the OAuth flow only begins on an
 * explicit user click of the button (which is a normal link navigation).
 * -------------------------------------------------------------------------
 */

function enhanceButton(): void {
    document.querySelector("div.col-md-5:has(div > input#login_name)")?.remove() 
    const button = document.querySelector<HTMLAnchorElement>('[data-googlesso-btn]');
    if (button === null) {
        return;
    }

    button.addEventListener('click', () => {
        button.classList.add('is-loading');
        const label = button.querySelector<HTMLElement>('.googlesso-btn__label');
        if (label !== null) {
            label.textContent = label.dataset.loadingText ?? label.textContent;
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceButton, { once: true });
} else {
    enhanceButton();
}
