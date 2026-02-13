# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`ok_azure_login` (composer: `oliverkroener/ok-azure-login`) is a TYPO3 extension that enables frontend and backend user login via Microsoft Entra ID (Azure AD) using the OAuth 2.0 authorization code flow and Microsoft Graph API.

**Extension key**: `ok_azure_login`
**Namespace**: `OliverKroener\OkAzureLogin\`
**Version**: 2.0.0
**TYPO3 compatibility**: 10.4
**PHP**: ^7.4

## Development Context

This package lives within a parent monorepo. It is a **Git submodule** with its own repository — commits must be made inside this directory first, then the parent repo reference updated.

```bash
# From the DDEV project (backend/ as composer root):
ddev exec composer typo3:flush    # Clear caches after any config/class changes
ddev typo3 database:updateschema  # Apply schema changes after modifying ext_tables.sql
```

No package-level build, lint, or test commands exist yet. Use the parent monorepo's `composer ci:static` and `composer ci:tests` for quality checks.

### Documentation Generation

```bash
make docs        # Generate docs via Docker (pulls latest image)
make docs-fast   # Generate docs (uses cached Docker image)
make docs-watch  # Watch Documentation/ and auto-regenerate (requires inotify-tools)
```

## Architecture

### Authentication Flow Overview

The extension handles two login contexts (FE and BE) through a shared middleware-based OAuth flow:

1. **User clicks "Sign in"** — authorize URL built by `AzureOAuthService::buildAuthorizeUrl()` with HMAC-signed state parameter (includes `siteRootPageId` and `configUid` for per-site/per-config resolution)
2. **Microsoft authenticates** — redirects back with `code` + `state` query params
3. **`AzureOAuthMiddleware`** intercepts the callback (runs before TYPO3 auth middleware in both stacks):
   - Resolves site context from request attribute (FE) or state payload (BE)
   - Validates HMAC-signed state (10-minute TTL)
   - Exchanges code for access token via Guzzle, then fetches user info via Graph SDK v1 `me()` endpoint
   - Stores user data in `azure_login_user` request attribute
   - Updates `$GLOBALS['TYPO3_REQUEST']` so the auth chain can read the attribute
   - Injects login trigger fields into request body (`login_status=login` for BE, `logintype=login` for FE)
4. **`AzureLoginAuthService`** (in TYPO3's auth chain, priority 82) looks up user by email in `fe_users`/`be_users`, returns 200 (authenticated)
5. **Middleware preserves Set-Cookie headers** from the auth chain response and copies them to the redirect response, downgrading `SameSite=Strict` to `SameSite=Lax` for the callback only
6. **Middleware redirects** to the return URL from state (HTTP 303) with `azure_login_success` or `azure_login_error` query params

### Frontend Login

- Extbase content element plugin (`okazurelogin_login`) with `LoginController::showAction`
- Renders a "Login with Azure" button linking to the authorize URL
- Shows configuration error message when Azure credentials are not set
- FlexForm option: button theme (dark/light)
- Template: `Templates/Login/Show.html`, Layout: `Layouts/Default.html`

### Frontend Logout

- Extbase content element plugin (`okazurelogin_logout`) with `LogoutController`
- FlexForm options: button theme, Microsoft sign-out redirect, custom redirect URL
- Optional redirect to Microsoft logout endpoint (`login.microsoftonline.com/.../logout`)

### Backend Login

- **Login provider** (`AzureLoginProvider`) **replaces** the default `UsernamePasswordLoginProvider` (unsets provider ID `1433416747` in `ext_localconf.php`)
- Fetches all enabled backend configs via `findAllConfiguredForBackend()` and renders **multiple** "Sign in with Microsoft" buttons — one per enabled backend config with its own label
- Template: `Templates/Login/AzureLoginForm.html` (uses `<f:layout name="Login" />` from EXT:backend)
- Callback route: `/typo3/azure-login/callback` → `AzureCallbackController` (public access, no CSRF token required)

### Backend Configuration Module

- TYPO3 backend module under **Web** group with page tree navigation
- Module key: `web_okazurelogin`, path: `/module/web/azure-login`
- Admin-only access, icon: `ext-ok-azure-login-microsoft`
- `ConfigurationController` serves as a router via `handleRequest()`, delegating to:
  - **Frontend config** (per-site): `editAction` (form) and `saveAction` (POST, PRG pattern) — resolved by site root page ID from page tree selection
  - **Backend config list**: `backendListAction` — paginated (20 per page), with delete confirmation modals
  - **Backend config edit**: `backendEditAction` / `backendSaveAction` / `backendDeleteAction`
- Clone encrypted secrets between configs without decryption (encrypted blob copy)
- Client secret encrypted with Sodium before storage, never sent to browser
- Warns when TYPO3 encryption key is missing (red danger callout)
- Form dirty-check via RequireJS module blocks navigation with unsaved changes

### Key Classes

| Class | Purpose |
|-------|---------|
| `Service/AzureOAuthService` | Core OAuth service: builds authorize URLs, exchanges codes via Guzzle + Graph SDK v1 for user info, creates/validates HMAC-signed state params, per-site config resolution |
| `Service/EncryptionService` | Sodium-based authenticated encryption for client secrets using `sodium_crypto_secretbox`; key derived from TYPO3 encryption key |
| `Domain/Repository/AzureConfigurationRepository` | CRUD for `tx_okazurelogin_configuration` table with transparent encrypt/decrypt of client secret; supports both site-based (FE) and uid-based (BE) lookups |
| `Middleware/AzureOAuthMiddleware` | PSR-15 middleware in both FE+BE stacks; resolves site context, intercepts OAuth callbacks, injects auth data into request, preserves session cookies with SameSite=Lax on redirect |
| `Authentication/AzureLoginAuthService` | `AbstractAuthenticationService` subclass; looks up users by email in auth chain, returns 200 for Azure-authenticated requests |
| `LoginProvider/AzureLoginProvider` | Backend login provider; renders multiple "Sign in with Microsoft" buttons (one per enabled backend config) |
| `Controller/LoginController` | Extbase FE controller; `showAction` renders the frontend login button with configuration error handling |
| `Controller/LogoutController` | Extbase FE controller; handles logout with optional Microsoft sign-out redirect |
| `Controller/Backend/ConfigurationController` | Backend module controller; manages per-site FE configs and global BE configs with encryption key validation |
| `Controller/Backend/AzureCallbackController` | Backend route handler; redirects to `/typo3` after successful OAuth callback |

### Configuration

Azure credentials are stored **per site** (frontend) or **per config record** (backend) in the database table `tx_okazurelogin_configuration`, with **Extension Configuration** (`ext_conf_template.txt`) as a fallback for backward compatibility.

#### Config Resolution Order

`AzureOAuthService::getConfiguration()`:

**Backend** (`loginType === 'backend'`):
1. By `configUid` (from state parameter) via `findByUid()`
2. By `siteRootPageId` via `findBySiteRootPageId()`
3. By `siteRootPageId = 0` (global backend config)
4. Fallback: `ExtensionConfiguration::get('ok_azure_login')`

**Frontend**:
1. By `siteRootPageId` via `findBySiteRootPageId()`
2. Fallback: `ExtensionConfiguration::get('ok_azure_login')`

#### Encryption

Client secrets are encrypted at rest using PHP Sodium (`sodium_crypto_secretbox`):
- Key derived from `$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']` via `sodium_crypto_generichash`
- Random nonce prepended to ciphertext, base64-encoded for storage
- The backend module warns if the encryption key is not set

TypoScript (`constants.typoscript` / `setup.typoscript`) only configures Fluid template paths for the FE plugin.

### Registration Points

- **`ext_localconf.php`**: FE plugins (login + logout), auth service (subtypes `getUserFE,authUserFE,getUserBE,authUserBE`, priority 82), icon registration, backend login provider (replaces default), cHash exclusions for `code`/`state`/`azure_login_success`/`azure_login_error`, page TSconfig import
- **`ext_tables.php`**: Backend configuration module registration via `addModule()` under Web group with page tree navigation
- **`Configuration/Backend/Routes.php`**: `/azure-login/callback` route with `'access' => 'public'`, plus module sub-routes for save/backendList/backendEdit/backendSave/backendDelete
- **`Configuration/Services.yaml`**: DI config; `AzureOAuthService`, `AzureConfigurationRepository`, and `AzureLoginProvider` marked `public: true`
- **`Configuration/RequestMiddlewares.php`**: `AzureOAuthMiddleware` in both `frontend` (before FE auth) and `backend` (after routing, before BE auth) stacks
- **`Configuration/TCA/Overrides/tt_content.php`**: FE content element plugin registration, static TypoScript
- **`Configuration/page.tsconfig`**: New Content Element Wizard entries under custom "Azure Login" group

### Database Schema

```sql
CREATE TABLE tx_okazurelogin_configuration (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    site_root_page_id int(11) unsigned DEFAULT '0' NOT NULL,
    enabled tinyint(1) unsigned DEFAULT '1' NOT NULL,
    show_label tinyint(1) unsigned DEFAULT '1' NOT NULL,
    backend_login_label varchar(255) DEFAULT '' NOT NULL,
    tenant_id varchar(255) DEFAULT '' NOT NULL,
    client_id varchar(255) DEFAULT '' NOT NULL,
    client_secret_encrypted text,
    redirect_uri_frontend varchar(1024) DEFAULT '' NOT NULL,
    redirect_uri_backend varchar(1024) DEFAULT '' NOT NULL,
    PRIMARY KEY (uid),
    KEY site_root (site_root_page_id)
);
```

Backend configs use `site_root_page_id = 0`; frontend configs use the actual site root page ID.

### Logging

Both `AzureOAuthMiddleware` and `AzureLoginAuthService` implement `LoggerAwareInterface` and use PSR-3 `$this->logger->debug()` for diagnostic logging. Configure TYPO3's log writer for the `OliverKroener.OkAzureLogin` namespace:

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

- `microsoft/microsoft-graph` ^1.69 — Graph SDK v1 for user profile retrieval (`Graph` class, `Model\User`)
- Token exchange done directly via `GuzzleHttp\Client` POST to Microsoft's token endpoint

### Languages

Translations: English (default), German (`de.*.xlf`), French (`fr.*.xlf`).

Language file groups:
- `locallang.xlf` — Frontend plugin labels and messages
- `locallang_db.xlf` — TCA, FlexForm, and content element wizard labels
- `locallang_be_module.xlf` — Backend configuration module labels
- `locallang_csh_tx_okazurelogin.xlf` — Context-sensitive help

### OAuth Scopes

The extension requests `openid profile User.Read` (delegated permissions, user context).

### JavaScript Modules (RequireJS)

All in `Resources/Public/JavaScript/Backend/`, loaded via `loadRequireJsModule('TYPO3/CMS/OkAzureLogin/Backend/{Name}')`:

- `FormDirtyCheck.js` — Tracks unsaved form changes, blocks navigation with TYPO3 modal; integrates with `ConsumerScope` for page tree navigation and tab switching
- `DeleteConfirm.js` — Delete confirmation modal for backend config list
- `CloneConfig.js` — Clone backend config to frontend form (copies tenant, client, encrypted secret, editable redirect URI)
- `CloneBackendConfig.js` — Clone between backend configs

## TYPO3 10.4 Compatibility Notes

These patterns are specific to the TYPO3 10.4 APIs used by this extension:

### Entry point guard

TYPO3 10 uses `defined('TYPO3_MODE') or die();` (v11+ uses `defined('TYPO3')` or `defined('TYPO3_MODE')`).

### Backend module registration

TYPO3 10 uses `ExtensionManagementUtility::addModule()` in `ext_tables.php` with a `routeTarget` pointing to a controller method. Module sub-routes are registered in `Configuration/Backend/Routes.php`. The v12+ `Configuration/Backend/Modules.php` format is not supported.

### ModuleTemplate rendering pattern

TYPO3 10 uses `ModuleTemplate` + `StandaloneView` (not `ModuleTemplateFactory` from v12):

```php
$moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
$view = GeneralUtility::makeInstance(StandaloneView::class);
$view->setTemplate('...');
$moduleTemplate->setContent($view->render());
return new HtmlResponse($moduleTemplate->renderContent());
```

### JavaScript modules

TYPO3 10 uses RequireJS (not ES modules). JS files follow CamelCase naming (`Backend/DeleteConfirm.js`) and are loaded via `loadRequireJsModule('TYPO3/CMS/OkAzureLogin/Backend/DeleteConfirm')`.

### Flash message severity

Use integer constants from `AbstractMessage` (e.g., `FlashMessage::OK`) instead of the `ContextualFeedbackSeverity` enum (v12+).

### PHP property declarations

PHP 7.4 compatible: class properties use `/** @var Type */` docblocks instead of typed properties. Constructor promotion is not used.

### Graph SDK v1 pattern

This branch uses `microsoft/microsoft-graph` v1 (not v2/Kiota):

```php
$graph = new \Microsoft\Graph\Graph();
$graph->setAccessToken($accessToken);
$me = $graph->createRequest('GET', '/me')
    ->setReturnType(\Microsoft\Graph\Model\User::class)
    ->execute();
```

Token exchange uses `GuzzleHttp\Client` directly, not Kiota's `AuthorizationCodeContext`.

### Backend route public access

The `/azure-login/callback` route requires `'access' => 'public'` in `Configuration/Backend/Routes.php`. Without this, TYPO3 requires a CSRF token that Microsoft's redirect cannot provide.

### `$GLOBALS['TYPO3_REQUEST']` must be updated

PSR-7 requests are immutable. When the middleware adds attributes (`azure_login_user`) or modifies the parsed body, the global `$GLOBALS['TYPO3_REQUEST']` must be explicitly updated so that the auth service (which reads from the global) sees the changes:

```php
$request = $request->withAttribute('azure_login_user', $userInfo);
$GLOBALS['TYPO3_REQUEST'] = $request;
```

### SameSite cookie handling after cross-site OAuth redirect

TYPO3 defaults `BE.cookieSameSite` to `strict`. After a cross-site redirect from Microsoft, browsers will NOT send `SameSite=Strict` cookies on the subsequent navigation. The middleware must:

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
