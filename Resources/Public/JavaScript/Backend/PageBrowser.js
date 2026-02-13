/**
 * Page browser integration for feUserStoragePid field.
 * Opens the TYPO3 Element Browser popup and handles the selected page.
 * RequireJS module for TYPO3 v10.
 */
define([], function() {
    'use strict';

    var browseButton = document.getElementById('feUserStoragePid_browse');
    var clearButton = document.getElementById('feUserStoragePid_clear');
    var hiddenInput = document.getElementById('feUserStoragePid');
    var titleDisplay = document.getElementById('feUserStoragePid_title');

    if (browseButton) {
        browseButton.addEventListener('click', function() {
            var url = browseButton.dataset.elementBrowserUrl;
            var popup = window.open(
                url,
                'typo3_element_browser',
                'height=600,width=800,status=0,menubar=0,resizable=1,scrollbars=1'
            );
            if (popup) {
                popup.focus();
            }
        });
    }

    if (clearButton) {
        clearButton.addEventListener('click', function() {
            if (hiddenInput) hiddenInput.value = '0';
            if (titleDisplay) titleDisplay.textContent = '';
            if (clearButton) clearButton.style.display = 'none';
            if (hiddenInput) hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    // Listen for Element Browser postMessage callback.
    // TYPO3 sends: { actionName, fieldName, value: "table_uid" or "recordType_uid", label }
    window.addEventListener('message', function(event) {
        if (!event.data || event.data.actionName !== 'typo3:elementBrowser:elementAdded') {
            return;
        }

        var value = event.data.value || '';
        var label = event.data.label || '';

        // value is "pages_<uid>" (from record list) or "<doktype>_<uid>" (from tree) — extract uid
        var parts = value.split('_');
        var uid = parseInt(parts[parts.length - 1], 10);

        if (uid > 0) {
            if (hiddenInput) {
                hiddenInput.value = uid;
                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (titleDisplay) {
                titleDisplay.textContent = '[' + uid + '] ' + label;
            }
            if (clearButton) {
                clearButton.style.display = '';
            }
        }
    });
});
