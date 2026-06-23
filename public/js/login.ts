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
    // Break-glass: when gate.ts tagged <html> with `googlesso-break-glass`
    // (via `?hilfe=1`), keep the standard credentials form so an admin can log
    // in locally. Only remove it from the DOM in the normal SSO-only case.
    //
    // Use closest() rather than a `:has()` selector: `:has()` is not supported
    // in every browser, and an unsupported selector passed to querySelector
    // throws, which would abort this whole script.
    //
    // Pull <form> content and remove the empty tag
    //
    if (!document.documentElement.classList.contains('googlesso-break-glass')) {
        document.querySelector<HTMLInputElement>('input#login_name')
            ?.closest('.col-md-5')
            ?.remove();
        const child = document.querySelector<HTMLDivElement>("form > div");
        const parent = child?.parentElement;
        if (!child || !parent) throw new Error("Page rendered with errors");
        parent.insertAdjacentHTML('beforebegin', child.innerHTML);
        child.remove();
        parent.remove();
    }

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
