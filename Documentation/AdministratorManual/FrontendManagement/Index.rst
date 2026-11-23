..  include:: /Includes.rst.txt

..  _frontend-management:

==================================
Topics and posts in the frontend
==================================

Topics and posts are created and edited in the frontend with the Extbase
forms of the Forum plugin. New records can be previewed before they are
published: the button "Preview" stores the record hidden and shows it
above the edit form. "Publish" stores it with the visibility of
`settings.topic.hideAtCreation` or `settings.post.hideAtCreation`.

..  contents::
    :caption: Table of contents
    :local:

..  _frontend-management-access:

Who may create, edit, delete and activate records
=================================================

..  t3-field-list-table::
    :header-rows: 1

    -   :Action: Action
        :Anonymous: `settings.auth = 1`
        :FrontendUser: `settings.auth = 2`

    -   :Action: Create
        :Anonymous: Everyone
        :FrontendUser: Logged-in frontend users. If `settings.uidOfUserGroup`
                       is set, they have to be member of this group.

    -   :Action: Edit and delete
        :Anonymous: Everyone with the link of the mail, which is sent to the
                    pforum user after publishing (`settings.emailIsMandatory`)
        :FrontendUser: The frontend user, who created the record

    -   :Action: Activate
        :Anonymous: The pforum user with the link of the mail, if
                    `hideAtCreation` is set and `activateByAdmin` is not set.
                    Members of the group `settings.uidOfAdminGroup`.
        :FrontendUser: Members of the group `settings.uidOfAdminGroup`

The links of the mail contain a signed token. The token to edit and delete
a record can not be used to activate it and vice versa. Hidden records can
be activated in the backend module of pforum, too. Edited records keep
their visibility.

With `settings.auth = 1` the form contains name, username and email of
the pforum user. A given email address has to be valid. Username and
email address are mandatory, if `settings.usernameIsMandatory` or
`settings.emailIsMandatory` is set.

..  _frontend-management-confirmation:

Confirmation of activation and deletion
=======================================

Mail security scanners and link previews open the links of a mail
automatically. So the links to activate and delete a record show a
confirmation page with a summary of the record. Only its form activates
or deletes the record with a POST request. The result is shown as flash
message on the page of the forum or topic.

Members of the admin group see hidden topics and posts with the badge
"Hidden" and a button, which leads to the same confirmation page.

..  _frontend-management-mails:

Mails
=====

`PforumManageTopic`, `PforumManagePost`
    Sent to the pforum user after publishing a new record
    (`settings.auth = 1` and `settings.emailIsMandatory = 1`). Contains
    the links to edit and delete the record and, if the author activates
    the record, the link to activate it.

`PforumNewPost`
    Sent to the author of a topic for each new visible post.

The templates are read from the template root paths of TypoScript
`plugin.tx_pforum.settings.email.templateRootPaths`, which are added to
the mails of pforum only. Add your own path with a higher key to override
a template. Sender address and name are taken from the extension
settings.

..  _frontend-management-checkfaluploads:

Rights checkbox of EXT:checkfaluploads
======================================

If EXT:checkfaluploads (6.0.0 or higher) is installed, pforum adds its
`CheckFalUploadValidator` to the image uploads. The templates of pforum
do not contain the rights checkbox, so add the checkbox
`tx_pforum_forum[topic|post][images][rights]` to your overrides of the
form partials, as described in the :ref:`documentation of
EXT:checkfaluploads <checkfaluploads:developer-manual>`.
