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
```

No package-level build, lint, or test commands exist yet. Use the parent monorepo's `composer ci:static` and `composer ci:tests` for quality checks.

## Architecture

### Authentication Flow Overview

The extension handles two login contexts (FE and BE) through a shared middleware-based OAuth flow:

1. **User clicks "Sign in"** — authorize URL built by `AzureOAuthService::buildAuthorizeUrl()` with HMAC-signed state parameter
2. **Microsoft authenticates** — redirects back with `code` + `state` query params
3. **`AzureOAuthMiddleware`** intercepts the callback (runs before TYPO3 auth middleware in both stacks):
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
- Template: `Templates/Login/Show.html`, Layout: `Layouts/Default.html`

### Backend Login

- **Login provider** (`AzureLoginProvider`) registered in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders']`
- Shows "Sign in with Microsoft" button on the `/typo3/` login screen as a separate tab
- Template: `Templates/Login/AzureLoginForm.html` (uses `<f:layout name="Login" />` from EXT:backend)
- Callback route: `/typo3/azure-login/callback` → `AzureCallbackController` (public access, no CSRF token required)
- Implements both `render()` (v12) and `modifyView()` (v13+) for cross-version compatibility

### Key Classes

| Class | Purpose |
|-------|---------|
| `Service/AzureOAuthService` | Core OAuth service: builds authorize URLs, exchanges codes for user info via Graph API, creates/validates HMAC-signed state params |
| `Middleware/AzureOAuthMiddleware` | PSR-15 middleware in both FE+BE stacks; intercepts OAuth callbacks, injects auth data into request, preserves session cookies with SameSite=Lax on redirect |
| `Authentication/AzureLoginAuthService` | `AbstractAuthenticationService` subclass; looks up users by email in auth chain, returns 200 for Azure-authenticated requests |
| `EventListener/AzureRequestTokenListener` | Creates valid CSRF `RequestToken` for Azure callbacks (TYPO3 v13+ compatibility) |
| `LoginProvider/AzureLoginProvider` | Backend login provider; renders "Sign in with Microsoft" button on `/typo3/` login screen |
| `Controller/LoginController` | Extbase FE controller; `showAction` renders the frontend login button |
| `Controller/Backend/AzureCallbackController` | Backend route handler; redirects to `/typo3` after successful OAuth callback |

### Configuration

Azure credentials are configured via **Extension Configuration** (`ext_conf_template.txt`), not TypoScript:

| Setting | Description |
|---------|-------------|
| `tenantId` | Microsoft Entra Directory (Tenant) ID |
| `clientId` | Application (Client) ID from Azure App Registration |
| `clientSecret` | Client Secret value |
| `redirectUriFrontend` | OAuth callback URL for frontend login |
| `redirectUriBackend` | OAuth callback URL for backend login (e.g., `https://example.com/typo3/azure-login/callback`) |

TypoScript (`constants.typoscript` / `setup.typoscript`) only configures Fluid template paths for the FE plugin.

### Registration Points

- **`ext_localconf.php`**: FE plugin, auth service (subtypes `getUserFE,authUserFE,getUserBE,authUserBE`, priority 82), icon registration, backend login provider
- **`Configuration/Services.yaml`**: DI config, `AzureLoginProvider` marked `public: true`, event listener registration
- **`Configuration/RequestMiddlewares.php`**: `AzureOAuthMiddleware` in both `frontend` (before FE auth) and `backend` (after routing, before BE auth) stacks
- **`Configuration/Backend/Routes.php`**: `/azure-login/callback` route with `'access' => 'public'`
- **`Configuration/TCA/Overrides/tt_content.php`**: FE content element plugin registration, static TypoScript

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

Translations: English (default), German (`de.locallang.xlf`), French (`fr.locallang.xlf`).

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
