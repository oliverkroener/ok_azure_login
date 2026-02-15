/**
 * Page browser integration for feUserStoragePid field.
 * Opens the TYPO3 Element Browser in a modal iframe and handles the selected page.
 */
import Modal from '@typo3/backend/modal.js';
import { MessageUtility } from '@typo3/backend/utility/message-utility.js';

const browseButton = document.getElementById('feUserStoragePid_browse');
const clearButton = document.getElementById('feUserStoragePid_clear');
const hiddenInput = document.getElementById('feUserStoragePid');
const titleDisplay = document.getElementById('feUserStoragePid_title');

function handleMessage(event) {
    if (!MessageUtility.verifyOrigin(event.origin)) {
        return;
    }
    if (!event.data || event.data.actionName !== 'typo3:elementBrowser:elementAdded') {
        return;
    }

    const value = event.data.value || '';
    const label = event.data.label || '';

    // value is "pages_<uid>" or "<doktype>_<uid>" — extract uid
    const uid = parseInt(value.split('_').pop(), 10);

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
}

if (browseButton) {
    browseButton.addEventListener('click', () => {
        const url = browseButton.dataset.elementBrowserUrl;
        const modal = Modal.advanced({
            type: Modal.types.iframe,
            content: url,
            size: Modal.sizes.large,
        });
        window.addEventListener('message', handleMessage);
        modal.addEventListener('typo3-modal-hide', () => {
            window.removeEventListener('message', handleMessage);
        });
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
