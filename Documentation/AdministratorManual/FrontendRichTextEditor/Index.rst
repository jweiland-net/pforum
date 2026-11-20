..  include:: /Includes.rst.txt

..  _frontend-rich-text-editor:

======================================
Rich text editor in the frontend forms
======================================

..  versionadded:: 8.0.0

    The description of topics and posts can be edited with a CKEditor 5.

The element `description` of the topic and post forms (see :ref:`forms`)
can be edited with a small CKEditor 5. The feature is disabled by default.
Without it, the description is a plain textarea. Its plain text is stored
as HTML like the backend RTE stores it: a line break becomes
:html:`<br />`, an empty line starts a new paragraph.

..  _frontend-rich-text-editor-requirements:

Requirements
============

*   The system extension EXT:rte_ckeditor
    (:composer:`typo3/cms-rte-ckeditor`). The editor is not activated, if
    it is not installed.

No build step is needed. The CKEditor 5 modules of EXT:rte_ckeditor are
used.

..  _frontend-rich-text-editor-settings:

Site settings
=============

`pforum.form.richText.enable`
    Enables the editor. Default: `false`. TypoScript:
    `plugin.tx_pforum.settings.new.richText.enable`.

`pforum.form.richText.elements`
    Comma separated identifiers of form elements, which get the editor.
    Default: `description`. TypoScript:
    `plugin.tx_pforum.settings.new.richText.elements`. Elements with
    :yaml:`properties.dbMapping.dataType: richText` in the form definition
    get the editor, too.

`pforum.form.richText.translationsPath`
    Folder with the translations of the user interface of CKEditor 5, e.g.
    :file:`de.js`. Default:
    `EXT:rte_ckeditor/Resources/Public/Contrib/translations/`. The
    translation of the language of the current site is loaded, if it
    exists. Leave it empty to use the English user interface only. The
    property :yaml:`properties.richTextEditor.translationsPath` of a form
    element takes precedence.

..  _frontend-rich-text-editor-assets:

Loaded files
============

Rich text elements are rendered with the partial
:file:`EXT:pforum/Resources/Private/FormFramework/Partials/RichTextEditor/Textarea.html`
(:yaml:`renderingOptions.templateName` of the element). It is based on the
partial :file:`Textarea.html` of EXT:form and renders the partial
:file:`RichTextEditor/LoadAssets.html`, which loads the assets with the
ViewHelpers :html:`<f:asset.module>` and :html:`<f:asset.css>`:

*   The ES module `@jweiland/pforum/rich-text-editor.js`
    (:file:`EXT:pforum/Resources/Public/JavaScript/rich-text-editor.js`)
    creates an editor for each textarea with the attribute
    :html:`data-pforum-rich-text`.
*   The style sheets :file:`EXT:rte_ckeditor/Resources/Public/Css/editor.css`
    (identifier `pforum-ckeditor`) and
    :file:`EXT:pforum/Resources/Public/Css/RichTextEditor.css` (identifier
    `pforum-rich-text-editor`).

The import map of the CKEditor modules is defined in
:file:`EXT:pforum/Configuration/JavaScriptModules.php`.

The partial root path of pforum is set in the form definitions for the
topic and post forms only (key `1763890800`). To load own files, copy
:file:`RichTextEditor/LoadAssets.html` into your site package and add your
partial root path with a higher key, either in your copy of the form
definition or with TypoScript:

..  code-block:: typoscript
    :caption: EXT:my_sitepackage/Configuration/Sets/MySitepackage/setup.typoscript

    plugin.tx_pforum.settings.overrideForm {
      topic.renderingOptions.partialRootPaths.1800000000 = EXT:my_sitepackage/Resources/Private/FormFramework/Partials/
      post.renderingOptions.partialRootPaths.1800000000 = EXT:my_sitepackage/Resources/Private/FormFramework/Partials/
    }

To render a single element with another partial, set
:yaml:`renderingOptions.templateName` of the element in your copy of the
form definition.

..  _frontend-rich-text-editor-sanitizer:

Allowed tags
============

The submitted HTML is sanitized before it is stored: only paragraphs,
line breaks, headings, bold and italic text, lists and links (`http`,
`https`, `mailto` and `tel`) are kept. Afterward, the HTML is transformed
like DataHandler transforms the value of a backend RTE field. The toolbar
of the editor contains the buttons of the allowed tags only.

Use the PSR-14 event
:php:`\JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent`
to change the allowed, mapped and removed tags for a table and column.
The event provides the methods `getAllowedTags()`, `setAllowedTags()`,
`getMappedTags()`, `setMappedTags()`, `getRemovedTags()`,
`setRemovedTags()`, `getBlockTags()`, `setBlockTags()`, `getTable()` and
`getColumn()`.
