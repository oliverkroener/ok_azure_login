# TYPO3 Azure Login (ok_azure_login)

TYPO3 extension for frontend and backend login via Microsoft Entra ID (Azure AD) using the OAuth 2.0 authorization code flow and Microsoft Graph API.

|                  |                                              |
|------------------|----------------------------------------------|
| Extension key    | `ok_azure_login`                             |
| Composer         | `oliverkroener/ok-azure-login`               |
| TYPO3            | 12.4, 13.4, 14.0                            |
| PHP              | ^8.1                                         |

## Features

- **Frontend login** via "Sign in with Microsoft" content element
- **Backend login** via login provider on the TYPO3 backend login screen
- **Per-site configuration** with encrypted client secret storage
- **Multi-tenant support** with multiple backend login buttons
- OAuth 2.0 authorization code flow with HMAC-signed state parameter
- User lookup by email in `fe_users` / `be_users`
- Backend redirect URI auto-derived from route configuration
- Frontend logout with optional Microsoft sign-out redirect
- Translations: English, German, French

## Quick start

1. Register an app in [Microsoft Entra ID](https://portal.azure.com) (see [Azure setup docs](Documentation/Azure.rst))
2. Install the extension via Composer:
   ```bash
   composer require oliverkroener/ok-azure-login
   ```
3. Configure credentials in **Web > Azure Login** backend module
4. Add the **Azure Login** content element to a frontend page
5. Register the redirect URIs from the backend module in your Azure app

## Configuration

Credentials are managed per TYPO3 site via the backend module. Each site stores:

- **Tenant ID** and **Client ID** from Azure App Registration
- **Client Secret** (encrypted at rest with PHP Sodium)
- **Redirect URI (Frontend)** -- manually configured, points to the login page
- **Redirect URI (Backend)** -- auto-generated from route config, shown as read-only with copy button

The backend redirect URI (`/typo3/azure-login/callback`) is derived from `Configuration/Backend/Routes.php` and cannot be misconfigured.

Global credentials via Extension Configuration serve as a fallback for single-site setups.

## Documentation

Full documentation is in the [Documentation/](Documentation/) directory:

- [Azure Entra ID setup](Documentation/Azure.rst)
- [Configuration](Documentation/Configuration/Index.rst)
- [Installation](Documentation/Installation.rst)
- [FAQ](Documentation/Faq.rst)

## Security

- Client secrets encrypted at rest (PHP Sodium `sodium_crypto_secretbox`)
- HMAC-signed OAuth state with 10-minute TTL
- Per-site credential isolation
- CSRF token handling for TYPO3 v13+

## Requirements

- `microsoft/microsoft-graph` ^2
- `microsoft/kiota-authentication-phpleague` ^1
- TYPO3 encryption key must be configured
