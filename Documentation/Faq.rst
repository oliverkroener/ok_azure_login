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
        email address must exist in TYPO3.

        **Frontend users**: By default, yes. However, if **auto-create** is
        enabled in the site configuration, the extension will automatically
        create a **disabled** ``fe_users`` record on first login. An
        administrator must then enable the account before the user can sign in.

        See :ref:`configuration-auto-create` for details.

    ..  accordion-item:: Can I use this for both frontend and backend login?
        :name: faq-frontend-backend
        :header-level: 2

        Yes. For **frontend login**, add the **Azure Login** content element to a
        page. For **backend login**, the extension automatically adds a "Sign in
        with Microsoft" tab to the TYPO3 backend login screen. Both require a
        redirect URI configured in Microsoft Entra ID.

        The **backend redirect URI** is automatically derived from the TYPO3
        route configuration and shown as a read-only field in the backend module.

    ..  accordion-item:: Can I have multiple backend login configurations?
        :name: faq-multiple-backend
        :header-level: 2

        Yes. The Backend tab in the configuration module allows you to create
        multiple backend login configurations. Each one appears as a separate
        "Sign in with Microsoft" button on the backend login screen, with its
        own label (e.g. company name). This is useful when multiple Azure
        tenants need backend access.

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
        configuration with encrypted client secret storage and supports
        multiple backend login configurations.

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
        Tenant ID, Client ID, Client Secret, and frontend redirect URI.

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

    ..  accordion-item:: What happens if a frontend user account is auto-created?
        :name: faq-auto-create
        :header-level: 2

        The auto-created ``fe_users`` record is **disabled** by default. The
        user will see a message explaining that their account has been created
        but is pending activation. An administrator must enable the account
        in the TYPO3 backend before the user can sign in.

        Auto-created users are assigned to the default user groups configured
        in the site settings and stored on the configured storage page.

    ..  accordion-item:: Where can I get help?
        :name: faq-help
        :header-level: 2

        See chapter :ref:`help`.
