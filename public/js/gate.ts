/**
 * -------------------------------------------------------------------------
 * googlessoauth plugin for GLPI
 * -------------------------------------------------------------------------
 * Synchronous "gate" script.
 *
 * Loaded as a classic, blocking <script src> in <head>, this runs *before* the
 * body is painted. When the break-glass `?hilfe=1` query parameter is present,
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
        const breakGlass = params.get('hilfe');
        if (breakGlass !== null && breakGlass !== '0' && breakGlass.toLowerCase() !== 'false') {
            document.documentElement.classList.add('googlesso-break-glass');
        }
    } catch {
        // If anything goes wrong, fail open to the (CSS-hidden) default state.
    }
})();
