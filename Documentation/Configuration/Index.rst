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
can use its own Azure app registration.

..  rst-class:: bignums-xxl

1.  Open the backend module

    In the TYPO3 backend, navigate to :guilabel:`Web` > :guilabel:`Azure Login`.

2.  Select a page in the page tree

    Click on any page that belongs to the site you want to configure. The module
    automatically resolves the site root page from the TYPO3 Site Configuration.

3.  Fill in the credentials

    The form displays the following fields:

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

    .. confval:: Redirect URI (Backend)

        :type: string (URL)

        The OAuth callback URL for **backend** login. This must match one of the
        redirect URIs registered in your Azure app.

        Example: ``https://your-domain.com/typo3/index.php?route=/azure-login/callback``

        ..  important::
            TYPO3 9.5 requires the ``index.php?route=`` format for backend URLs.
            Do **not** use clean URLs like ``/typo3/azure-login/callback``.

    **Frontend User Auto-Creation**

    These settings control automatic creation of frontend user accounts for
    authenticated Microsoft users who do not yet have a TYPO3 account.

    .. confval:: Auto-create frontend users

        :type: boolean
        :Default: disabled

        When enabled, a **disabled** frontend user account is automatically
        created for authenticated Microsoft users who have no matching
        ``fe_users`` record. An administrator must manually enable the account
        before the user can sign in.

    .. confval:: User Storage Page

        :type: int (page ID)
        :Default: 0 (root level)

        The page ID (PID) where newly created frontend user records will be
        stored. Use the page browser button to select a page from the page tree.

    .. confval:: Default User Groups

        :type: multi-select

        Frontend user groups assigned to newly created accounts. Hold
        :kbd:`Ctrl` / :kbd:`Cmd` to select multiple groups.

4.  Save

    Click the save button in the document header. A success message confirms the
    configuration has been saved.

..  important::
    The backend module requires admin access. It is only available to TYPO3
    administrators.

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

..  _configuration-backend-login:

Backend login configuration
---------------------------

Each site can have one or more **backend login configurations**. These are
managed in the "Backend" section of the backend module and control the "Sign in
with Microsoft" buttons on the TYPO3 backend login screen.

.. confval:: Enabled

    :type: boolean
    :Default: enabled

    Enable or disable this backend login configuration. Disabled configurations
    will not show a login button on the backend login screen.

.. confval:: Login Button Label

    :type: string

    Required. A header shown above the login button on the backend login page
    (e.g. the company name). This helps users identify which Azure tenant to
    use when multiple configurations exist.

.. confval:: Show Label on Login

    :type: boolean
    :Default: enabled

    Whether to display the login button label above the sign-in button on the
    backend login page.

.. confval:: Backend Callback URL

    :type: string (read-only)

    The backend callback URL is displayed as a read-only field with a
    **copy-to-clipboard** button. Register this URL as a redirect URI in your
    Azure App Registration.

    The URL follows the TYPO3 9.5 format:
    ``https://your-domain.com/typo3/index.php?route=/azure-login/callback``

    This URL is fixed for all backend configurations on the same domain.

.. confval:: Clone from other Config

    :type: select

    Copy Tenant ID, Client ID, and optionally the Client Secret from another
    existing backend configuration. Useful when multiple sites share the same
    Azure app registration.

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

    The OAuth callback URL for **backend** login.

    Example: ``https://your-domain.com/typo3/index.php?route=/azure-login/callback``

    ..  important::
        TYPO3 9.5 requires the ``index.php?route=`` format for backend URLs.
        Do **not** use clean URLs like ``/typo3/azure-login/callback``.

    ..  note::
        The backend callback route ``/azure-login/callback`` is automatically
        registered by the extension.

..  _configuration-resolution-order:

Configuration resolution order
==============================

The extension resolves Azure credentials in the following order:

1. **Database configuration** for the current site root page (from the backend module)
2. **Extension Configuration** (global fallback from ``ext_conf_template.txt``)

If a database record exists for the site but has an empty Tenant ID, it is
treated as unconfigured and the extension falls back to Extension Configuration.

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
   information via the Microsoft Graph API (email, display name, given name,
   surname), and injects the user data into the TYPO3 authentication chain.
4. The TYPO3 authentication service looks up the user by email in the
   appropriate user table (``fe_users`` or ``be_users``).
5. If a matching, non-disabled user is found, they are logged in and
   redirected to the return URL.
6. **Frontend auto-creation**: If no matching ``fe_users`` record exists and
   auto-creation is enabled for the site, a **disabled** user account is created
   automatically. The user sees an "account pending" info message and an
   administrator must enable the account before the user can sign in.

..  note::
    For **backend login**, a matching ``be_users`` record must already exist.
    Auto-creation is only available for frontend users.

..  tip::
    On repeated login attempts, the extension automatically strips stale
    ``azure_login_error`` and ``azure_login_success`` query parameters from the
    return URL to prevent parameter accumulation.

Security notes
==============

- **Encrypted secrets**: Client secrets stored via the backend module are
  encrypted at rest using PHP Sodium (``sodium_crypto_secretbox``). The
  encryption key is derived from TYPO3's ``encryptionKey``.
- **HMAC-signed state**: The OAuth state parameter is HMAC-signed using TYPO3's
  ``encryptionKey`` and has a 10-minute TTL to prevent CSRF and replay attacks.
- **Per-site isolation**: Each TYPO3 site can have its own Azure credentials,
  preventing credential leakage across multi-site installations.
- Never commit client secrets to version control.
- Use separate Azure app registrations for development, staging, and production.
- Rotate client secrets regularly before their expiration date.
