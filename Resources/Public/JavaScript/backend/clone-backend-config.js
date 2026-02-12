/**
 * Clone values from another backend config into the current backend edit form.
 * Copies Tenant ID, Client ID, and Client Secret.
 * Shows a TYPO3 Modal confirmation with details.
 * Redirect URI is editable — Clone button stays disabled until it is changed.
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
        const redirectUri = option.dataset.redirectUri || '';
        const sourceUid = option.value;

        const title = select.dataset.cloneConfirmTitle || 'Clone Configuration';
        const confirmText = select.dataset.cloneConfirmButton || 'Clone';
        const cancelText = select.dataset.cloneCancelButton || 'Cancel';

        const lblTenant = select.dataset.cloneLabelTenant || 'Tenant ID';
        const lblClient = select.dataset.cloneLabelClient || 'Client ID';
        const lblSecret = select.dataset.cloneLabelSecret || 'Client Secret';
        const lblRedirect = select.dataset.cloneLabelRedirect || 'Redirect URI (Backend)';
        const redirectNote = select.dataset.cloneRedirectNote || 'Please configure the correct Redirect URI for this backend configuration.';
        const secretClonedText = select.dataset.cloneSecretCloned || 'Client secret will be copied from the selected configuration on save.';

        let html = '<table class="table table-sm table-bordered mb-3">';
        html += '<tr><th>' + escapeHtml(lblTenant) + '</th><td><code>' + escapeHtml(tenantId) + '</code></td></tr>';
        html += '<tr><th>' + escapeHtml(lblClient) + '</th><td><code>' + escapeHtml(clientId) + '</code></td></tr>';
        html += '<tr><th>' + escapeHtml(lblSecret) + '</th><td>' + (hasSecret
            ? '<div class="form-check"><input type="checkbox" class="form-check-input" id="cloneSecretCheckbox" />'
            + ' <label class="form-check-label" for="cloneSecretCheckbox">' + escapeHtml(secretClonedText) + '</label></div>'
            : '<span class="text-muted">&mdash;</span>') + '</td></tr>';
        html += '<tr><th>' + escapeHtml(lblRedirect) + '</th><td>'
            + '<input type="url" class="form-control form-control-sm" id="cloneRedirectUriInput" '
            + 'value="' + escapeAttr(redirectUri) + '" placeholder="https://example.com/typo3/azure-login/callback" />'
            + '<div class="form-text text-warning mb-0">' + escapeHtml(redirectNote) + '</div>'
            + '</td></tr>';
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

        // Disable Clone button until redirect URI is changed
        modal.addEventListener('typo3-modal-shown', () => {
            const cloneBtn = modal.querySelector('button[name="clone"]');
            const redirectInput = modal.querySelector('#cloneRedirectUriInput');
            if (cloneBtn && redirectInput) {
                cloneBtn.disabled = true;
                redirectInput.addEventListener('input', () => {
                    const changed = redirectInput.value.trim() !== redirectUri;
                    cloneBtn.disabled = !changed || redirectInput.value.trim() === '';
                });
            }
        });

        modal.addEventListener('button.clicked', (evt) => {
            const name = evt.target.getAttribute('name');
            if (name === 'clone') {
                const redirectInput = modal.querySelector('#cloneRedirectUriInput');
                const newRedirectUri = redirectInput ? redirectInput.value.trim() : '';

                modal.hideModal();

                const tenantInput = document.getElementById('tenantId');
                const clientInput = document.getElementById('clientId');
                const redirectBackendInput = document.getElementById('redirectUriBackend');
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
                if (redirectBackendInput && newRedirectUri) {
                    redirectBackendInput.value = newRedirectUri;
                    redirectBackendInput.dispatchEvent(new Event('input', { bubbles: true }));
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

function escapeAttr(str) {
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
