/**
 * Delete confirmation dialog for backend config list.
 * Uses TYPO3's Modal API for a proper confirmation dialog.
 */
define(['TYPO3/CMS/Backend/Modal', 'TYPO3/CMS/Backend/Severity'], function(Modal, Severity) {
    'use strict';

    document.querySelectorAll('[data-delete-form-id]').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            var formId = button.dataset.deleteFormId;
            var title = button.dataset.deleteTitle || 'Delete';
            var message = button.dataset.deleteMessage || 'Are you sure you want to delete this configuration?';
            var cancelText = button.dataset.deleteCancelText || 'Cancel';
            var confirmText = button.dataset.deleteConfirmText || 'Delete';

            var modal = Modal.confirm(title, message, Severity.warning, [
                {
                    text: cancelText,
                    btnClass: 'btn-default',
                    name: 'cancel',
                    active: true
                },
                {
                    text: confirmText,
                    btnClass: 'btn-warning',
                    name: 'delete'
                }
            ]);

            modal.on('button.clicked', function(evt) {
                var name = evt.target.getAttribute('name');
                Modal.dismiss();
                if (name === 'delete') {
                    var form = document.getElementById(formId);
                    if (form) {
                        form.submit();
                    }
                }
            });
        });
    });
});
