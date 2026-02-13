/**
 * Clone backend config values into the frontend edit form.
 * Copies Tenant ID, Client ID, and Client Secret.
 * Shows a TYPO3 Modal confirmation with details.
 * Redirect URI is editable — Clone button stays disabled until it is changed.
 */
define(['jquery', 'TYPO3/CMS/Backend/Modal', 'TYPO3/CMS/Backend/Severity'], function($, Modal, Severity) {
    'use strict';

    var select = document.getElementById('cloneSource');
    if (!select) {
        return;
    }

    select.addEventListener('change', function() {
        var option = select.options[select.selectedIndex];
        if (!option || !option.value) {
            return;
        }

        var tenantId = option.dataset.tenantId || '';
        var clientId = option.dataset.clientId || '';
        var hasSecret = option.dataset.hasSecret === '1';
        var redirectFrontendField = document.getElementById('redirectUriFrontend');
        var redirectUri = (redirectFrontendField ? redirectFrontendField.value : '') || '';
        var sourceUid = option.value;

        var title = select.dataset.cloneConfirmTitle || 'Clone Configuration';
        var confirmText = select.dataset.cloneConfirmButton || 'Clone';
        var cancelText = select.dataset.cloneCancelButton || 'Cancel';

        var lblTenant = select.dataset.cloneLabelTenant || 'Tenant ID';
        var lblClient = select.dataset.cloneLabelClient || 'Client ID';
        var lblSecret = select.dataset.cloneLabelSecret || 'Client Secret';
        var lblRedirect = select.dataset.cloneLabelRedirect || 'Redirect URI (Frontend)';
        var redirectNote = select.dataset.cloneRedirectNote || 'Please configure a separate Redirect URI for frontend use.';
        var secretClonedText = select.dataset.cloneSecretCloned || 'Client secret will be copied from backend configuration on save.';

        var html = '<table class="table table-sm table-bordered mb-3">';
        html += '<tr><th>' + escapeHtml(lblTenant) + '</th><td><code>' + escapeHtml(tenantId) + '</code></td></tr>';
        html += '<tr><th>' + escapeHtml(lblClient) + '</th><td><code>' + escapeHtml(clientId) + '</code></td></tr>';
        html += '<tr><th>' + escapeHtml(lblSecret) + '</th><td>' + (hasSecret
            ? '<div class="form-check"><input type="checkbox" class="form-check-input" id="cloneSecretCheckbox" />'
            + ' <label class="form-check-label" for="cloneSecretCheckbox">' + escapeHtml(secretClonedText) + '</label></div>'
            : '<span class="text-muted">&mdash;</span>') + '</td></tr>';
        html += '<tr><th>' + escapeHtml(lblRedirect) + '</th><td>'
            + '<input type="url" class="form-control form-control-sm" id="cloneRedirectUriInput" '
            + 'value="' + escapeAttr(redirectUri) + '" placeholder="https://example.com/azure-login/callback" />'
            + '<div class="form-text text-warning mb-0">' + escapeHtml(redirectNote) + '</div>'
            + '</td></tr>';
        html += '</table>';

        var modal = Modal.confirm(title, $('<div>').html(html), Severity.info, [
            {
                text: cancelText,
                btnClass: 'btn-default',
                name: 'cancel',
                active: true
            },
            {
                text: confirmText,
                btnClass: 'btn-primary',
                name: 'clone'
            }
        ]);

        // Disable Clone button until redirect URI is changed
        setTimeout(function() {
            var cloneBtn = modal.find('button[name="clone"]');
            var redirectInput = modal.find('#cloneRedirectUriInput');
            if (cloneBtn.length && redirectInput.length) {
                cloneBtn.prop('disabled', true);
                redirectInput.on('input', function() {
                    var changed = redirectInput.val().trim() !== redirectUri;
                    cloneBtn.prop('disabled', !changed || redirectInput.val().trim() === '');
                });
            }
        }, 100);

        modal.on('button.clicked', function(evt) {
            var name = evt.target.getAttribute('name');
            if (name === 'clone') {
                var redirectInput = modal.find('#cloneRedirectUriInput');
                var newRedirectUri = redirectInput.length ? redirectInput.val().trim() : '';

                Modal.dismiss();

                var tenantInput = document.getElementById('tenantId');
                var clientInput = document.getElementById('clientId');
                var redirectFrontendInput = document.getElementById('redirectUriFrontend');
                var cloneSecretInput = document.getElementById('cloneSecretFromUid');
                var secretField = document.getElementById('clientSecret');
                var secretHelp = secretField ? secretField.closest('.row') : null;
                secretHelp = secretHelp ? secretHelp.querySelector('.form-text') : null;

                if (tenantInput) {
                    tenantInput.value = tenantId;
                    tenantInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (clientInput) {
                    clientInput.value = clientId;
                    clientInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (redirectFrontendInput && newRedirectUri) {
                    redirectFrontendInput.value = newRedirectUri;
                    redirectFrontendInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                var secretCheckbox = modal.find('#cloneSecretCheckbox');
                if (cloneSecretInput && hasSecret && secretCheckbox.length && secretCheckbox.prop('checked')) {
                    cloneSecretInput.value = sourceUid;
                    if (secretHelp) {
                        secretHelp.innerHTML = '<span class="text-info"><strong>&#10003; ' + escapeHtml(secretClonedText) + '</strong></span>';
                    }
                }
            } else {
                Modal.dismiss();
            }

            // Reset select to placeholder
            select.selectedIndex = 0;
        });
    });

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
});
