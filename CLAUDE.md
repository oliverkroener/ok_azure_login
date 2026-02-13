# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`ok_azure_login` (composer: `oliverkroener/ok-azure-login`) is a TYPO3 extension that enables frontend and backend user login via Microsoft Entra ID (Azure AD) using the OAuth 2.0 authorization code flow and Microsoft Graph API.

**Extension key**: `ok_azure_login`
**Namespace**: `OliverKroener\OkAzureLogin\`
**TYPO3 compatibility**: 11.5
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
4. **`AzureLoginAuthService`** (in TYPO3's auth chain) looks up user by email in `fe_users`/`be_users`, returns 200 (authenticated)
5. **Middleware checks login result**: For FE, if login fails and auto-create is enabled, creates a disabled fe_user. For BE, redirects to `/typo3/login` with error code.
6. **Middleware strips stale login params** from the return URL before appending the current result
7. **Middleware preserves Set-Cookie headers** from the auth chain response and copies them to the redirect response, downgrading `SameSite=Strict` to `SameSite=Lax` for the callback only
8. **Middleware redirects** to the return URL from state (HTTP 303)

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
| `Service/AzureOAuthService` | Core OAuth service: builds authorize URLs, exchanges codes for user info via Graph API, creates/validates HMAC-signed state params, per-site config resolution. Backend redirect URI is auto-derived from route config via `getBackendCallbackUrl()` |
| `Service/EncryptionService` | Sodium-based authenticated encryption for client secrets using `sodium_crypto_secretbox`; key derived from TYPO3 encryption key |
| `Domain/Repository/AzureConfigurationRepository` | CRUD for `tx_okazurelogin_configuration` table with transparent encrypt/decrypt of client secret |
| `Middleware/AzureOAuthMiddleware` | PSR-15 middleware in both FE+BE stacks; resolves site context, intercepts OAuth callbacks, injects auth data into request, preserves session cookies with SameSite=Lax on redirect |
| `Authentication/AzureLoginAuthService` | `AbstractAuthenticationService` subclass; looks up users by email in auth chain, returns 200 for Azure-authenticated requests |
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
| `site_root_page_id` | TYPO3 site root page ID (0 for backend-only configs) |
| `enabled` | Whether this config is active (default 1) |
| `show_label` | Show the login button label on the backend login page (default 1) |
| `backend_login_label` | Label/header shown above the backend login button (e.g. company name) |
| `tenant_id` | Microsoft Entra Directory (Tenant) ID |
| `client_id` | Application (Client) ID from Azure App Registration |
| `client_secret_encrypted` | Sodium-encrypted client secret |
| `redirect_uri_frontend` | OAuth callback URL for frontend login |
| `redirect_uri_backend` | Legacy field, no longer used for OAuth flow (backend URI is auto-derived from route config) |
| `auto_create_fe_user` | Enable auto-creation of disabled fe_users on first Azure login |
| `default_fe_groups` | CSV of fe_group UIDs assigned to auto-created users |
| `fe_user_storage_pid` | PID where auto-created fe_user records are stored |

#### Backend Redirect URI (auto-derived)

The backend OAuth redirect URI is **not manually configured**. It is automatically derived from the registered backend route `azure_login_callback` in `Configuration/Backend/Routes.php` using TYPO3's `BackendUriBuilder::buildUriFromRoute()`. This produces the absolute URL (e.g. `https://example.com/typo3/azure-login/callback`). The backend config form shows this URL as a read-only field with a copy button so admins can register it in Azure AD.

#### Extension Configuration (fallback — global)

Settings in `ext_conf_template.txt`: `tenantId`, `clientId`, `clientSecret`, `redirectUriFrontend`.

Used when no database record exists for the current site.

#### Config Resolution Order

`AzureOAuthService::getConfiguration()`:

**Backend** (`loginType === 'backend'`):
1. If `configUid > 0`, try by UID via `AzureConfigurationRepository::findByUid()`
2. If `siteRootPageId > 0`, try by site via `AzureConfigurationRepository::findBySiteRootPageId()`
3. Try global backend config (site_root_page_id = 0)
4. Fall back to `ExtensionConfiguration::get('ok_azure_login')`

**Frontend** (`loginType === 'frontend'`):
1. If `siteRootPageId > 0`, try database via `AzureConfigurationRepository::findBySiteRootPageId()`
2. Fall back to `ExtensionConfiguration::get('ok_azure_login')`

#### Encryption

Client secrets are encrypted at rest using PHP Sodium (`sodium_crypto_secretbox`):
- Key derived from `$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']` via `sodium_crypto_generichash`
- Random nonce prepended to ciphertext, base64-encoded for storage
- The backend module warns if the encryption key is not set

TypoScript (`constants.typoscript` / `setup.typoscript`) only configures Fluid template paths for the FE plugin.

### Registration Points

- **`ext_localconf.php`**: FE plugins (login + logout), auth service (subtypes `getUserFE,authUserFE,getUserBE,authUserBE`, priority 82), icon registration (`ext-ok-azure-login-microsoft`), backend login provider
- **`ext_tables.php`**: Backend configuration module registration via `addModule()` under Web group with page tree navigation
- **`Configuration/Backend/Routes.php`**: `/azure-login/callback` route with `'access' => 'public'`, plus module sub-routes for save/list/edit/delete actions
- **`Configuration/Services.yaml`**: DI config, `AzureLoginProvider` marked `public: true`
- **`Configuration/RequestMiddlewares.php`**: `AzureOAuthMiddleware` in both `frontend` (before FE auth) and `backend` (after routing, before BE auth) stacks
- **`Configuration/TCA/Overrides/tt_content.php`**: FE content element plugin registration, static TypoScript
- **`Configuration/page.tsconfig`**: New Content Element Wizard entries under custom "Azure Login" group

### Database Schema

```sql
CREATE TABLE tx_okazurelogin_configuration (
    site_root_page_id int(11) unsigned DEFAULT '0' NOT NULL,
    enabled tinyint(1) unsigned DEFAULT '1' NOT NULL,
    show_label tinyint(1) unsigned DEFAULT '1' NOT NULL,
    backend_login_label varchar(255) DEFAULT '' NOT NULL,
    tenant_id varchar(255) DEFAULT '' NOT NULL,
    client_id varchar(255) DEFAULT '' NOT NULL,
    client_secret_encrypted text,
    redirect_uri_frontend varchar(1024) DEFAULT '' NOT NULL,
    redirect_uri_backend varchar(1024) DEFAULT '' NOT NULL,
    auto_create_fe_user tinyint(1) unsigned DEFAULT '0' NOT NULL,
    default_fe_groups varchar(255) DEFAULT '' NOT NULL,
    fe_user_storage_pid int(11) unsigned DEFAULT '0' NOT NULL,
    KEY site_root (site_root_page_id)
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

## TYPO3 11.5 Compatibility Notes

These patterns are specific to the TYPO3 11.5 APIs used by this extension:

### Backend module registration

TYPO3 11 uses `ExtensionManagementUtility::addModule()` in `ext_tables.php` with a `routeTarget` pointing to a controller method. Module sub-routes are registered in `Configuration/Backend/Routes.php`. The v12+ `Configuration/Backend/Modules.php` format is not supported.

### ModuleTemplate rendering pattern

TYPO3 11 uses `ModuleTemplate` + `StandaloneView` (not `ModuleTemplateFactory`):

```php
$moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
$view = GeneralUtility::makeInstance(StandaloneView::class);
$view->setTemplate('...');
$moduleTemplate->setContent($view->render());
return new HtmlResponse($moduleTemplate->renderContent());
```

### JavaScript modules

TYPO3 11 uses RequireJS (not ES modules). JS files follow CamelCase naming (`Backend/DeleteConfirm.js`) and are loaded via `loadRequireJsModule('TYPO3/CMS/OkAzureLogin/Backend/DeleteConfirm')`.

### Flash message severity

Use integer constants from `AbstractMessage` (e.g., `FlashMessage::OK`) instead of the `ContextualFeedbackSeverity` enum (v12+).

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

### Return URL must be stripped of stale login params

When a user retries login from a page that already has `?azure_login_error=...` in the URL, that URL becomes the new OAuth `returnUrl` stored in the state parameter. The middleware then appends another `azure_login_error` or `azure_login_success`, causing parameter accumulation.

`AzureOAuthMiddleware::stripLoginParams()` removes `azure_login_error` and `azure_login_success` from the return URL before appending the current result.

### Login success takes priority over error display

`LoginController::showAction` must check `azure_login_success` before `azure_login_error`. When the auth flow ultimately succeeds, only the success message is shown — even if stale error params somehow survive in the URL.

## Known Issues

- **Backend login session persistence**: The auth chain succeeds (user found, BE_USER set, cookies carried with SameSite=Lax), but session persistence after the OAuth redirect may need further investigation if the login still redirects to the login page.
