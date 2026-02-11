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

        Yes. When adding the Azure Login content element, choose **Frontend Login**
        or **Backend Login** as the login type. Each type uses its own redirect
        URI and authenticates against the corresponding user table.

    ..  accordion-item:: Which Microsoft Graph API permissions are needed?
        :name: faq-permissions
        :header-level: 2

        The extension requires **delegated** permissions only: ``openid``,
        ``profile``, and ``User.Read``. No application permissions are needed.
        See the :ref:`Azure Entra ID setup <azure>` for details.

    ..  accordion-item:: Where do I configure the Azure credentials?
        :name: faq-configuration
        :header-level: 2

        Go to :guilabel:`Admin Tools` > :guilabel:`Settings` >
        :guilabel:`Extension Configuration` > :guilabel:`ok_azure_login`.
        See chapter :ref:`configuration`.

    ..  accordion-item:: Where can I get help?
        :name: faq-help
        :header-level: 2

        See chapter :ref:`help`.
