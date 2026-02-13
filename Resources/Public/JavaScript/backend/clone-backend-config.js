/**
 * Clone values from another backend config into the current backend edit form.
 * Copies Tenant ID, Client ID, and Client Secret.
 * Shows a TYPO3 Modal confirmation with details.
 */
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';

const select = document.getElementById('cloneSource');
if (select) {
    select.addEventListener('change', () => {
        const option = select.options[select.selectedIndex];
        if (!option || !option.value) {
            return;
        }

        const tenantId = option.dataset.tenantId || '';
        const clientId = option.dataset.clientId || '';
        const hasSecret = option.dataset.hasSecret === '1';
        const sourceUid = option.value;

        const title = select.dataset.cloneConfirmTitle || 'Clone Configuration';
        const confirmText = select.dataset.cloneConfirmButton || 'Clone';
        const cancelText = select.dataset.cloneCancelButton || 'Cancel';

        const lblTenant = select.dataset.cloneLabelTenant || 'Tenant ID';
        const lblClient = select.dataset.cloneLabelClient || 'Client ID';
        const lblSecret = select.dataset.cloneLabelSecret || 'Client Secret';
        const secretClonedText = select.dataset.cloneSecretCloned || 'Client secret will be copied from the selected configuration on save.';

        let html = '<table class="table table-sm table-bordered mb-3">';
        html += '<tr><th>' + escapeHtml(lblTenant) + '</th><td><code>' + escapeHtml(tenantId) + '</code></td></tr>';
        html += '<tr><th>' + escapeHtml(lblClient) + '</th><td><code>' + escapeHtml(clientId) + '</code></td></tr>';
        html += '<tr><th>' + escapeHtml(lblSecret) + '</th><td>' + (hasSecret
            ? '<div class="form-check"><input type="checkbox" class="form-check-input" id="cloneSecretCheckbox" />'
            + ' <label class="form-check-label" for="cloneSecretCheckbox">' + escapeHtml(secretClonedText) + '</label></div>'
            : '<span class="text-muted">&mdash;</span>') + '</td></tr>';
        html += '</table>';

        const contentElement = document.createElement('div');
        contentElement.innerHTML = html;

        const modal = Modal.advanced({
            title: title,
            content: contentElement,
            severity: Severity.info,
            buttons: [
                {
                    text: cancelText,
                    btnClass: 'btn-default',
                    name: 'cancel',
                    active: true,
                },
                {
                    text: confirmText,
                    btnClass: 'btn-primary',
                    name: 'clone',
                },
            ],
        });

        modal.addEventListener('button.clicked', (evt) => {
            const name = evt.target.getAttribute('name');
            if (name === 'clone') {
                modal.hideModal();

                const tenantInput = document.getElementById('tenantId');
                const clientInput = document.getElementById('clientId');
                const cloneSecretInput = document.getElementById('cloneSecretFromUid');
                const secretHelp = document.getElementById('clientSecret')
                    ?.closest('.row')
                    ?.querySelector('.form-text');

                if (tenantInput) {
                    tenantInput.value = tenantId;
                    tenantInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (clientInput) {
                    clientInput.value = clientId;
                    clientInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                const secretCheckbox = modal.querySelector('#cloneSecretCheckbox');
                if (cloneSecretInput && hasSecret && secretCheckbox && secretCheckbox.checked) {
                    cloneSecretInput.value = sourceUid;
                    if (secretHelp) {
                        secretHelp.innerHTML = '<span class="text-info"><strong>&#10003; ' + escapeHtml(secretClonedText) + '</strong></span>';
                    }
                }
            } else {
                modal.hideModal();
            }

            // Reset select to placeholder
            select.selectedIndex = 0;
        });
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
