/**
 * Tiempo Real monitoring page logic.
 * Depends on: grafana-utils.js, device-selector.js
 * Requires globals: GRAFANA_BASE, GRAFANA_PARAMS, MSG_SIN_DISPOSITIVO
 */

// Classic Palette — same order as Grafana's Classic Palette
const PALETTE = [
    '#7EB26D', '#EAB839', '#6ED0E0', '#EF843C',
    '#E24D42', '#1F78C1', '#BA43A9', '#705DA0',
    '#508642', '#CCA300',
];

document.addEventListener('DOMContentLoaded', () => {
    const iframe  = document.getElementById('grafanaIframe');
    const leyenda = document.getElementById('grafanaLeyenda');

    // ── Fechas por defecto ─────────────────────────────────────────────────────
    const today = new Date();
    const from  = new Date();
    from.setDate(today.getDate() - 7);
    document.getElementById('fromDate').value = formatLocalDate(from);
    document.getElementById('toDate').value   = formatLocalDate(today);

    // ── Selector de dispositivos ───────────────────────────────────────────────
    const selector = new MultiDeviceSelector({
        containerId:  'dispositivosSeleccionados',
        itemSelector: '.dispositivo-opcion',
        msgEmpty:     MSG_SIN_DISPOSITIVO,
        getData:      el => ({ key: el.dataset.url, label: el.textContent.trim() }),
        hideSelected: true,
        onChange:     () => {
            actualizarIframe();
            actualizarLeyenda(selector.selections);
        },
    });

    // ── Iframe ─────────────────────────────────────────────────────────────────
    function actualizarIframe() {
        const url = buildTiempoRealUrl(
            GRAFANA_BASE, GRAFANA_PARAMS,
            selector.selections,
            document.getElementById('fromDate').value,
            document.getElementById('toDate').value
        );
        iframe.src = url ?? '';
    }

    // ── Leyenda ────────────────────────────────────────────────────────────────
    function actualizarLeyenda(selections) {
        leyenda.innerHTML = '';

        if (!selections.length) {
            leyenda.classList.add('d-none');
            return;
        }

        selections.forEach((sel, i) => {
            const color = PALETTE[i % PALETTE.length];
            const item  = document.createElement('span');
            item.className = 'd-flex align-items-center gap-2 small fw-medium';

            const dot = document.createElement('span');
            dot.style.cssText = `width:12px;height:12px;border-radius:50%;background:${color};flex-shrink:0`;

            const texto = document.createElement('span');
            texto.textContent = sel.label;

            item.appendChild(dot);
            item.appendChild(texto);
            leyenda.appendChild(item);
        });

        leyenda.classList.remove('d-none');
    }

    document.getElementById('fromDate').addEventListener('change', actualizarIframe);
    document.getElementById('toDate').addEventListener('change',   actualizarIframe);
    actualizarIframe();

    // Modal zoom: carga el iframe al abrir, lo limpia al cerrar
    const zoomModal  = document.getElementById('modal-grafana-zoom');
    const zoomIframe = document.getElementById('grafanaZoomIframe');
    zoomModal.addEventListener('show.bs.modal', () => { zoomIframe.src = iframe.src; });
    zoomModal.addEventListener('hide.bs.modal', () => { zoomIframe.src = ''; });
});
