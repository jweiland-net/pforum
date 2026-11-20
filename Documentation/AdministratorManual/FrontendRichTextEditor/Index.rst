..  include:: /Includes.rst.txt

..  _frontend-rich-text-editor:

======================================
Rich text editor in the frontend forms
======================================

..  versionadded:: 8.0.0

    The description of topics and posts can be edited with a CKEditor 5.

The description of topics and posts is stored as HTML like the backend RTE
stores it. Without the rich text editor, the description is a plain
textarea: a line break becomes :html:`<br />`, an empty line starts a new
paragraph. When a record is edited, the stored HTML is shown as plain
text.

With the rich text editor, the description is edited with a small CKEditor
5. The submitted HTML is sanitized before it is stored.

..  contents::
    :caption: Table of contents
    :local:

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
    Comma separated names of the fields, which get the editor. Default and
    currently only supported field: `description`.

`pforum.form.richText.translationsPath`
    Folder with the translations of the user interface of CKEditor 5, e.g.
    :file:`de.js`. Default:
    `EXT:rte_ckeditor/Resources/Public/Contrib/translations/`. The
    translation of the language of the current site is loaded, if it
    exists. Leave it empty to use the English user interface only.

..  _frontend-rich-text-editor-sanitizer:

Allowed tags
============

The submitted HTML is sanitized before it is stored: only paragraphs,
line breaks, headings, bold and italic text, lists and links (`http`,
`https`, `mailto` and `tel`) are kept. Afterward, the HTML is transformed
like DataHandler transforms the value of a backend RTE field. The toolbar
of the editor contains the buttons of the allowed tags only. The preview
of new records, the confirmation pages and the mail about a new post
render the description sanitized with
:php:`\JWeiland\Pforum\ViewHelpers\FormatRichTextViewHelper`.

Use the PSR-14 event
:php:`\JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent`
to change the allowed, mapped and removed tags for a table and column.

..  _frontend-rich-text-editor-assets:

Loaded files
============

The partial :file:`RichTextEditor/Description.html` renders the textarea
with the options of the editor in the attribute
:html:`data-pforum-rich-text` and renders the partial
:file:`RichTextEditor/LoadAssets.html`, which loads the assets with the
ViewHelpers :html:`<f:asset.module>` and :html:`<f:asset.css>`:

*   The ES module `@jweiland/pforum/rich-text-editor.js`
    (:file:`EXT:pforum/Resources/Public/JavaScript/rich-text-editor.js`),
    mapped in :file:`EXT:pforum/Configuration/JavaScriptModules.php`
*   The style sheets :file:`EXT:rte_ckeditor/Resources/Public/Css/editor.css`
    (identifier `pforum-ckeditor`) and
    :file:`EXT:pforum/Resources/Public/Css/RichTextEditor.css` (identifier
    `pforum-rich-text-editor`)

To load own files, copy :file:`RichTextEditor/LoadAssets.html` into the
partial root path of your site package
(`plugin.tx_pforum.view.partialRootPaths`).
