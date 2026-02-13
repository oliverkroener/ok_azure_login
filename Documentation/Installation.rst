:navigation-title: Installation

..  _installation:

============
Installation
============

..  _installation-composer:

Install with Composer
=====================

..  note::
    This is the recommended way to install this extension.

Install the extension via Composer:

..  code-block:: bash

    composer req oliverkroener/ok-azure-login

After installation, update the database schema to create the configuration table:

..  code-block:: bash

    vendor/bin/typo3 database:updateschema

See also `Installing extensions, TYPO3 Getting started <https://docs.typo3.org/permalink/t3start:installing-extensions>`_.

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
#. Configure the element settings (button theme, redirect URL, etc.)
#. Save and clear caches

..  _installation-backend-login:

Backend login
=============

The extension automatically registers a backend login provider. Once configured
(see :ref:`Configuration <configuration>`), one or more "Sign in with Microsoft"
buttons appear as a separate tab on the TYPO3 backend login screen at ``/typo3/``.

To set up backend login:

#. Go to :guilabel:`Web` > :guilabel:`Azure Login` and switch to the **Backend** tab
#. Click :guilabel:`Add Backend Login Configuration`
#. Fill in the Azure credentials (Tenant ID, Client ID, Client Secret, Label)
#. Copy the auto-generated **Redirect URI** and register it in your Azure app
#. Save the configuration

The backend redirect URI (``/typo3/azure-login/callback``) is automatically
derived from the TYPO3 route configuration. You can create multiple backend
login configurations for different Azure tenants.
