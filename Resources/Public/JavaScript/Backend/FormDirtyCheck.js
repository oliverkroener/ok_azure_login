/**
 * Tracks unsaved changes in the Azure Login configuration form.
 * Integrates with TYPO3's ConsumerScope to intercept page tree navigation
 * and shows the standard "unsaved changes" modal dialog.
 */
define(['jquery', 'TYPO3/CMS/Backend/Modal', 'TYPO3/CMS/Backend/Severity'], function($, Modal, Severity) {
    'use strict';

    var FormDirtyCheck = {
        form: null,
        isDirty: false,
        initialData: ''
    };

    FormDirtyCheck.initialize = function() {
        this.form = document.getElementById('azureConfigForm');
        if (!this.form) {
            return;
        }

        this.initialData = this.serializeForm();

        var self = this;

        // Track input changes
        this.form.addEventListener('input', function() { self.checkDirty(); });
        this.form.addEventListener('change', function() { self.checkDirty(); });

        // Register with TYPO3's ConsumerScope for page tree / module navigation
        try {
            top.TYPO3.Backend.consumerScope.attach(this);
        } catch (e) {
            // ConsumerScope not available (e.g. cross-origin)
        }

        // Browser close/refresh fallback
        window.addEventListener('beforeunload', function(e) {
            if (self.isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Detach consumer when iframe is unloaded
        window.addEventListener('pagehide', function() {
            try {
                top.TYPO3.Backend.consumerScope.detach(self);
            } catch (e) {
                // ignore
            }
        }, {once: true});

        // Reset dirty state on form submit
        this.form.addEventListener('submit', function() {
            self.isDirty = false;
        });

        // Intercept Frontend/Backend tab link clicks
        document.querySelectorAll('.nav-tabs .nav-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!self.isDirty) {
                    return;
                }
                e.preventDefault();
                var href = link.getAttribute('href');
                self.showConfirmModal().then(function() {
                    window.location.href = href;
                });
            });
        });
    };

    FormDirtyCheck.serializeForm = function() {
        return new URLSearchParams(new FormData(this.form)).toString();
    };

    FormDirtyCheck.checkDirty = function() {
        this.isDirty = this.serializeForm() !== this.initialData;
    };

    /**
     * ConsumerScope consumer interface.
     * Called by TYPO3 before navigating away (page tree click, module switch, refresh).
     * Returns a jQuery Deferred that resolves to allow navigation or stays pending to block it.
     */
    FormDirtyCheck.consume = function(interactionRequest) {
        var deferred = $.Deferred();

        if (!this.isDirty) {
            deferred.resolve();
            return deferred;
        }

        this.showConfirmModal().then(function() { deferred.resolve(); });

        return deferred;
    };

    /**
     * Shows the standard TYPO3 "unsaved changes" modal with three buttons.
     * Returns a Promise that resolves when navigation should proceed.
     */
    FormDirtyCheck.showConfirmModal = function() {
        var self = this;
        return new Promise(function(resolve) {
            var buttons = [
                {
                    text: (TYPO3 && TYPO3.lang && TYPO3.lang['buttons.confirm.close_without_save.no']) || 'No, I will continue editing',
                    btnClass: 'btn-default',
                    name: 'no',
                    active: true
                },
                {
                    text: (TYPO3 && TYPO3.lang && TYPO3.lang['buttons.confirm.close_without_save.yes']) || 'Yes, discard my changes',
                    btnClass: 'btn-default',
                    name: 'yes'
                },
                {
                    text: (TYPO3 && TYPO3.lang && TYPO3.lang['buttons.confirm.save_and_close']) || 'Save and close',
                    btnClass: 'btn-primary',
                    name: 'save'
                }
            ];

            var modal = Modal.confirm(
                (TYPO3 && TYPO3.lang && TYPO3.lang['label.confirm.close_without_save.title']) || 'Do you want to close without saving?',
                (TYPO3 && TYPO3.lang && TYPO3.lang['label.confirm.close_without_save.content']) || 'You currently have unsaved changes. Are you sure you want to discard these changes?',
                Severity.warning,
                buttons
            );

            modal.on('button.clicked', function(e) {
                var name = e.target.getAttribute('name');
                Modal.dismiss();

                if (name === 'yes') {
                    self.isDirty = false;
                    resolve();
                } else if (name === 'save') {
                    self.saveForm()
                        .then(function() {
                            self.isDirty = false;
                            resolve();
                        })
                        .catch(function() {
                            // Save failed, stay on the page
                        });
                }
                // 'no' or backdrop click: Promise stays pending, navigation blocked
            });
        });
    };

    /**
     * Submits the form via AJAX (fetch) so the configuration is saved
     * without a full page reload.
     */
    FormDirtyCheck.saveForm = function() {
        return fetch(this.form.action, {
            method: 'POST',
            body: new FormData(this.form)
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('Save failed');
            }
        });
    };

    FormDirtyCheck.initialize();
});
