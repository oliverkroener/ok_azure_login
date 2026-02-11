:navigation-title: Configuration

..  _configuration:

=============
Configuration
=============

After completing the :ref:`Microsoft Entra ID setup <azure>`, configure the
extension in TYPO3 with the credentials obtained from Azure.

Extension settings
==================

The extension is configured via **Extension Configuration** in the TYPO3 backend
(:guilabel:`Admin Tools` > :guilabel:`Settings` > :guilabel:`Extension Configuration` > :guilabel:`ok_azure_login`).

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
        This is the secret **Value**, not the Secret ID. Store it securely.

.. confval:: redirectUriFrontend

    :type: string
    :Default: (empty)

    The OAuth callback URL for **frontend** login. This must match one of the
    redirect URIs registered in your Azure app.

    Example: ``https://your-domain.com/azure-login/callback``

.. confval:: redirectUriBackend

    :type: string
    :Default: (empty)

    The OAuth callback URL for **backend** login. This must match one of the
    redirect URIs registered in your Azure app.

    Example: ``https://your-domain.com/typo3/azure-login/callback``

    ..  note::
        The backend callback route ``/typo3/azure-login/callback`` is
        automatically registered by the extension.

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

Content element settings
========================

When you add the **Azure Login** content element to a page, you can configure:

Login Type
    Choose between **Frontend Login** (authenticates ``fe_users``) and
    **Backend Login** (authenticates ``be_users``). This determines which
    redirect URI is used and which user table is queried.

How it works
============

The authentication flow is handled entirely by the extension:

1. The content element renders a **"Login with Azure"** button linking to the
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

..  important::
    The extension does **not** create new user accounts. A matching
    ``fe_users`` or ``be_users`` record with the same email address must
    already exist in TYPO3.

Security notes
==============

- The OAuth **state** parameter is HMAC-signed using TYPO3's ``encryptionKey``
  and has a 10-minute TTL to prevent CSRF and replay attacks.
- Client secrets should be stored securely. Consider using environment variables
  or TYPO3's encrypted configuration features for production deployments.
- Never commit client secrets to version control.
- Use separate Azure app registrations for development, staging, and production.
- Rotate client secrets regularly before their expiration date.
