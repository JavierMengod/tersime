/**
 * Usuarios — tokens page logic.
 * Handles: delete confirmation flow, clipboard copy for newly created tokens.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Delete token modal ────────────────────────────────────────────────────
    document.querySelectorAll('.btn-eliminar-token').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modal-token-nombre').textContent = this.dataset.tokenName;
            document.getElementById('form-eliminar-token').action = this.dataset.url;
            new bootstrap.Modal(document.getElementById('modal-eliminar-token')).show();
        });
    });

    // ── Copy token button (only present when a new token was just created) ────
    const btnCopiar = document.getElementById('btnCopiar');
    if (!btnCopiar) return;

    function copyFallback(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    btnCopiar.addEventListener('click', function () {
        const btn   = this;
        const token = document.getElementById('tokenTexto').innerText.trim();

        function onCopied() {
            btn.innerHTML = '<i class="bi bi-clipboard-check"></i> Copiado';
            btn.classList.replace('btn-outline-secondary', 'btn-success');
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-clipboard"></i> Copiar';
                btn.classList.replace('btn-success', 'btn-outline-secondary');
            }, 2000);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(token).then(onCopied).catch(() => {
                copyFallback(token);
                onCopied();
            });
        } else {
            copyFallback(token);
            onCopied();
        }
    });

});
