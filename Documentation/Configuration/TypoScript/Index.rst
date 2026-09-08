..  include:: /Includes.rst.txt


..  _typoscript:

==========
TypoScript
==========

`pforum` needs some basic TypoScript configuration. To do so, you have to add an extension template
("+ext template") to either the root page of your website or to the specific page that contains the `pforum`
plugin.

..  rst-class:: bignums

1.  Locate the page

    Decide where you want to insert the TypoScript template. Either the root page or the page with the `pforum`
    plugin works fine.

2.  Create a TypoScript template

    Switch to the Template module and select the page you chose above in the page tree. Choose
    `Click here to create an extension template` from the right-hand frame. In the TYPO3 community, this is also
    known as an "+ext template".

3.  Add the static template

    Choose `Info/Modify` from the upper select box and then click the `Edit the whole template record` button
    below the small table. On the `Includes` tab, locate the `Include static (from extension)` section. Use the
    search field below `Available items` to search for `pforum`. Usually only one record is shown. Select it to
    move that record to the left.

4.  Save

    If you want, give the template a name on the `General` tab, then save and close it.

5.  Open the Constant Editor

    Choose `Constant Editor` from the upper select box.

6.  `pforum` constants

    Choose `PLUGIN.TX_PFORUM` from the category select box to show only `pforum`-related constants.

7.  Configure constants

    Adapt the constants to your needs.

8.  Configure TypoScript

    Since constants only allow you to modify a fixed selection of TypoScript, switch back to `Info/Modify` and
    click `Setup`. Here you can configure all `pforum`-related settings.

View
====

view.templateRootPaths
----------------------

Default: Value from constants `EXT:pforum/Resources/Private/Templates/`

You can override our templates with your own site package extension. We recommend changing this value in the
TypoScript constants.

view.partialRootPaths
-----------------------

Default: Value from constants `EXT:pforum/Resources/Private/Partials/`

You can override our partials with your own site package extension. We recommend changing this value in the
TypoScript constants.

view.layoutsRootPaths
-----------------------

Default: Value from constants `EXT:pforum/Resources/Layouts/Templates/`

You can override our layouts with your own site package extension. We recommend changing this value in the
TypoScript constants.


Persistence
===========

persistence.storagePid
----------------------

Set this value to a Storage Folder (PID) where you have stored the records.

Example: `plugin.tx_pforum.settings.storagePid = 21,45,3234`


Settings
========

settings.auth
-------------

Default: 1 (no authentication)

Example: `plugin.tx_pforum.settings.auth = 2`

Define whether creating new topics and posts requires an authenticated frontend user.

*   Value `1`: No authentication. Everyone can create topics and posts. We recommend this for intranet environments.
*   Value `2`: An authenticated frontend user is required to create topics and posts.

..  note::

    If you choose `1`, a new pforum user record is created for every topic and post.

settings.emailIsMandatory
---------------------------

Default: 0

Example: `plugin.tx_pforum.settings.emailIsMandatory = 1`

If activated, an additional input field is displayed where the user must enter a valid email address.
Useful in case of `auth = 1`. The email address is added to the pforum user record.

settings.usernameIsMandatory
-------------------------------

Default: 0

Example: `plugin.tx_pforum.settings.usernameIsMandatory = 1`

If activated, an additional input field is displayed where the user must enter a username.
Useful in case of `auth = 1`. The username is added to the pforum user record.

settings.useImages
---------------------

Default: 0

Example: `plugin.tx_pforum.settings.useImages = 1`

If activated, two additional upload fields are added to the form for new topics and posts.

settings.uidOfAdminGroup
------------------------

Default: 0

Example: `plugin.tx_pforum.settings.uidOfAdminGroup = 14`

By default, you, as an administrator, have to modify or delete topic and post records in the TYPO3 backend.
With this setting you can define a frontend user group that should act as an administrator to edit
and delete records in the frontend.

settings.uidOfUserGroup
--------------------------

Default: 0

Example: `plugin.tx_pforum.settings.uidOfUserGroup = 26`

If authentication is required (`auth = 2`), you have to define a frontend user group that is allowed to create
new topics and posts.

settings.pidOfDetailPage
---------------------------

Default: 0

Example: `plugin.tx_pforum.settings.pidOfDetailPage = 26`

By default, all detail views are displayed on the same page as the forum record list. For design reasons,
it may make sense to define a dedicated detail view page.

settings.topic.hideAtCreation
--------------------------------

Default: 0

Example: `plugin.tx_pforum.settings.topic.hideAtCreation = 1`

By default, every new topic created in the frontend is directly visible. If you want to prevent this, activate
this option — an administrator will then have to review the topic first.

settings.topic.activateByAdmin
---------------------------------

Default: 0

Example: `plugin.tx_pforum.settings.topic.activateByAdmin = 1`

By default, hidden records can only be activated by a backend editor. If you want your frontend
administrator to be able to enable hidden topics, activate this option here.

settings.post.hideAtCreation
-------------------------------

Default: 0

Example: `plugin.tx_pforum.settings.post.hideAtCreation = 1`

By default, every new post created in the frontend is directly visible. If you want to prevent this, activate
this option — an administrator will then have to review the post first.

settings.post.activateByAdmin
--------------------------------

Default: 0

Example: `plugin.tx_pforum.settings.post.activateByAdmin = 1`

By default, hidden records can only be activated by a backend editor. If you want your frontend
administrator to be able to enable hidden posts, activate this option here.

settings.new.uploadFolder
----------------------------

Default: 1:user_upload/tx_pforum/

Example: `plugin.tx_pforum.settings.new.uploadFolder = 2:dropbox/pforum/`

Only valid if you have activated `useImages`. Define the default storage location for uploaded images in the
frontend.

settings.image.*
----------------

Default:

..  code-block:: typoscript

    settings.image {
      width = 120c
      height = 90c
      minWidth = 120
      maxWidth = 120
      minHeight = 90
      maxHeight = 90
    }

With these values you can manipulate the topic and post image size.

settings.pageBrowser.itemsPerPage
------------------------------------

Default: 15

Example: `plugin.tx_pforum.settings.pageBrowser.itemsPerPage = 10`

If there are a lot of records, the page browser helps you navigate through all these records.
Define the maximum number of records to display per page.
