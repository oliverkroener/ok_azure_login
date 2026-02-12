# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`ok_azure_login` (composer: `oliverkroener/ok-azure-login`) is a TYPO3 extension that enables frontend and backend user login via Microsoft Entra ID (Azure AD) using the OAuth 2.0 authorization code flow and Microsoft Graph API.

**Extension key**: `ok_azure_login`
**Namespace**: `OliverKroener\OkAzureLogin\`
**TYPO3 compatibility**: 12.4, 13.4, 14.0
**PHP**: ^8.1

## Development Context

This package lives at `backend/packages/ok-azure-login/` within the parent monorepo. It is a **Git submodule** with its own repository — commits must be made inside this directory first, then the parent repo reference updated.

See the root `CLAUDE.md` for DDEV setup, composer commands, and testing infrastructure.

```bash
# From the DDEV project (backend/ as composer root):
ddev exec composer typo3:flush   # Clear caches after any config/class changes
ddev typo3 database:updateschema  # Apply schema changes after modifying ext_tables.sql
```

No package-level build, lint, or test commands exist yet. Use the parent monorepo's `composer ci:static` and `composer ci:tests` for quality checks.

## Architecture

### Authentication Flow Overview

The extension handles two login contexts (FE and BE) through a shared middleware-based OAuth flow:

1. **User clicks "Sign in"** — authorize URL built by `AzureOAuthService::buildAuthorizeUrl()` with HMAC-signed state parameter (includes `siteRootPageId` for per-site config)
2. **Microsoft authenticates** — redirects back with `code` + `state` query params
3. **`AzureOAuthMiddleware`** intercepts the callback (runs before TYPO3 auth middleware in both stacks):
   - Resolves site context from request attribute (FE) or state payload (BE)
   - Validates HMAC-signed state
   - Exchanges code for user info via Microsoft Graph `me()` endpoint
   - Stores user data in `azure_login_user` request attribute
   - Updates `$GLOBALS['TYPO3_REQUEST']` so the auth chain can read the attribute
   - Injects login trigger fields into request body (`login_status=login` for BE, `logintype=login` for FE)
4. **`AzureRequestTokenListener`** provides a valid CSRF token so TYPO3 v13+ doesn't block the request
5. **`AzureLoginAuthService`** (in TYPO3's auth chain) looks up user by email in `fe_users`/`be_users`, returns 200 (authenticated)
6. **Middleware preserves Set-Cookie headers** from the auth chain response and copies them to the redirect response, downgrading `SameSite=Strict` to `SameSite=Lax` for the callback only
7. **Middleware redirects** to the return URL from state (HTTP 303)

### Frontend Login

- Extbase content element plugin (`okazurelogin_login`) with `LoginController::showAction`
- Renders a "Login with Azure" button linking to the authorize URL
- Shows configuration error message when Azure credentials are not set
- Template: `Templates/Login/Show.html`, Layout: `Layouts/Default.html`

### Frontend Logout

- Extbase content element plugin (`okazurelogin_logout`) with `LogoutController`
- FlexForm options: button theme, Microsoft sign-out redirect, custom redirect URL
- Optional redirect to Microsoft logout endpoint (`login.microsoftonline.com/.../logout`)

### Backend Login

- **Login provider** (`AzureLoginProvider`) registered in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders']`
- Shows "Sign in with Microsoft" button on the `/typo3/` login screen as a separate tab
- Template: `Templates/Login/AzureLoginForm.html` (uses `<f:layout name="Login" />` from EXT:backend)
- Callback route: `/typo3/azure-login/callback` → `AzureCallbackController` (public access, no CSRF token required)
- Implements both `render()` (v12) and `modifyView()` (v13+) for cross-version compatibility
- Iterates all sites via `SiteFinder` to find first site with valid backend OAuth config

### Backend Configuration Module

- TYPO3 backend module under **Web** group with page tree navigation
- Module key: `web_okazurelogin`, path: `/module/web/azure-login`
- Admin-only access, icon: `ext-ok-azure-login-microsoft`
- `ConfigurationController` with `editAction` (form) and `saveAction` (POST, PRG pattern)
- Manages per-site Azure credentials stored in `tx_okazurelogin_configuration` table
- Client secret encrypted with Sodium before storage, never sent to browser
- Warns when TYPO3 encryption key is missing (red danger callout)

### Key Classes

| Class | Purpose |
|-------|---------|
| `Service/AzureOAuthService` | Core OAuth service: builds authorize URLs, exchanges codes for user info via Graph API, creates/validates HMAC-signed state params, per-site config resolution |
| `Service/EncryptionService` | Sodium-based authenticated encryption for client secrets using `sodium_crypto_secretbox`; key derived from TYPO3 encryption key |
| `Domain/Repository/AzureConfigurationRepository` | CRUD for `tx_okazurelogin_configuration` table with transparent encrypt/decrypt of client secret |
| `Middleware/AzureOAuthMiddleware` | PSR-15 middleware in both FE+BE stacks; resolves site context, intercepts OAuth callbacks, injects auth data into request, preserves session cookies with SameSite=Lax on redirect |
| `Authentication/AzureLoginAuthService` | `AbstractAuthenticationService` subclass; looks up users by email in auth chain, returns 200 for Azure-authenticated requests |
| `EventListener/AzureRequestTokenListener` | Creates valid CSRF `RequestToken` for Azure callbacks (TYPO3 v13+ compatibility) |
| `LoginProvider/AzureLoginProvider` | Backend login provider; renders "Sign in with Microsoft" button, resolves site context for backend OAuth |
| `Controller/LoginController` | Extbase FE controller; `showAction` renders the frontend login button with configuration error handling |
| `Controller/LogoutController` | Extbase FE controller; handles logout with optional Microsoft sign-out redirect |
| `Controller/Backend/ConfigurationController` | Backend module controller; manages per-site Azure configuration with encryption key validation |
| `Controller/Backend/AzureCallbackController` | Backend route handler; redirects to `/typo3` after successful OAuth callback |

### Configuration

Azure credentials are stored **per site** in the database table `tx_okazurelogin_configuration`, with **Extension Configuration** (`ext_conf_template.txt`) as a fallback for backward compatibility.

#### Database Configuration (primary — per site)

Managed via the backend module (Web > Azure Login). One record per site root page:

| Column | Description |
|--------|-------------|
| `site_root_page_id` | TYPO3 site root page ID (unique key) |
| `tenant_id` | Microsoft Entra Directory (Tenant) ID |
| `client_id` | Application (Client) ID from Azure App Registration |
| `client_secret_encrypted` | Sodium-encrypted client secret |
| `redirect_uri_frontend` | OAuth callback URL for frontend login |
| `redirect_uri_backend` | OAuth callback URL for backend login |

#### Extension Configuration (fallback — global)

Settings in `ext_conf_template.txt`: `tenantId`, `clientId`, `clientSecret`, `redirectUriFrontend`, `redirectUriBackend`.

Used when no database record exists for the current site.

#### Config Resolution Order

`AzureOAuthService::getConfiguration()`:
1. If `siteRootPageId > 0`, try database via `AzureConfigurationRepository`
2. If no DB record or tenant ID is empty, fall back to `ExtensionConfiguration::get('ok_azure_login')`

#### Encryption

Client secrets are encrypted at rest using PHP Sodium (`sodium_crypto_secretbox`):
- Key derived from `$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']` via `sodium_crypto_generichash`
- Random nonce prepended to ciphertext, base64-encoded for storage
- The backend module warns if the encryption key is not set

TypoScript (`constants.typoscript` / `setup.typoscript`) only configures Fluid template paths for the FE plugin.

### Registration Points

- **`ext_localconf.php`**: FE plugins (login + logout), auth service (subtypes `getUserFE,authUserFE,getUserBE,authUserBE`, priority 82), icon registration (`ext-ok-azure-login-microsoft`), backend login provider
- **`Configuration/Backend/Modules.php`**: Backend configuration module under Web group with page tree
- **`Configuration/Backend/Routes.php`**: `/azure-login/callback` route with `'access' => 'public'`
- **`Configuration/Services.yaml`**: DI config, `AzureLoginProvider` marked `public: true`, event listener registration
- **`Configuration/RequestMiddlewares.php`**: `AzureOAuthMiddleware` in both `frontend` (before FE auth) and `backend` (after routing, before BE auth) stacks
- **`Configuration/TCA/Overrides/tt_content.php`**: FE content element plugin registration, static TypoScript
- **`Configuration/page.tsconfig`**: New Content Element Wizard entries under custom "Azure Login" group

### Database Schema

```sql
CREATE TABLE tx_okazurelogin_configuration (
    site_root_page_id int(11) unsigned DEFAULT '0' NOT NULL,
    tenant_id varchar(255) DEFAULT '' NOT NULL,
    client_id varchar(255) DEFAULT '' NOT NULL,
    client_secret_encrypted text,
    redirect_uri_frontend varchar(1024) DEFAULT '' NOT NULL,
    redirect_uri_backend varchar(1024) DEFAULT '' NOT NULL,
    UNIQUE KEY site_root (site_root_page_id)
);
```

### Logging

Both `AzureOAuthMiddleware` and `AzureLoginAuthService` implement `LoggerAwareInterface` and use PSR-3 `$this->logger->debug()` for diagnostic logging. To see these messages, configure TYPO3's log writer for the `OliverKroener.OkAzureLogin` namespace in `settings.php`:

```php
'LOG' => [
    'OliverKroener' => [
        'OkAzureLogin' => [
            'writerConfiguration' => [
                \Psr\Log\LogLevel::DEBUG => [
                    \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                        'logFile' => 'var/log/azure-login.log',
                    ],
                ],
            ],
        ],
    ],
],
```

### Dependencies

- `microsoft/microsoft-graph` ^2 — Graph SDK for user profile retrieval
- `microsoft/kiota-authentication-phpleague` ^1 — OAuth token handling via `AuthorizationCodeContext`

### Languages

Translations: English (default), German (`de.*.xlf`), French (`fr.*.xlf`).

Language file groups:
- `locallang.xlf` — Frontend plugin labels and messages
- `locallang_db.xlf` — TCA, FlexForm, and content element wizard labels
- `locallang_be_module.xlf` — Backend configuration module labels
- `locallang_csh_tx_okazurelogin.xlf` — Context-sensitive help

### OAuth Scopes

The extension requests `openid profile User.Read` (delegated permissions, user context).

## TYPO3 13 Compatibility Notes

These issues were discovered and fixed during development. Keep them in mind when making changes:

### Template resolution in `modifyView()`

TYPO3 13's `LoginController` only configures `EXT:backend/Resources/Private/Templates` as template root paths. Extensions must add their own paths explicitly:

```php
$templatePaths = $view->getRenderingContext()->getTemplatePaths();
$currentPaths = $templatePaths->getTemplateRootPaths();
$currentPaths[] = 'EXT:ok_azure_login/Resources/Private/Templates';
$templatePaths->setTemplateRootPaths($currentPaths);
```

Without this, TYPO3 throws `InvalidTemplateResourceException: Tried resolving a template file for controller action "Default->Login/AzureLoginForm"`.

### Doctrine DBAL 4 parameter types

TYPO3 13 uses Doctrine DBAL 4 which replaces `\PDO::PARAM_INT` with `\Doctrine\DBAL\ParameterType::INTEGER`. All `QueryBuilder::createNamedParameter()` calls must use the new enum.

### Backend route public access

The `/azure-login/callback` route requires `'access' => 'public'` in `Configuration/Backend/Routes.php`. Without this, TYPO3 requires a CSRF token that Microsoft's redirect cannot provide.

### `$GLOBALS['TYPO3_REQUEST']` must be updated

PSR-7 requests are immutable. When the middleware adds attributes (`azure_login_user`) or modifies the parsed body, the global `$GLOBALS['TYPO3_REQUEST']` must be explicitly updated so that the auth service (which reads from the global) sees the changes:

```php
$request = $request->withAttribute('azure_login_user', $userInfo);
$GLOBALS['TYPO3_REQUEST'] = $request;
```

### SameSite cookie handling after cross-site OAuth redirect

TYPO3 defaults `BE.cookieSameSite` to `strict`. After a cross-site redirect from Microsoft, browsers will NOT send `SameSite=Strict` cookies on the subsequent navigation. `FE.cookieSameSite` defaults to `lax`, so frontend is unaffected — but if it is ever changed to `strict`, the same issue will occur. The middleware must:

1. Preserve `Set-Cookie` headers from the auth chain response (a new `RedirectResponse` discards all headers)
2. Downgrade `SameSite=Strict` to `SameSite=Lax` on the callback redirect response only

```php
$redirect = new RedirectResponse($returnUrl, 303);
foreach ($response->getHeader('Set-Cookie') as $cookie) {
    $cookie = preg_replace('/SameSite=Strict/i', 'SameSite=Lax', $cookie);
    $redirect = $redirect->withAddedHeader('Set-Cookie', $cookie);
}
```

## Known Issues

- **Backend login session persistence**: The auth chain succeeds (user found, BE_USER set, cookies carried with SameSite=Lax), but session persistence after the OAuth redirect may need further investigation if the login still redirects to the login page.
