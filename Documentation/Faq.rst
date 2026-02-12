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

        Yes. The extension matches the authenticated Microsoft account to an
        existing ``fe_users`` or ``be_users`` record by **email address**. If
        no matching record is found, the login is rejected. The extension does
        not create new user accounts automatically.

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

    ..  accordion-item:: Can I use different Azure credentials per site?
        :name: faq-per-site
        :header-level: 2

        Yes. The backend module stores configuration per TYPO3 site root page.
        Click on any page belonging to a site in the page tree, and the module
        resolves the correct site automatically. Each site can have its own
        Tenant ID, Client ID, Client Secret, and redirect URIs.

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
