:navigation-title: Installation

..  _installation:

============
Installation
============

..  _installation-composer:

Install with Composer
=====================

..  note::
    Composer is the recommended way to install this extension.

Install the extension via Composer:

..  code-block:: bash

    composer req oliverkroener/ok-azure-login

After installation, update the database schema to create the configuration table:

..  code-block:: bash

    vendor/bin/typo3 database:updateschema

See also `Installing extensions <https://docs.typo3.org/permalink/t3start:installing-extensions>`__
in the TYPO3 Getting Started guide.

..  _installation-typoscript:

Include the static TypoScript
=============================

#. In the TYPO3 backend, go to the :guilabel:`Template` module
#. Select the root page of your site
#. Choose :guilabel:`Info/Modify` and click :guilabel:`Edit the whole template record`
#. Switch to the :guilabel:`Includes` tab
#. Add **Azure Login** from the list of available static templates

..  _installation-content-element:

Add the content elements
========================

The extension provides two content elements, available under the **Azure Login**
group in the New Content Element Wizard:

Azure Login
    Renders a "Sign in with Microsoft" button. When a user authenticates via
    Microsoft Entra ID, the extension matches their email to an existing
    ``fe_users`` record and logs them in.

Azure Logout
    Renders a "Sign out" button for logged-in users. Can optionally redirect
    to the Microsoft logout endpoint to sign the user out of Microsoft as well.

To add them:

#. Go to the :guilabel:`Page` module and select the page where the login or logout button should appear
#. Click :guilabel:`Create new content element`
#. Select from the **Azure Login** group: either **Azure Login** or **Azure Logout**
#. Configure the element settings (button theme, redirect URL, etc.) as needed
#. Save and clear caches

..  _installation-backend-login:

Backend login
=============

The extension automatically registers a backend login provider. Once configured
(see :ref:`Configuration <configuration>`), a "Sign in with Microsoft" button
appears as a separate tab on the TYPO3 backend login screen at ``/typo3/``.

No additional setup is needed for backend login beyond configuring the Azure
credentials. The backend redirect URI is automatically derived from the route
configuration and shown as a read-only field with a copy button in the backend
module. Register this URL in your Azure app registration.
