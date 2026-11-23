..  include:: /Includes.rst.txt


========
Updating
========

If you update `pforum` to a newer version, please read this section carefully!

..  _upgrade-8-0-2:

Upgrade to version 8.0.2
========================

*   pforum does not add the `CheckFalUploadValidator` of EXT:checkfaluploads
    to the image uploads anymore. Images of topics and posts are uploaded
    without a rights checkbox, even if EXT:checkfaluploads is installed. If
    you want the rights checkbox, add it to your overrides of the form
    partials. EXT:checkfaluploads checks it, see
    :ref:`frontend-management-checkfaluploads`.

..  _upgrade-8-0-1:

Upgrade to version 8.0.1
========================

*   The templates of pforum do not contain the rights checkbox of
    EXT:checkfaluploads anymore. The partial
    :file:`CheckFalUploads/Rights.html` and the variable
    `isCheckFalUploadsLoaded` have been removed. If EXT:checkfaluploads is
    installed, add the checkbox to your overrides of the form partials, see
    :ref:`frontend-management-checkfaluploads`.
*   Run the upgrade wizard "[pforum] Fill path segments of topics and posts"
    again, if path segments of existing records are empty.

..  _upgrade-8-0-0:

Upgrade to version 8.0.0
========================

*   Run the database analyzer and the upgrade wizards "[pforum] Convert
    plain text descriptions of topics and posts into HTML" and "[pforum]
    Fill path segments of topics and posts". The description of posts
    becomes a `text` column instead of `varchar(255)`, both descriptions
    are rich text fields now. Topics and posts get the column
    `path_segment`, see :ref:`routes`.
*   Links to activate and delete records show a confirmation page, see
    :ref:`frontend-management-confirmation`. The actions `delete`,
    `confirmActivation` and `activate` get the UID of the record and the
    argument `token`. The new action `confirmActivation` has to be allowed,
    if you configure the plugin yourself.
*   The mail link to activate a record contains an own token now.
    Activation links of mails sent by former versions do not work anymore.
    The links to edit and delete a record keep working.
*   Edited records keep their visibility. Former versions made hidden
    records visible after editing them. The preview button is shown for
    new records only.
*   Members of the group `settings.uidOfAdminGroup` activate hidden records
    in the frontend. With `settings.auth = 2` only members of
    `settings.uidOfUserGroup` create records, if the group is set.
*   All actions of the Topic and Post controllers are uncached now.
*   The mail templates moved to :file:`Resources/Private/Templates/Email/`
    and are named `PforumManageTopic`, `PforumManagePost` and
    `PforumNewPost`. They are read from
    `plugin.tx_pforum.settings.email.templateRootPaths` instead of
    `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths']`.
*   The templates use Bootstrap 5 classes. Adapt your own copies of
    :file:`Topic/New.html`, :file:`Topic/Edit.html`, :file:`Post/New.html`,
    :file:`Post/Edit.html`, :file:`Forum/Show.html`, :file:`Topic/Show.html`
    and the partials :file:`FormErrors.html`, :file:`*/FormFields.html` and
    :file:`Component/Pagination.html`. The templates get the variables
    `isCreationAllowed`, `isAdmin`, `richTextEditor`,
    `descriptionPlainText` and `isCheckFalUploadsLoaded`. The partial
    :file:`User/FormFields.html` gets the argument `object` ("topic" or
    "post"). `plugin.tx_pforum._CSS_DEFAULT_STYLE` has been removed.
*   Removed templates and partials: :file:`Post/List.html`,
    :file:`Post/Show.html`, :file:`Topic/List.html`, :file:`User/*.html`,
    :file:`Partials/*/Properties.html`, :file:`Mail/ConfigureTopic.html`
    and :file:`Mail/ConfigurePost.html`.
*   The preview of the plugin in the page module is the template of page
    TSconfig `mod.web_layout.tt_content.preview.pforum_forum`
    (:file:`Resources/Private/Backend/PluginPreview/Forum.html`). The class
    `PluginPreview` has been removed.
*   Removed classes: `IsCreateButtonAllowedViewHelper`,
    `FrontendGroupHelper`, `EmailValidator`, `UsernameValidator`,
    `AnonymousUserRepository`, `PostCheckFileReferenceEvent` and the event
    listeners `RestrictAccessEventListener`,
    `ApplyEmailAsMandatoryIfNeededEventListener` and
    `ApplyUsernameAsMandatoryIfNeededEventListener`. Username and email are
    validated by the new `AnonymousUserValidator`.
*   The site settings `pforum.topic.*`, `pforum.post.*`,
    `pforum.new.uploadFolder` and `pforum.image.*` are used in TypoScript
    now. `hideAtCreation` and `activateByAdmin` are of type `bool`.

Upgrade to version 7.0.0
=========================

*   Post/Topic image uploads now use Extbase's native `#[FileUpload]` attribute instead of a custom
    `PropertyMappingConfiguration` type converter. If you have overridden `Resources/Private/Partials/Post/FormFields.html`
    or `Resources/Private/Partials/Topic/FormFields.html` in your own site package, replace the two indexed
    `<f:form.upload name="{post}[images][0]" />` / `[1]` fields with a single
    `<f:form.upload property="images" multiple="1" />` field and adjust your template accordingly.
*   `Topic::getCrdate()` and `Post::getCrdate()` now return `?\DateTime` instead of `\DateTime`. If your own code
    or overridden templates rely on a non-nullable creation date, add a null check.

Upgrade to version 4.0.0
=========================

We have changed many parts of the Fluid templates. Please check the image and link sections in your overridden
templates and adjust them accordingly.
