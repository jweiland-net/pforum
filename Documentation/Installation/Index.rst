..  include:: /Includes.rst.txt


..  _installation:

============
Installation
============

Composer
========

If your TYPO3 installation runs in Composer mode, please execute the following command:

..  code-block:: bash

    composer req jweiland/pforum
    vendor/bin/typo3 extension:setup --extension=pforum

If you work with DDEV please execute this command:

..  code-block:: bash

    ddev composer req jweiland/pforum
    ddev exec vendor/bin/typo3 extension:setup --extension=pforum

Extension Manager
=================

On TYPO3 installations that don't run in Composer mode, you can still install `pforum` via the Extension Manager:

..  rst-class:: bignums

1.  Log in

    Log in to the backend of your TYPO3 installation as an administrator or system maintainer.

2.  Open the Extension Manager

    Click on `Extensions` in the left-hand menu to open the Extension Manager.

3.  Update extensions

    Choose `Get Extensions` from the upper select box and click the `Update now` button in the upper right.

4.  Install `pforum`

    Use the search field to find `pforum`. Select the `pforum` entry from the search results and click the cloud
    icon to install `pforum`.

Next step
=========

:ref:`Configure pforum <configuration>`.
