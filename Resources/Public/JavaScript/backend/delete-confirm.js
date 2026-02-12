/**
 * Delete confirmation dialog for backend config list.
 * Uses TYPO3's Modal API for a proper confirmation dialog.
 */
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';

document.querySelectorAll('[data-delete-form-id]').forEach((button) => {
    button.addEventListener('click', (e) => {
        e.preventDefault();
        const formId = button.dataset.deleteFormId;
        const title = button.dataset.deleteTitle || 'Delete';
        const message = button.dataset.deleteMessage || 'Are you sure you want to delete this configuration?';
        const cancelText = button.dataset.deleteCancelText || 'Cancel';
        const confirmText = button.dataset.deleteConfirmText || 'Delete';

        const modal = Modal.confirm(title, message, Severity.warning, [
            {
                text: cancelText,
                btnClass: 'btn-default',
                name: 'cancel',
                active: true,
            },
            {
                text: confirmText,
                btnClass: 'btn-warning',
                name: 'delete',
            },
        ]);

        modal.addEventListener('button.clicked', (evt) => {
            const name = evt.target.getAttribute('name');
            modal.hideModal();
            if (name === 'delete') {
                document.getElementById(formId)?.submit();
            }
        });
    });
});
