..  include:: /Includes.rst.txt


========
Updating
========

If you update `pforum` to a newer version, please read this section carefully!

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
