/**
 * Tracks unsaved changes in the Azure Login configuration form.
 * Integrates with TYPO3's ConsumerScope to intercept page tree navigation
 * and shows the standard "unsaved changes" modal dialog.
 */
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';
import $ from 'jquery';

class FormDirtyCheck {
    constructor() {
        this.form = null;
        this.isDirty = false;
        this.initialData = '';
    }

    initialize() {
        this.form = document.getElementById('azureConfigForm');
        if (!this.form) {
            return;
        }

        this.initialData = this.serializeForm();

        // Track input changes
        this.form.addEventListener('input', () => this.checkDirty());
        this.form.addEventListener('change', () => this.checkDirty());

        // Register with TYPO3's ConsumerScope for page tree / module navigation
        try {
            top.TYPO3.Backend.consumerScope.attach(this);
        } catch (e) {
            // ConsumerScope not available (e.g. cross-origin)
        }

        // Browser close/refresh fallback
        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Detach consumer when iframe is unloaded
        window.addEventListener('pagehide', () => {
            try {
                top.TYPO3.Backend.consumerScope.detach(this);
            } catch (e) {
                // ignore
            }
        }, {once: true});

        // Reset dirty state on form submit
        this.form.addEventListener('submit', () => {
            this.isDirty = false;
        });

        // Intercept Frontend/Backend tab link clicks
        document.querySelectorAll('.nav-tabs .nav-link').forEach((link) => {
            link.addEventListener('click', (e) => {
                if (!this.isDirty) {
                    return;
                }
                e.preventDefault();
                const href = link.getAttribute('href');
                this.showConfirmModal().then(() => {
                    window.location.href = href;
                });
            });
        });
    }

    serializeForm() {
        return new URLSearchParams(new FormData(this.form)).toString();
    }

    checkDirty() {
        this.isDirty = this.serializeForm() !== this.initialData;
    }

    /**
     * ConsumerScope consumer interface.
     * Called by TYPO3 before navigating away (page tree click, module switch, refresh).
     * Returns a jQuery Deferred that resolves to allow navigation or stays pending to block it.
     */
    consume(interactionRequest) {
        const deferred = $.Deferred();

        if (!this.isDirty) {
            deferred.resolve();
            return deferred;
        }

        this.showConfirmModal().then(() => deferred.resolve());

        return deferred;
    }

    /**
     * Shows the standard TYPO3 "unsaved changes" modal with three buttons:
     * - No, I will continue editing (blocks navigation)
     * - Yes, discard my changes (allows navigation)
     * - Save and close (saves via AJAX, then allows navigation)
     *
     * Returns a Promise that resolves when navigation should proceed.
     * The Promise stays pending if the user chooses to continue editing.
     */
    showConfirmModal() {
        return new Promise((resolve) => {
            const buttons = [
                {
                    text: TYPO3?.lang?.['buttons.confirm.close_without_save.no'] || 'No, I will continue editing',
                    btnClass: 'btn-default',
                    name: 'no',
                    active: true,
                },
                {
                    text: TYPO3?.lang?.['buttons.confirm.close_without_save.yes'] || 'Yes, discard my changes',
                    btnClass: 'btn-default',
                    name: 'yes',
                },
                {
                    text: TYPO3?.lang?.['buttons.confirm.save_and_close'] || 'Save and close',
                    btnClass: 'btn-primary',
                    name: 'save',
                },
            ];

            const modal = Modal.confirm(
                TYPO3?.lang?.['label.confirm.close_without_save.title'] || 'Do you want to close without saving?',
                TYPO3?.lang?.['label.confirm.close_without_save.content'] || 'You currently have unsaved changes. Are you sure you want to discard these changes?',
                Severity.warning,
                buttons
            );

            modal.addEventListener('button.clicked', (e) => {
                const name = e.target.getAttribute('name');
                modal.hideModal();

                if (name === 'yes') {
                    this.isDirty = false;
                    resolve();
                } else if (name === 'save') {
                    this.saveForm()
                        .then(() => {
                            this.isDirty = false;
                            resolve();
                        })
                        .catch(() => {
                            // Save failed, stay on the page
                        });
                }
                // 'no' or backdrop click: Promise stays pending, navigation blocked
            });
        });
    }

    /**
     * Submits the form via AJAX (fetch) so the configuration is saved
     * without a full page reload.
     */
    saveForm() {
        return fetch(this.form.action, {
            method: 'POST',
            body: new FormData(this.form),
        }).then((response) => {
            if (!response.ok) {
                throw new Error('Save failed');
            }
        });
    }
}

const checker = new FormDirtyCheck();
checker.initialize();
