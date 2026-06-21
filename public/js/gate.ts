/**
 * -------------------------------------------------------------------------
 * googlessoauth plugin for GLPI
 * -------------------------------------------------------------------------
 * Synchronous "gate" script.
 *
 * Loaded as a classic, blocking <script src> in <head>, this runs *before* the
 * body is painted. When the break-glass `?noAUTO=1` query parameter is present,
 * it tags the <html> element so the accompanying CSS stops hiding the standard
 * GLPI login form, allowing emergency local authentication.
 *
 * Keeping this logic in a head-blocking classic script (rather than a deferred
 * module) avoids any flash of the credentials form on normal page loads.
 * -------------------------------------------------------------------------
 */
(function gate(): void {
    try {
        const params = new URLSearchParams(window.location.search);
        const noAuto = params.get('noAUTO');
        if (noAuto !== null && noAuto !== '0' && noAuto.toLowerCase() !== 'false') {
            document.documentElement.classList.add('googlesso-break-glass');
        }
    } catch {
        // If anything goes wrong, fail open to the (CSS-hidden) default state.
    }
})();
