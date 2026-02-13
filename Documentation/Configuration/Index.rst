:navigation-title: Configuration

..  _configuration:

=============
Configuration
=============

After completing the :ref:`Microsoft Entra ID setup <azure>`, configure the
extension in TYPO3 with the credentials obtained from Azure.

..  _configuration-backend-module:

Backend module (recommended)
============================

The recommended way to configure Azure credentials is via the dedicated backend
module. This allows you to manage credentials **per TYPO3 site**, so each site
can use its own Azure app registration. The module also supports multiple
**backend login configurations**, each appearing as a separate login button on
the TYPO3 backend login screen.

..  rst-class:: bignums-xxl

1.  Open the backend module

    In the TYPO3 backend, navigate to :guilabel:`Web` > :guilabel:`Azure Login`.

2.  Select a page in the page tree

    Click on any page that belongs to the site you want to configure. The module
    automatically resolves the site root page from the TYPO3 Site Configuration.

3.  Choose Frontend or Backend tab

    The module has two tabs:

    - **Frontend** -- configure Azure credentials for frontend user login on this site
    - **Backend** -- manage backend login configurations (list, create, edit, delete)

..  _configuration-frontend:

Frontend configuration
----------------------

The Frontend tab displays the following fields:

.. confval:: Tenant ID

    :type: string

    The **Directory (tenant) ID** from your Microsoft Entra ID app registration.

.. confval:: Client ID

    :type: string

    The **Application (client) ID** from your Microsoft Entra ID app registration.

.. confval:: Client Secret

    :type: string (password)

    The **Client Secret Value** from your Microsoft Entra ID app registration.

    ..  attention::
        This is the secret **Value**, not the Secret ID.

    The secret is encrypted using PHP Sodium before being stored in the
    database. It is never displayed in the form after saving. If a secret is
    already stored, a green indicator is shown. Leave the field empty to keep
    the existing secret, or enter a new value to replace it.

.. confval:: Redirect URI (Frontend)

    :type: string (URL)

    The OAuth callback URL for **frontend** login. This must match one of the
    redirect URIs registered in your Azure app.

    Example: ``https://your-domain.com/your-login-page``

.. confval:: Clone from Backend Config

    :type: dropdown

    If backend configurations exist, you can clone Tenant ID, Client ID, and
    (optionally) the encrypted Client Secret from one of them. You will be
    prompted to enter a separate frontend Redirect URI.

..  _configuration-auto-create:

Frontend user auto-creation
~~~~~~~~~~~~~~~~~~~~~~~~~~~

When a user authenticates via Microsoft but has no matching ``fe_users`` record,
the extension can automatically create a **disabled** account. An administrator
must then enable the account before the user can sign in.

.. confval:: Auto-create frontend users

    :type: boolean
    :Default: false

    When enabled, a disabled frontend user account is automatically created for
    authenticated Microsoft users who do not yet have an account.

.. confval:: User Storage Page

    :type: integer (page ID)
    :Default: 0

    Page ID (PID) where new frontend user records will be stored. Use 0 for the
    root level. A page browser is provided to select the page visually.

.. confval:: Default User Groups

    :type: multi-select

    Groups assigned to newly created frontend users. Hold Ctrl/Cmd to select
    multiple groups.

..  _configuration-backend:

Backend configuration
---------------------

The Backend tab shows a **list of backend login configurations**. Each
configuration represents a separate "Sign in with Microsoft" button on the
TYPO3 backend login screen. This allows multiple Azure tenants or app
registrations to be used for backend login.

Each backend configuration has the following fields:

.. confval:: Enabled

    :type: boolean
    :Default: true

    Enable or disable this backend login configuration. Disabled configurations
    will not show a login button.

.. confval:: Login Button Label

    :type: string (required)

    Header shown above the login button on the backend login page (e.g. company
    name or tenant name).

.. confval:: Show Label on Login

    :type: boolean
    :Default: true

    Show the login button label above the sign-in button on the backend login
    page.

.. confval:: Tenant ID

    :type: string

    The **Directory (tenant) ID** from your Microsoft Entra ID app registration.

.. confval:: Client ID

    :type: string

    The **Application (client) ID** from your Microsoft Entra ID app registration.

.. confval:: Client Secret

    :type: string (password)

    The **Client Secret Value** from your Microsoft Entra ID app registration.

.. confval:: Redirect URI (Backend)

    :type: read-only (auto-derived)

    The backend OAuth callback URL is **automatically derived** from the
    registered TYPO3 backend route. It is displayed as a read-only field with a
    copy button. Register this URL as a redirect URI in your Azure app
    registration.

    The URL follows the pattern: ``https://your-domain.com/typo3/azure-login/callback``

..  _configuration-encryption:

Encryption
----------

Client secrets are encrypted at rest using PHP Sodium authenticated encryption
(``sodium_crypto_secretbox``). The encryption key is derived from TYPO3's
``$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']``.

..  warning::
    If the TYPO3 encryption key is not set, the backend module displays a warning
    and secrets cannot be encrypted. Configure the encryption key in the
    :guilabel:`Install Tool` before saving credentials.

    If the encryption key changes after secrets have been saved, previously
    encrypted secrets become unreadable and must be re-entered.

..  _configuration-extension-settings:

Extension settings (fallback)
=============================

As a fallback, global credentials can be configured via **Extension Configuration**
in the TYPO3 backend (:guilabel:`Admin Tools` > :guilabel:`Settings` >
:guilabel:`Extension Configuration` > :guilabel:`ok_azure_login`).

These global settings are used when no per-site database configuration exists
for the current site. This is useful for simple single-site installations or
as a migration path from older versions of the extension.

The following settings are available:

.. confval:: tenantId

    :type: string
    :Default: (empty)

    The **Directory (tenant) ID** from your Microsoft Entra ID app registration.

.. confval:: clientId

    :type: string
    :Default: (empty)

    The **Application (client) ID** from your Microsoft Entra ID app registration.

.. confval:: clientSecret

    :type: string
    :Default: (empty)

    The **Client Secret Value** from your Microsoft Entra ID app registration.

    ..  attention::
        This is the secret **Value**, not the Secret ID. Note that secrets stored
        via Extension Configuration are **not encrypted**.

.. confval:: redirectUriFrontend

    :type: string
    :Default: (empty)

    The OAuth callback URL for **frontend** login.

    Example: ``https://your-domain.com/azure-login/callback``

.. confval:: redirectUriBackend

    :type: string
    :Default: (empty)

    The OAuth callback URL for **backend** login (legacy setting).

    ..  note::
        When using the backend module, the backend redirect URI is automatically
        derived from the TYPO3 route configuration. This setting is only used
        as part of the Extension Configuration fallback.

..  _configuration-resolution-order:

Configuration resolution order
==============================

The extension resolves Azure credentials differently for frontend and backend:

**Frontend login:**

1. Database configuration for the current site root page (from the backend module)
2. Extension Configuration (global fallback)

**Backend login:**

1. Configuration by UID (if a specific config was selected)
2. Database configuration for the current site root page
3. Global backend configuration (``site_root_page_id = 0``)
4. Extension Configuration (global fallback)

If a database record exists but has an empty Tenant ID, it is treated as
unconfigured and the extension falls back to the next source.

..  _configuration-typoscript:

TypoScript configuration
========================

The extension registers a static TypoScript template **Azure Login** that
configures the Fluid template paths. Include it via the :guilabel:`Template`
module (see :ref:`Installation <installation-typoscript>`).

You can override the template paths via TypoScript constants:

..  code-block:: typoscript

    plugin.tx_okazurelogin_login {
        view {
            templateRootPath = EXT:your_sitepackage/Resources/Private/Extensions/OkAzureLogin/Templates/
            partialRootPath = EXT:your_sitepackage/Resources/Private/Extensions/OkAzureLogin/Partials/
            layoutRootPath = EXT:your_sitepackage/Resources/Private/Extensions/OkAzureLogin/Layouts/
        }
    }

..  _configuration-content-element:

Content element settings
========================

**Azure Login** (frontend login)
    Button Theme
        Choose between a **dark** or **light** Microsoft button style.

**Azure Logout** (frontend logout)
    Button Theme
        Choose between a **dark** or **light** Microsoft button style.

    Microsoft Sign-Out
        When enabled, the user is redirected to the Microsoft logout endpoint
        to sign them out of Microsoft as well as TYPO3.

    Redirect URL
        Custom URL to redirect to after logout. Defaults to the site root.

How it works
============

The authentication flow is handled entirely by the extension:

1. The content element renders a **"Sign in with Microsoft"** button linking to the
   Microsoft Entra ID authorization endpoint.
2. The user authenticates at Microsoft and is redirected back with an
   authorization code.
3. A PSR-15 middleware intercepts the callback, exchanges the code for user
   information via the Microsoft Graph API, and injects the user data into the
   TYPO3 authentication chain.
4. The TYPO3 authentication service looks up the user by email in the
   appropriate user table (``fe_users`` or ``be_users``).
5. If a matching, non-disabled user is found, they are logged in and
   redirected to the return URL.
6. For frontend login: if no matching user is found and **auto-create** is
   enabled, a disabled ``fe_users`` record is created. The user sees a message
   that their account is pending activation.
7. For backend login: if no matching user is found, the user is redirected back
   to the login page with an error message.

Security notes
==============

- **Encrypted secrets**: Client secrets stored via the backend module are
  encrypted at rest using PHP Sodium (``sodium_crypto_secretbox``). The
  encryption key is derived from TYPO3's ``encryptionKey``.
- **HMAC-signed state**: The OAuth state parameter is HMAC-signed using TYPO3's
  ``encryptionKey`` and has a 10-minute TTL to prevent CSRF and replay attacks.
- **Per-site isolation**: Each TYPO3 site can have its own Azure credentials,
  preventing credential leakage across multi-site installations.
- **SameSite cookie handling**: The middleware preserves session cookies on the
  OAuth callback redirect and downgrades ``SameSite=Strict`` to ``SameSite=Lax``
  to ensure the browser sends cookies after the cross-site redirect from
  Microsoft.
- **Stale parameter stripping**: The middleware removes stale ``azure_login_error``
  and ``azure_login_success`` query parameters from the return URL before
  appending the current result, preventing parameter accumulation on retries.
- Never commit client secrets to version control.
- Use separate Azure app registrations for development, staging, and production.
- Rotate client secrets regularly before their expiration date.
