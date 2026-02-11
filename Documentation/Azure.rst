:navigation-title: Azure Entra Setup

..  _azure:

=======================================================
Configuration of Microsoft Entra ID (formerly Azure AD)
=======================================================

This guide walks you through registering an application in Microsoft Entra ID
so the extension can authenticate users via the OAuth 2.0 authorization code
flow.

..  attention::
    The **Client ID** can be found on the overview page in Azure and **should not be confused with the Client Secret ID**. For the secret configuration, only the Secret **Value** itself is required, not the Secret ID.

..  note::
    This guide assumes you have **administrative access to Microsoft Entra ID** and the necessary permissions to register applications.

..  rst-class:: bignums-xxl

1.  Register an application in Microsoft Entra ID

    Go to `https://portal.azure.com <https://portal.azure.com>`__ and navigate
    to :guilabel:`Microsoft Entra ID` > :guilabel:`App registrations`.

2.  Configure the application

    - **Name**: Choose a descriptive name (e.g. "TYPO3 Azure Login").
    - **Supported account types**: Select "Accounts in this organizational directory only (Single tenant)".

3.  Add redirect URIs

    Under :guilabel:`Redirect URI`, select **Web** and add the callback URLs
    that match your extension configuration:

    - Frontend: ``https://your-domain.com/your-login-page`` (the page containing the Azure Login content element)
    - Backend: ``https://your-domain.com/typo3/azure-login/callback``

    ..  tip::
        You can add multiple redirect URIs later in the :guilabel:`Authentication`
        section of the app registration. The frontend redirect URI should point
        to the page where Microsoft redirects the user after authentication. The
        backend redirect URI uses the fixed route ``/typo3/azure-login/callback``.

    Click :guilabel:`Register`.

4.  Collect Tenant ID and Client ID

    On the :guilabel:`Overview` page, note down:

    - **Directory (tenant) ID** — this is the ``tenantId``
    - **Application (client) ID** — this is the ``clientId``

    ..  attention::
        The **Client ID** is the Application ID on the overview page. Do **not**
        confuse it with the **Secret ID** shown in the next step.

5.  Create a client secret

    - Navigate to :guilabel:`Certificates & secrets` > :guilabel:`Client secrets`
    - Click :guilabel:`New client secret`
    - Enter a description and choose an expiration period
    - Click :guilabel:`Add`

    ..  attention::
        - Copy the **Secret Value** immediately after creation — it will not be shown again.
        - Manage the expiration and renew the secret before it expires to maintain uninterrupted service.
        - The **Secret Value** is sensitive information. Store it securely and do not expose it in public repositories or logs.

6.  Configure API permissions

    The extension uses the **authorization code flow** with **delegated permissions** (not application permissions). It requests the following scopes:

    - ``openid`` — sign-in
    - ``profile`` — basic user profile
    - ``User.Read`` — read the signed-in user's profile (email, display name)

    To configure:

    - Navigate to :guilabel:`API permissions`
    - Click :guilabel:`Add a permission` > :guilabel:`Microsoft Graph` > :guilabel:`Delegated permissions`
    - Select: ``openid``, ``profile``, ``User.Read``
    - Click :guilabel:`Add permissions`
    - Click :guilabel:`Grant admin consent for [Your Organization]`

    ..  note::
        Unlike server-to-server integrations, this extension authenticates on
        behalf of the user. Only **delegated** permissions are needed —
        **application** permissions are not required.
