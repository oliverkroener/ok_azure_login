/**
 * Page browser integration for feUserStoragePid field.
 * Opens the TYPO3 Element Browser popup and handles the selected page.
 */
const browseButton = document.getElementById('feUserStoragePid_browse');
const clearButton = document.getElementById('feUserStoragePid_clear');
const hiddenInput = document.getElementById('feUserStoragePid');
const titleDisplay = document.getElementById('feUserStoragePid_title');

if (browseButton) {
    browseButton.addEventListener('click', () => {
        const url = browseButton.dataset.elementBrowserUrl;
        const popup = window.open(
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
    clearButton.addEventListener('click', () => {
        if (hiddenInput) hiddenInput.value = '0';
        if (titleDisplay) titleDisplay.textContent = '';
        if (clearButton) clearButton.style.display = 'none';
        if (hiddenInput) hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

// Listen for Element Browser postMessage callback.
// TYPO3 sends: { actionName, fieldName, value: "table_uid" or "recordType_uid", label }
window.addEventListener('message', (event) => {
    if (!event.data || event.data.actionName !== 'typo3:elementBrowser:elementAdded') {
        return;
    }

    const value = event.data.value || '';
    const label = event.data.label || '';

    // value is "pages_<uid>" (from record list) or "<doktype>_<uid>" (from tree) — extract uid
    const parts = value.split('_');
    const uid = parseInt(parts[parts.length - 1], 10);

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
