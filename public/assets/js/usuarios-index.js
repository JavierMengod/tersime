/**
 * Usuarios — index page logic.
 * Handles: edit modal population, delete confirmation flow.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Delete user modal ──────────────────────────────────────────────────────
    document.querySelectorAll('.btn-eliminar-usuario').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modal-usuario-nombre').textContent = this.dataset.userName;
            document.getElementById('form-eliminar-usuario').action = this.dataset.url;
            new bootstrap.Modal(document.getElementById('modal-eliminar-usuario')).show();
        });
    });

    // ── Edit user modal ────────────────────────────────────────────────────────
    const editModal = document.getElementById('modal-editar');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', e => {
            const user = JSON.parse(e.relatedTarget.dataset.user);

            document.getElementById('modal-editar-nombre').textContent = user.name;
            document.getElementById('form-editar-usuario').action      = user.url;
            document.getElementById('edit-name').value                 = user.name;
            document.getElementById('edit-language').value             = user.language;
            document.getElementById('edit-theme').value                = user.theme;
            document.getElementById('edit-timezone').value             = user.timezone;
            document.getElementById('edit-admin').checked              = user.admin;
            document.getElementById('edit-password').value             = '';
            document.getElementById('edit-password-confirm').value     = '';
        });
    }

});
