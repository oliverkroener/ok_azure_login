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

Add the content element
=======================

#. Go to the :guilabel:`Page` module and select the page where the login button should appear
#. Add a new content element of type **Azure Login**
#. In the element settings, choose the **Login Type**:

   - **Frontend Login** — authenticates against ``fe_users`` records
   - **Backend Login** — authenticates against ``be_users`` records and redirects to ``/typo3``

#. Save and clear caches
