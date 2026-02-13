:navigation-title: FAQ

..  _faq:

================================
Frequently Asked Questions (FAQ)
================================

..  accordion::
    :name: faq

    ..  accordion-item:: How do I install this extension?
        :name: faq-installation
        :header-level: 2
        :show:

        See chapter :ref:`installation`.

    ..  accordion-item:: Do users need to exist in TYPO3 before they can log in?
        :name: faq-user-exists
        :header-level: 2

        **Backend users**: Yes. A matching ``be_users`` record with the same
        email address must already exist in TYPO3.

        **Frontend users**: By default, yes. However, you can enable
        **auto-creation** in the backend module. When enabled, a **disabled**
        frontend user account is created automatically for authenticated
        Microsoft users who have no matching ``fe_users`` record. An
        administrator must then enable the account before the user can sign in.

        See :ref:`configuration-backend-module` for details on configuring
        auto-creation.

    ..  accordion-item:: Can I use this for both frontend and backend login?
        :name: faq-frontend-backend
        :header-level: 2

        Yes. For **frontend login**, add the **Azure Login** content element to a
        page. For **backend login**, the extension automatically adds a "Sign in
        with Microsoft" tab to the TYPO3 backend login screen. Both require their
        own redirect URI configured in Microsoft Entra ID.

    ..  accordion-item:: Which Microsoft Graph API permissions are needed?
        :name: faq-permissions
        :header-level: 2

        The extension requires **delegated** permissions only: ``openid``,
        ``profile``, and ``User.Read``. No application permissions are needed.
        See the :ref:`Azure Entra ID setup <azure>` for details.

    ..  accordion-item:: Where do I configure the Azure credentials?
        :name: faq-configuration
        :header-level: 2

        The recommended method is the **backend module** at
        :guilabel:`Web` > :guilabel:`Azure Login`. This allows per-site
        configuration with encrypted client secret storage.

        As a fallback, global credentials can be set via
        :guilabel:`Admin Tools` > :guilabel:`Settings` >
        :guilabel:`Extension Configuration` > :guilabel:`ok_azure_login`.

        See chapter :ref:`configuration`.

    ..  accordion-item:: How does frontend user auto-creation work?
        :name: faq-auto-create
        :header-level: 2

        When enabled in the backend module (per site), the extension
        automatically creates a **disabled** ``fe_users`` record for Microsoft
        users who authenticate successfully but have no existing TYPO3 account.

        The new account receives the user's email as username, their display
        name, given name, and surname from Microsoft Graph, a random password,
        and any default frontend user groups you configured. The user sees a
        blue "account pending" info message.

        An administrator must manually enable the account in the TYPO3 backend
        before the user can sign in. This prevents unauthorized access while
        still streamlining the onboarding process.

    ..  accordion-item:: Can I use different Azure credentials per site?
        :name: faq-per-site
        :header-level: 2

        Yes. The backend module stores configuration per TYPO3 site root page.
        Click on any page belonging to a site in the page tree, and the module
        resolves the correct site automatically. Each site can have its own
        Tenant ID, Client ID, Client Secret, and redirect URIs.

    ..  accordion-item:: Can I have multiple backend login buttons?
        :name: faq-multi-backend
        :header-level: 2

        Yes. Each site can have its own backend login configuration with a
        separate Azure app registration. Every site with valid, enabled backend
        credentials will show a separate "Sign in with Microsoft" button on the
        TYPO3 backend login screen, identified by the configured login button
        label (e.g. company name).

    ..  accordion-item:: How is the client secret stored?
        :name: faq-encryption
        :header-level: 2

        When configured via the backend module, the client secret is encrypted
        using PHP Sodium (``sodium_crypto_secretbox``) before being stored in the
        database. The encryption key is derived from TYPO3's
        ``$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']``.

        The secret is never displayed in the backend module after saving.

        ..  warning::
            If the TYPO3 encryption key is not set, the backend module shows a
            warning. Secrets stored via Extension Configuration (fallback) are
            **not** encrypted.

    ..  accordion-item:: Where can I get help?
        :name: faq-help
        :header-level: 2

        See chapter :ref:`help`.
