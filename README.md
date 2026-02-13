# Azure Login for TYPO3

A TYPO3 extension that enables frontend and backend user login via Microsoft Entra ID (formerly Azure AD) using the OAuth 2.0 authorization code flow and Microsoft Graph API.

**TYPO3 compatibility**: 11.5
**PHP**: ^8.1

## Features

- **Frontend login** via "Sign in with Microsoft" content element
- **Backend login** via login provider on the TYPO3 backend login screen
- **Per-site configuration** with dedicated backend module (Web > Azure Login)
- **Multiple backend login configs** for different Azure tenants
- **Encrypted client secrets** using PHP Sodium (`sodium_crypto_secretbox`)
- **Auto-create frontend users** (disabled by default, requires admin activation)
- **Frontend logout** with optional Microsoft sign-out redirect
- **HMAC-signed state parameter** with 10-minute TTL for CSRF protection
- **SameSite cookie handling** for cross-site OAuth redirects
- Translations: English, German, French

## Requirements

- TYPO3 11.5
- PHP 8.1+
- `microsoft/microsoft-graph` ^2
- `microsoft/kiota-authentication-phpleague` ^1

## Installation

```bash
composer req oliverkroener/ok-azure-login
vendor/bin/typo3 database:updateschema
```

Then include the static TypoScript template **Azure Login** via the Template module.

## Configuration

### 1. Register an Azure App

In [Microsoft Entra ID](https://portal.azure.com), register an application with:

- **Redirect URIs**: Your frontend login page URL and `/typo3/azure-login/callback` for backend
- **API permissions**: `openid`, `profile`, `User.Read` (delegated)
- **Client secret**: Copy the secret **Value** (not the Secret ID)

### 2. Configure in TYPO3

Navigate to **Web > Azure Login** in the TYPO3 backend:

- **Frontend tab**: Enter Tenant ID, Client ID, Client Secret, and frontend Redirect URI per site
- **Backend tab**: Create one or more backend login configurations with label, credentials, and auto-derived callback URL

Alternatively, use **Extension Configuration** (`Admin Tools > Settings > Extension Configuration > ok_azure_login`) as a global fallback.

### Frontend user auto-creation

When enabled per site, the extension automatically creates a **disabled** `fe_users` record for authenticated Microsoft users without an existing account. An administrator must enable the account before the user can sign in. Configure default user groups and storage page in the backend module.

## How it works

1. User clicks "Sign in with Microsoft"
2. User authenticates at Microsoft Entra ID
3. Microsoft redirects back with an authorization code
4. PSR-15 middleware exchanges the code for user info via Microsoft Graph API
5. TYPO3 authentication service matches the user by email in `fe_users` or `be_users`
6. User is logged in and redirected to the return URL

## Documentation

Full documentation is available in the `Documentation/` directory.

## License

GPL-2.0-or-later

## Author

Oliver Kroener - [oliver-kroener.de](https://www.oliver-kroener.de)
