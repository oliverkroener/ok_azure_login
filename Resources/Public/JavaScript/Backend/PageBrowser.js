/**
 * Page browser integration for feUserStoragePid field.
 * Opens the TYPO3 Element Browser popup and handles the selected page.
 *
 * TYPO3 9 Element Browser calls window.opener.setFormValueFromBrowseWin()
 * to pass the selected record back. We define that global function here.
 *
 * RequireJS module for TYPO3 v9.
 */
define([], function() {
    'use strict';

    var browseButton = document.getElementById('feUserStoragePid_browse');
    var clearButton = document.getElementById('feUserStoragePid_clear');
    var hiddenInput = document.getElementById('feUserStoragePid');
    var titleDisplay = document.getElementById('feUserStoragePid_title');

    // Define the global callback that TYPO3 9's Element Browser expects.
    // Signature: setFormValueFromBrowseWin(fieldName, value, label)
    // value is "pages_<uid>"
    window.setFormValueFromBrowseWin = function(fieldName, value, label) {
        // Extract UID from "pages_123" format
        var parts = String(value).split('_');
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
    };

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
});
