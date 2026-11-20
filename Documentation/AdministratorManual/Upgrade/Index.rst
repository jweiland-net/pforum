..  include:: /Includes.rst.txt


========
Updating
========

If you update `pforum` to a newer version, please read this section carefully!

..  _upgrade-8-0-0:

Upgrade to version 8.0.0
========================

Topics and posts are created and edited with forms based on EXT:form, see
:ref:`forms`. EXT:form is required now and the site set `jweiland/pforum`
depends on the site set `typo3/form`.

*   Run the upgrade wizard "[pforum] Convert plain text descriptions of
    topics and posts into HTML". The description of topics and posts is a
    rich text field now. The description of posts becomes a `text` column
    instead of a `varchar(255)` column, so run the database analyzer, too.
*   The actions `create` and `update` of the controllers `Topic` and `Post`
    have been removed. `new` and `edit` render the forms of EXT:form. The
    preview of new records has been removed without replacement.
*   The actions `edit`, `delete`, `confirmActivation` and `activate` get the
    UID of the record and the argument `token`. Links to `activate` and
    `delete` show a confirmation page. The record is changed with POST
    requests only. The new action `confirmActivation` has to be allowed in
    your own plugin configuration, if you have one.
*   The links of the mail to activate a record contain an own token now.
    Activation links of mails sent by former versions do not work anymore.
    The links to edit and delete a record keep working.
*   Edited records keep their visibility. Former versions made hidden
    records visible after editing them.
*   Members of the group `settings.uidOfAdminGroup` see hidden records and
    activate them in the frontend without a token.
*   Removed templates and partials: :file:`Post/List.html`,
    :file:`Post/Show.html`, :file:`Topic/List.html`, :file:`User/*`,
    :file:`Partials/FormErrors.html`, :file:`Partials/*/FormFields.html`,
    :file:`Partials/*/Properties.html` and the mail templates
    :file:`Mail/ConfigureTopic.html` and :file:`Mail/ConfigurePost.html`.
    Adapt your own copies of :file:`Topic/New.html`, :file:`Topic/Edit.html`,
    :file:`Post/New.html`, :file:`Post/Edit.html`, :file:`Forum/Show.html`
    and :file:`Topic/Show.html`. The templates get the variables
    `isCreationAllowed` and `isAdmin` instead of the ViewHelper
    `pf:isCreateButtonAllowed`.
*   The mail templates of pforum are not added to
    `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths']` anymore. The
    template root paths of the mail about a new post are set in
    `plugin.tx_pforum.settings.email.templateRootPaths`.
*   The template of the plugin preview in the page module is read from page
    TSconfig `mod.web_layout.tt_content.preview.pforum_forum`, which is set
    in :file:`Configuration/page.tsconfig` of pforum.
*   Removed classes: `IsCreateButtonAllowedViewHelper`,
    `FrontendGroupHelper`, `EmailValidator`, `UsernameValidator`,
    `PreProcessControllerActionEvent`, `PostCheckFileReferenceEvent` and the
    event listeners `RestrictAccessEventListener`,
    `ApplyEmailAsMandatoryIfNeededEventListener`,
    `ApplyUsernameAsMandatoryIfNeededEventListener`,
    `ApplyUploadFolderEventListener` and `CheckFalUploadsEventListener`.
*   The rights checkbox of EXT:checkfaluploads is not added automatically
    anymore, see :ref:`forms-checkfaluploads`.
*   `settings.topic.*`, `settings.post.*`, `settings.new.uploadFolder` and
    `settings.image.*` are set by the site settings of the same name now.
    The site settings `pforum.topic.hideAtCreation`,
    `pforum.topic.activateByAdmin`, `pforum.post.hideAtCreation` and
    `pforum.post.activateByAdmin` are of type `bool` now.
*   The frontend templates use Bootstrap 5 classes. The pagination renders
    `ul.pagination` with `page-item` and `page-link` instead of
    `f3-widget-paginator`. `plugin.tx_pforum._CSS_DEFAULT_STYLE` has been
    removed.

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
