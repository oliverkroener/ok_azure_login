:navigation-title:
    Azure Login

..  _start:

===========
Azure Login
===========

:Extension key:
    ok_azure_login

:Package name:
    oliverkroener/ok-azure-login

:Version:
    |release|

:Language:
    en

:Author:
    Oliver Kroener <https://www.oliver-kroener.de> & Contributors

:License:
    This document is published under the
    `Open Publication License <https://www.opencontent.org/openpub/>`__.

:Rendered:
    |today|

..  toctree::
    :titlesonly:
    :hidden:
    :maxdepth: 2

    Installation
    Azure
    Configuration/Index
    Faq
    GetHelp
    Sitemap

..  note::
    * **Purpose**: Enables TYPO3 frontend and backend user login via Microsoft Entra ID (formerly Azure AD)
    * **Authentication**: Uses OAuth 2.0 authorization code flow with HMAC-signed state parameter
    * **API Integration**: Retrieves user profile via Microsoft Graph API ``/me`` endpoint
    * **Flexibility**: Supports both frontend (``fe_users``) and backend (``be_users``) authentication
    * **Per-site configuration**: Manage Azure credentials per TYPO3 site via a dedicated backend module
    * **Security**: Client secrets are encrypted at rest using PHP Sodium

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Installation <installation>`

        How to install this extension via Composer and add the
        content elements to your TYPO3 site.

    ..  card:: :ref:`Microsoft Entra ID Setup <azure>`

        Register an application in Microsoft Entra ID and configure
        OAuth 2.0 credentials and permissions.

    ..  card:: :ref:`Configuration <configuration>`

        Configure Azure credentials per site using the backend module
        or via global extension settings as fallback.

    ..  card:: :ref:`Frequently Asked Questions (FAQ) <faq>`

        Common questions about setup and usage.

    ..  card:: :ref:`Get Help <help>`

        Where to get help and how to report issues.
