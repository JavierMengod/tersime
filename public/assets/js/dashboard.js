document.addEventListener('DOMContentLoaded', () => {
    const toMs   = window.DASHBOARD_TO_MS ?? Date.now();
    const MS_DAY = 86400000;

    const daysByBtn = { 1: 1, 7: 7, 30: 30, 365: 365 };
    let activeDays = 30;

    function updateRangeIframes(days) {
        const fromMs = toMs - days * MS_DAY;
        document.querySelectorAll('iframe.grafana-range').forEach(iframe => {
            try {
                const url = new URL(iframe.src, window.location.origin);
                url.searchParams.set('from', fromMs);
                url.searchParams.set('to', toMs);
                iframe.src = url.toString();
            } catch (_) {}
        });
    }

    const selector = document.getElementById('period-selector');
    if (selector) {
        selector.querySelectorAll('button[data-days]').forEach(btn => {
            btn.addEventListener('click', function () {
                selector.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                activeDays = parseInt(this.dataset.days, 10);
                updateRangeIframes(activeDays);
            });
        });
    }
});
