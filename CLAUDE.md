# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`ok_azure_login` (composer: `oliverkroener/ok-azure-login`) is a TYPO3 extension that enables frontend and backend user login via Microsoft Entra ID (Azure AD) using the OAuth 2.0 authorization code flow and Microsoft Graph API.

**Extension key**: `ok_azure_login`
**Namespace**: `OliverKroener\OkAzureLogin\`
**TYPO3 compatibility**: 9.5
**PHP**: ^7.4

## Development Context

This package lives at `packages/ok_azure_login/` within the parent project. It is a **Git submodule** with its own repository — commits must be made inside this directory first, then the parent repo reference updated.

See the root `CLAUDE.md` for DDEV setup, composer commands, and testing infrastructure.

```bash
# From the DDEV project:
ddev exec vendor/bin/typo3cms cache:flush   # Clear caches after any config/class changes
ddev typo3 database:updateschema             # Apply schema changes after modifying ext_tables.sql
```

No package-level build, lint, or test commands exist yet. Use the parent monorepo's `composer ci:static` and `composer ci:tests` for quality checks.

## Architecture

### Authentication Flow Overview

The extension handles two login contexts (FE and BE) through a shared middleware-based OAuth flow:

1. **User clicks "Sign in"** — authorize URL built by `AzureOAuthService::buildAuthorizeUrl()` with HMAC-signed state parameter (includes `siteRootPageId` and `configUid` for per-site/per-config resolution)
2. **Microsoft authenticates** — redirects back with `code` + `state` query params
3. **`AzureOAuthMiddleware`** intercepts the callback (runs before TYPO3 auth middleware in both stacks):
   - Resolves site context from request attribute (FE) or state payload (BE)
   - Validates HMAC-signed state (10-minute TTL)
   - Strips stale `azure_login_error`/`azure_login_success` params from return URL via `stripLoginParams()`
   - Exchanges code for access token via Guzzle, then fetches user info (email, displayName, givenName, surname) via Graph SDK v1 `me()` endpoint
   - Stores user data in `azure_login_user` request attribute
   - Updates `$GLOBALS['TYPO3_REQUEST']` so the auth chain can read the attribute
   - Injects login trigger fields into request body (`login_status=login` for BE, `logintype=login` for FE)
4. **`AzureLoginAuthService`** (in TYPO3's auth chain, priority 82) looks up user by email in `fe_users`/`be_users`, returns 200 (authenticated)
5. **Middleware checks login result**: For FE, if login fails and auto-create is enabled, creates a disabled fe_user via `handleFailedFrontendLogin()`. For BE, redirects with error code.
6. **Middleware preserves Set-Cookie headers** from the auth chain response and copies them to the redirect response, downgrading `SameSite=Strict` to `SameSite=Lax` for the callback only
7. **Middleware redirects** to the return URL from state (HTTP 303) with `azure_login_success` or `azure_login_error` query params

### Frontend User Auto-Creation

When `auto_create_fe_user` is enabled for a site configuration:

- If a Microsoft-authenticated user has no matching `fe_users` record, a **disabled** record is created automatically
- The new user gets: email as username, displayName/givenName/surname from Graph API, a random password, configured default FE groups, and the configured storage PID
- The user sees an "account pending" info message (blue, not red error)
- An administrator must manually enable the account before the user can sign in
- If the user already exists but is disabled, just show "account pending"

### Frontend Login

- Extbase content element plugin (`okazurelogin_login`) with `LoginController::showAction`
- Renders a "Login with Azure" button linking to the authorize URL
- Shows configuration error message when Azure credentials are not set
- Login success takes priority over error display (`azure_login_success` checked before `azure_login_error`)
- Template: `Templates/Login/Show.html`, Layout: `Layouts/Default.html`

### Frontend Logout

- Extbase content element plugin (`okazurelogin_logout`) with `LogoutController`
- FlexForm options: button theme, Microsoft sign-out redirect, custom redirect URL
- Optional redirect to Microsoft logout endpoint (`login.microsoftonline.com/.../logout`)

### Backend Login

- **Login provider** (`AzureLoginProvider`) registered in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders']`
- Replaces the default `UsernamePasswordLoginProvider`; the custom template includes both Azure buttons and standard username/password fields
- Template: `Templates/Login/AzureLoginForm.html` (uses `<f:layout name="Login" />` from EXT:backend)
- The `UserPassLogin` RequireJS module is loaded in `AzureLoginProvider::render()` (not via `<f:be.pageRenderer>`) to match the TYPO3 9 pattern
- Callback route: `/azure-login/callback` → `AzureCallbackController` (public access, no CSRF token required)
- **TYPO3 9 callback URL format**: `https://domain.com/typo3/index.php?route=/azure-login/callback` (NOT clean URLs — see TYPO3 9.5 notes below)
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
| `Middleware/AzureOAuthMiddleware` | PSR-15 middleware in both FE+BE stacks; resolves site context, intercepts OAuth callbacks, strips stale login params from return URL, injects auth data into request, handles FE user auto-creation on failed login, preserves session cookies with SameSite=Lax on redirect |
| `Authentication/AzureLoginAuthService` | `AbstractAuthenticationService` subclass; looks up users by email in auth chain, returns 200 for Azure-authenticated requests |
| `LoginProvider/AzureLoginProvider` | Backend login provider; renders "Sign in with Microsoft" button + standard username/password form, loads `UserPassLogin` JS module |
| `Controller/LoginController` | Extbase FE controller; `showAction` renders the frontend login button with configuration error handling |
| `Controller/LogoutController` | Extbase FE controller; handles logout with optional Microsoft sign-out redirect |
| `Controller/Backend/ConfigurationController` | Backend module controller; manages per-site Azure configuration with encryption key validation, FE user auto-creation settings (storage PID via Element Browser, default FE groups), and backend callback URL display with copy-to-clipboard |
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
| `auto_create_fe_user` | Enable auto-creation of disabled fe_users on failed FE login (0/1) |
| `default_fe_groups` | Comma-separated list of fe_groups UIDs assigned to auto-created users |
| `fe_user_storage_pid` | Page ID where auto-created fe_user records are stored |

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
- **`ext_tables.php`**: Backend configuration module registration via `addModule()` under Web group with page tree navigation
- **`Configuration/Backend/Routes.php`**: `/azure-login/callback` route with `'access' => 'public'`, plus module sub-routes for save/list/edit/delete actions
- **`Configuration/RequestMiddlewares.php`**: `AzureOAuthMiddleware` in both `frontend` (before FE auth) and `backend` (before routing — required for TYPO3 9 callback URL handling) stacks
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
    auto_create_fe_user tinyint(1) unsigned DEFAULT '0' NOT NULL,
    default_fe_groups varchar(255) DEFAULT '' NOT NULL,
    fe_user_storage_pid int(11) unsigned DEFAULT '0' NOT NULL,
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

- `microsoft/microsoft-graph` ^1.69 — Graph SDK v1 for user profile retrieval (v2 requires PHP 8.1+)

### Languages

Translations: English (default), German (`de.*.xlf`), French (`fr.*.xlf`).

Language file groups:
- `locallang.xlf` — Frontend plugin labels and messages
- `locallang_db.xlf` — TCA, FlexForm, and content element wizard labels
- `locallang_be_module.xlf` — Backend configuration module labels
- `locallang_csh_tx_okazurelogin.xlf` — Context-sensitive help

### OAuth Scopes

The extension requests `openid profile User.Read` (delegated permissions, user context).

## TYPO3 9.5 Compatibility Notes

These patterns are specific to the TYPO3 9.5 APIs used by this extension:

### Backend route URL format (critical)

TYPO3 9 does **not** support clean backend URLs. All backend routes are accessed via `index.php` with a `route` query parameter:

- **Correct**: `https://domain.com/typo3/index.php?route=/azure-login/callback`
- **Wrong**: `https://domain.com/typo3/azure-login/callback` (Apache 404)

This affects:
- The **redirect URI** configured in Microsoft Azure portal and in the `tx_okazurelogin_configuration` database table
- The backend middleware must run **before** `typo3/cms-backend/backend-routing` (not after), so it can intercept the OAuth callback before TYPO3's route resolver runs

### Middleware positioning and direct auth handling

Both FE and BE middleware stacks handle authentication **directly** — neither delegates to `$handler->handle()` for OAuth callbacks. Instead, each creates the user session object itself (`BackendUserAuthentication` / `FrontendUserAuthentication`), calls `start()`, captures `Set-Cookie` headers, and returns a redirect response.

**Why**: Passing through the middleware chain via `$handler->handle()` would reach the page/route resolver, which fails because:
- **Backend**: TYPO3 9's `BackendRouteInitialization` resolves routes via the `route` query parameter; the callback URL confuses routing
- **Frontend**: The callback URL (`/azure-login/callback`) is not a real TYPO3 page, so the page resolver throws `PageNotFoundException`

**Backend** middleware runs **before** routing:
```php
// Configuration/RequestMiddlewares.php — backend stack
'after' => ['typo3/cms-core/normalized-params-attribute'],
'before' => ['typo3/cms-backend/backend-routing'],
```

**Frontend** middleware runs **before** authentication:
```php
// Configuration/RequestMiddlewares.php — frontend stack
'before' => ['typo3/cms-frontend/authentication'],
```

The frontend handler also registers the `frontend.user` Context aspect (via `UserAspect`) and sets `$GLOBALS['TSFE']->fe_user` for backwards-compatibility, mirroring what `FrontendUserAuthenticator` would do.

### Backend login provider and template

The extension replaces the default `UsernamePasswordLoginProvider` (unset in `ext_localconf.php`) with `AzureLoginProvider`, which renders both Azure login buttons and the standard username/password form in a single template.

Key requirements for the login template in TYPO3 9:
- `UserPassLogin` RequireJS module must be loaded in `AzureLoginProvider::render()` via `$pageRenderer->loadRequireJsModule()` (not via `<f:be.pageRenderer>` in the template)
- Password field must have `data-rsa-encryption="t3-field-userident"` attribute for TYPO3 9's password handling
- Use the same HTML structure and CSS classes as TYPO3 9's core `UserPassLoginForm.html`

### Backend module registration

TYPO3 9 uses `ExtensionManagementUtility::addModule()` in `ext_tables.php` with a `routeTarget` pointing to a controller method. Module sub-routes are registered in `Configuration/Backend/Routes.php`. The v12+ `Configuration/Backend/Modules.php` format is not supported.

### ModuleTemplate rendering pattern

TYPO3 9 uses `ModuleTemplate` + `StandaloneView` (not `ModuleTemplateFactory`):

```php
$moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
$view = GeneralUtility::makeInstance(StandaloneView::class);
$view->setTemplate('...');
$moduleTemplate->setContent($view->render());
return new HtmlResponse($moduleTemplate->renderContent());
```

### JavaScript modules

TYPO3 9 uses RequireJS (not ES modules). JS files follow CamelCase naming and are loaded via `loadRequireJsModule('TYPO3/CMS/OkAzureLogin/Backend/...')`.

Available modules:
- **`Backend/DeleteConfirm.js`** — Intercepts delete button clicks, shows TYPO3 modal confirmation dialog
- **`Backend/PageBrowser.js`** — Element Browser integration for selecting storage PID; defines `window.setFormValueFromBrowseWin` callback (TYPO3 9 pattern), opens popup, updates hidden input + display label
- **`Backend/CopyCallbackUrl.js`** — Copies the backend callback URL to clipboard via `document.execCommand('copy')` fallback (no Clipboard API needed for TYPO3 9 browsers), shows checkmark feedback

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

### FE `isLoggedIn` requires user groups (critical)

TYPO3 9's `UserAspect::isLoggedIn()` for **frontend** users requires **both** `uid > 0` **AND** non-empty `groupData['uid']`. A user without any FE group is considered "not logged in" by the Context API even if their session is valid.

**Do NOT use** `$context->getPropertyFromAspect('frontend.user', 'isLoggedIn')` to check FE login status. Instead check the session directly:

```php
$isLoggedIn = !empty($GLOBALS['TSFE']->fe_user->user['uid']);
```

This affects both the middleware (login result check) and `LoginController::showAction` (template variable).

### Element Browser callback (TYPO3 9 pattern)

TYPO3 9's Element Browser uses `window.opener.setFormValueFromBrowseWin(fieldName, value, label)` — NOT the `postMessage` approach used in TYPO3 10+. Custom modules that open an Element Browser popup must define this global function on `window` before opening the popup.

### No Services.yaml / No DI container

TYPO3 9 does not use Symfony DI for extensions. All dependencies are resolved via `GeneralUtility::makeInstance()` with optional constructor parameters defaulting to `null`.

## Known Issues

- **Backend login session persistence**: The auth chain succeeds (user found, BE_USER set, cookies carried with SameSite=Lax), but session persistence after the OAuth redirect may need further investigation if the login still redirects to the login page.
