/**
 * Shared Grafana / date utilities for monitoring pages.
 */

/** Simple debounce – returns a version of fn that fires ms ms after the last call. */
function debounce(fn, ms) {
    let timer = null;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
}

/** Formats a Date object as YYYY-MM-DD in local timezone. */
function formatLocalDate(d) {
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * YYYY-MM-DD → ms at start of day via explicit local Date constructor.
 * Avoids UTC-offset issues when the platform treats YYYY-MM-DD as UTC.
 */
function parseDateStartLocal(fechaStr) {
    if (!fechaStr) return null;
    const [y, m, d] = fechaStr.split('-').map(Number);
    return new Date(y, m - 1, d, 0, 0, 0, 0).getTime();
}

/** Same as parseDateStartLocal but at end of day (23:59:59.999). */
function parseDateEndLocal(fechaStr) {
    if (!fechaStr) return null;
    const [y, m, d] = fechaStr.split('-').map(Number);
    return new Date(y, m - 1, d, 23, 59, 59, 999).getTime();
}

/**
 * Formats YYYY-MM-DD for Grafana var-start / var-stop params.
 * Grafana expects month without a leading zero (e.g. "2024-5-03").
 */
function formatGrafanaDate(fechaStr) {
    if (!fechaStr) return '';
    const [year, month, day] = fechaStr.split('-');
    if (!year || !month || !day) return '';
    return `${year}-${Number(month)}-${day.padStart(2, '0')}`;
}

/**
 * Returns hours from now until the end of toDateStr.
 * Used to populate var-predict_hours in predictive Grafana panels.
 */
function calcularPredictHours(toDateStr) {
    if (!toDateStr) return 0;
    const [y, m, d] = toDateStr.split('-').map(Number);
    const fechaFin = new Date(y, m - 1, d, 23, 59, 59);
    const diffMs = fechaFin.getTime() - Date.now();
    return diffMs > 0 ? Math.round(diffMs / 3_600_000) : 0;
}

/**
 * Builds the Grafana iframe src for the Tiempo Real page.
 * Appends one var-dispositivos param per selected device.
 *
 * @param {string}              base    Panel base URL (no query string)
 * @param {string}              params  Static query-string portion (orgId, panelId, …)
 * @param {Array<{key:string}>} devices Selected devices
 * @param {string}              fromVal YYYY-MM-DD
 * @param {string}              toVal   YYYY-MM-DD
 * @returns {string|null} Full URL, or null when no devices selected
 */
function buildTiempoRealUrl(base, params, devices, fromVal, toVal) {
    if (!devices.length) return null;

    const fromMs = parseDateStartLocal(fromVal);
    const toMs   = parseDateEndLocal(toVal);

    let url = `${base}?${params}`;
    if (fromMs) url += `&from=${fromMs}`;
    if (toMs)   url += `&to=${toMs}`;
    devices.forEach(d => { url += '&var-dispositivos=' + encodeURIComponent(d.key); });
    return url;
}

/**
 * Builds the Grafana iframe src for the Predicción page.
 * Includes prediction-specific params: predict_hours, var-start/stop/end, cacheBuster.
 *
 * @param {string}       base    Panel base URL
 * @param {string}       params  Static query-string portion
 * @param {{key:string}} device  The single selected device
 * @param {string}       fromVal YYYY-MM-DD
 * @param {string}       toVal   YYYY-MM-DD
 * @param {number}       w       iframe pixel width
 * @param {number}       h       iframe pixel height
 * @returns {string} Full URL
 */
function buildPrediccionUrl(base, params, device, fromVal, toVal, w, h) {
    const predictHours = calcularPredictHours(toVal);
    const fromMs  = parseDateStartLocal(fromVal);
    const toMs    = parseDateEndLocal(toVal);
    const fromSec = fromMs ? Math.floor(fromMs / 1000) : '';
    const toSec   = toMs   ? Math.floor(toMs   / 1000) : '';

    let url = `${base}?${params}`;
    if (fromMs)  url += `&from=${fromMs}`;
    if (toMs)    url += `&to=${toMs}`;
    if (fromSec) url += `&__from=${fromSec}`;
    if (toSec)   url += `&__to=${toSec}`;
    url += `&width=${w}&height=${h}`;
    url += `&var-dispositivos=${encodeURIComponent(device.key)}`;
    url += `&var-predict_hours=${encodeURIComponent(String(predictHours))}`;

    const grafanaStart = formatGrafanaDate(fromVal);
    const grafanaStop  = formatGrafanaDate(toVal);
    if (grafanaStart) url += `&var-start=${encodeURIComponent(grafanaStart)}`;
    if (grafanaStop) {
        url += `&var-stop=${encodeURIComponent(grafanaStop)}`;
        url += `&var-end=${encodeURIComponent(grafanaStop)}`;
    }
    url += `&cacheBuster=${Date.now()}`;
    return url;
}
