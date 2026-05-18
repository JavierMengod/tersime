/**
 * Alertas – Reglas page logic.
 * Handles: edit modal population, delete confirmation flow, channel template toggles.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Create modal — channel toggles ─────────────────────────────────────────
    ['telegram', 'email', 'discord'].forEach(channel => {
        const cb = document.getElementById(`create-ch-${channel}`);
        if (cb) cb.addEventListener('change', () => {
            document.getElementById(`create-tpl-${channel}`).style.display = cb.checked ? '' : 'none';
        });
    });

    // ── Edit modal ─────────────────────────────────────────────────────────────
    const editModal = document.getElementById('modal-rule-edit');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', e => {
            const rule = JSON.parse(e.relatedTarget.dataset.rule);
            const form = document.getElementById('form-rule-edit');

            // URLs come from the server — no hardcoded paths in JS
            form.action = rule.update_url;

            document.getElementById('edit-name').value         = rule.name;
            document.getElementById('edit-operator').value     = rule.operator;
            document.getElementById('edit-value').value        = rule.value;
            document.getElementById('edit-for-duration').value = rule.for_duration;

            form.querySelectorAll('[name="devices[]"]').forEach(cb => {
                cb.checked = rule.devices.includes(parseInt(cb.value, 10));
            });

            ['telegram', 'email', 'discord'].forEach(channel => {
                const cb  = document.getElementById(`edit-ch-${channel}`);
                const tpl = document.getElementById(`edit-tpl-${channel}`);
                cb.checked         = !!rule.methods[channel];
                tpl.style.display  = rule.methods[channel] ? '' : 'none';
            });

            document.getElementById('edit-template-telegram').value = rule.templates.telegram ?? '';
            document.getElementById('edit-template-email').value    = rule.templates.email    ?? '';
            document.getElementById('edit-template-discord').value  = rule.templates.discord  ?? '';
            document.getElementById('edit-recipient-email').value   = rule.recipient_email    ?? '';

            const deleteBtn = document.getElementById('edit-btn-delete');
            deleteBtn.dataset.ruleName  = rule.name;
            deleteBtn.dataset.deleteUrl = rule.delete_url;
        });

        // Channel toggles for edit modal
        ['telegram', 'email', 'discord'].forEach(channel => {
            const cb = document.getElementById(`edit-ch-${channel}`);
            if (cb) cb.addEventListener('change', () => {
                document.getElementById(`edit-tpl-${channel}`).style.display = cb.checked ? '' : 'none';
            });
        });

        // Delete button → populate and open confirm modal
        const deleteBtn = document.getElementById('edit-btn-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                document.getElementById('delete-rule-name').textContent = deleteBtn.dataset.ruleName;
                document.getElementById('form-rule-delete').action = deleteBtn.dataset.deleteUrl;

                bootstrap.Modal.getInstance(editModal)?.hide();
                new bootstrap.Modal(document.getElementById('modal-rule-delete')).show();
            });
        }
    }

});
