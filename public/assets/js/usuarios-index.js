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

            document.getElementById('modal-editar-nombre').textContent = user.nombre;
            document.getElementById('form-editar-usuario').action      = user.url;
            document.getElementById('edit-name').value                 = user.nombre;
            document.getElementById('edit-language').value             = user.idioma;
            document.getElementById('edit-theme').value                = user.tema;
            document.getElementById('edit-timezone').value             = user.zona_horaria;
            document.getElementById('edit-admin').checked              = user.administrador;
            document.getElementById('edit-password').value             = '';
            document.getElementById('edit-password-confirm').value     = '';
        });
    }

});
