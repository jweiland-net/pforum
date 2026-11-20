..  include:: /Includes.rst.txt

..  _forms:

=====================
Topic and post forms
=====================

..  versionadded:: 8.0.0

    Topics and posts are created and edited with forms based on EXT:form.

Topics and posts are created and edited in the frontend with two forms
based on the system extension EXT:form:

*   :file:`EXT:pforum/Configuration/Form/Topic.form.yaml`
*   :file:`EXT:pforum/Configuration/Form/Post.form.yaml`

The actions `new` and `edit` of the Forum plugin render these forms with
the :php:`\JWeiland\Pforum\Domain\Factory\ArrayFormFactory` of pforum. The
factory adapts the form definition to the plugin settings before the form
is built and fills the form with the values of the record to edit.

..  contents::
    :caption: Table of contents
    :local:

..  _forms-requirements:

Requirements
============

*   The system extension EXT:form (:composer:`typo3/cms-form`).
*   The site set `jweiland/pforum`. It depends on the site set
    `typo3/form`, which provides the frontend configuration of EXT:form.

..  _forms-elements:

Elements of the forms
=====================

Both forms contain the same elements. The ArrayFormFactory removes or
changes some of them according to the plugin settings:

`name`, `username`, `email`
    Name, username and email of the pforum user (anonymous user). These
    elements are rendered with `settings.auth = 1` only. `username` and
    `email` get the validator `NotEmpty`, if
    `settings.usernameIsMandatory` or `settings.emailIsMandatory` is set.

`title`
    Mandatory for topics, optional for posts.

`description`
    Mandatory. Its plain text is stored as HTML like the backend RTE stores
    it. With the rich text editor, see :ref:`frontend-rich-text-editor`,
    the formatted text is stored.

`image-1`, `image-2`
    Uploads of type `ImageUpload` (JPG or PNG, max. 4 MB). They are
    rendered with `settings.useImages = 1` only. A new file replaces the
    file at the same position.

..  _forms-access:

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
                    pforum user after creation (`settings.emailIsMandatory`)
        :FrontendUser: The frontend user, who created the record

    -   :Action: Activate
        :Anonymous: The pforum user with the link of the mail, if
                    `hideAtCreation` is set and `activateByAdmin` is not set.
                    Members of the group `settings.uidOfAdminGroup`.
        :FrontendUser: Members of the group `settings.uidOfAdminGroup`

The links of the mail contain a signed token. The token to edit and delete
a record can not be used to activate it and vice versa. The access is
checked by the controller actions and again by the save finishers, as a
form can be submitted without its action.

Hidden records can be activated in the backend module of pforum, too.

..  _forms-confirmation:

Confirmation of activation and deletion
---------------------------------------

Mail security scanners and link previews open the links of a mail
automatically. So the links to activate and delete a record show a
confirmation page with a summary of the record. Only its form activates
or deletes the record with a POST request. The result is shown as flash
message on the page of the forum or topic.

Members of the admin group see hidden topics and posts with the badge
"Hidden" and a button, which leads to the same confirmation page.

..  _forms-finishers:

Finishers and mails
===================

The forms use these finishers:

`PforumSaveTopic`, `PforumSavePost`
    Store the topic in the folder of its forum or the post in the folder of
    its topic. With `settings.auth = 1` a pforum user record is created.
    The author of a topic gets a mail about each new visible post (mail
    template `PforumNewPost`). Its template root paths are set in TypoScript
    `plugin.tx_pforum.settings.email.templateRootPaths` and are used for
    this mail only.

`EmailToSender`
    Sends the links to activate, edit and delete a new record to the pforum
    user. It is removed for edited records and if `settings.auth` is not
    `1` or `settings.emailIsMandatory` is not set. Sender address and name
    are taken from the extension settings. The templates `TopicModeration`
    and `PostModeration` are set in the form definitions, so they are not
    used for other forms of the installation.

`FlashMessage`
    The message depends on `hideAtCreation` and `activateByAdmin`.

`Redirect`
    Shows the forum of the topic or the topic of the post.

The finishers `PforumSaveTopic` and `PforumSavePost` are registered in
:file:`EXT:pforum/Configuration/Form/OverrideFormSettings.yaml`, which the
site set adds to `plugin.tx_form.settings.yamlConfigurations`. This file
registers nothing else, so other forms of the installation are not
changed.

..  _forms-checkfaluploads:

Rights checkbox of EXT:checkfaluploads
--------------------------------------

The image uploads are plain EXT:form elements of type `ImageUpload`. To add
the rights checkbox of EXT:checkfaluploads (6.0.0 or higher), add a
checkbox with :yaml:`checkboxType: uploadRights` and
:yaml:`referenceUploadIdentifier` to your copy of the form definition, as
described in the :ref:`documentation of EXT:checkfaluploads
<checkfaluploads:developer-manual>`.

..  _forms-configuration:

Configuration
=============

Upload folder
-------------

The images are stored in the folder of the site setting
`pforum.new.uploadFolder` (default: `1:/user_upload/tx_pforum/`).
TypoScript copies it into the upload elements of both forms with
`settings.overrideForm`:

..  code-block:: typoscript
    :caption: EXT:pforum/Configuration/Sets/PForum/setup.typoscript (excerpt)

    plugin.tx_pforum.settings.overrideForm {
      topic {
        renderables.0.renderables.5.properties.saveToFileMount < plugin.tx_pforum.settings.new.uploadFolder
        renderables.0.renderables.6.properties.saveToFileMount < plugin.tx_pforum.settings.new.uploadFolder
      }
    }

`settings.overrideForm.topic` and `settings.overrideForm.post` override
any value of the form definitions. Please keep in mind that the elements
are addressed by their position.

Own copy of a form definition
-----------------------------

To change the forms beyond `settings.overrideForm`, copy the form
definition into your site package:

#.  Register the folder of your form definitions in
    `persistenceManager.allowedExtensionPaths` of your own form setup.
#.  Override the templates :file:`Topic/New.html` and :file:`Topic/Edit.html`
    (or :file:`Post/New.html` and :file:`Post/Edit.html`) and set the
    `persistenceIdentifier` of the ViewHelper `formvh:render` to your
    copy. Keep the `factoryClass` of pforum.
#.  Keep the rendering option `recordType` and the finisher identifiers.
    Without `recordType` the ArrayFormFactory of pforum does not configure
    the form.

..  _forms-markup:

Markup of the forms
===================

The forms use the template variant `version2` of EXT:form, which renders
the markup of Bootstrap 5. The form definitions add the partial root path
:file:`EXT:pforum/Resources/Private/FormFramework/Partials/` and the
translation file
:file:`EXT:pforum/Resources/Private/Language/locallang_form.xlf` to these
forms only. The partial :file:`Field/Field.html` renders the element
description as small text below the field. The partials
:file:`RichTextEditor/Textarea.html` and
:file:`RichTextEditor/LoadAssets.html` render rich text elements, see
:ref:`frontend-rich-text-editor-assets`.

The ArrayFormFactory marks the forms with the rendering option
`formOwner: pforum`. The hooks of pforum for EXT:form act on forms with
this mark only.
