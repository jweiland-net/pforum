..  include:: /Includes.rst.txt


..  _changelog:

=========
ChangeLog
=========

Version 9.0.0
=============

*   [!!!][TASK] Add compatibility with TYPO3 14.3 LTS and remove compatibility with TYPO3 13.4. PHP 8.2 is the
    minimum version.
*   [!!!][TASK] Move the backend module into the module group "Admin Tools" (`admin`, formerly `tools`)
*   [!!!][REFACTOR] Replace `AbstractController::afterPublishing()` by the PSR-14 event `RecordPublishedEvent`.
    The mail about a new post is sent by `SendNewPostMailEventListener`.
*   [!!!][REFACTOR] Move finding, storing, activating and removing records from `AbstractController` into
    `RecordPersistenceService`, and merging of FlexForm and TypoScript settings into `PluginSettingsResolver`.
    `AbstractController` is abstract, its constructor has changed and the protected members
    `checkForMisconfiguration()`, `persistRecord()`, `findRecord()`, `getCreationMessageKey()`, `$session`,
    `$frontendUserRepository`, `$persistenceManager`, `$anonymousAccessTokenService` and `$pathSegmentService`
    have been removed.
*   [!!!][REFACTOR] Remove `PostController::injectNewPostMail()` and `PostController::$newPostMail`
*   [!!!][TASK] Add native types to `HiddenRepositoryInterface::findHiddenObject()`
*   [!!!][TASK] Render the plugin preview with the variables `record` (record object of TYPO3 14) and `pforum` only
*   [!!!][REFACTOR] Send all mails with `PforumMailer`. `ManageRecordMail::send()` and `NewPostMail::send()`
    return whether the mail was sent.
*   [!!!][REFACTOR] Add the argument `$includeHidden` to `TopicRepository::findByForum()` and
    `PostRepository::findByTopic()` instead of changing the query settings of their result in the controllers
*   [TASK] Sign the tokens of the mail links with SHA3-256. Links of mails sent before stay valid.
*   [TASK] Use the Extbase attributes of namespace `TYPO3\CMS\Extbase\Attribute` with named arguments
*   [TASK] Register the FlexForm of the plugin with `ExtensionUtility::registerPlugin()`
*   [TASK] Render the plugin preview with the record API of TYPO3 14 and `FlexFormTools`
*   [TASK] Resolve the CKEditor translations with the system resource API
*   [TASK] Move the upgrade wizards to the upgrade API of EXT:core
*   [TASK] Remove `ctrl.searchFields` from TCA
*   [TASK] Log access denials, mails, activations, deletions and upgrade wizards
*   [TASK] Analyze the code with PHPStan level 9
*   [TASK] Add unit, functional and Playwright end-to-end tests
*   [BUGFIX] Show a warning, if the mail with the links of a new record can not be sent (sender, transport or
    template error), instead of an exception
*   [BUGFIX] Show an error in the backend module, if a record to activate does not exist anymore
*   [BUGFIX] Translate the messages of the backend module
*   [BUGFIX] Show the description of records in the backend module without HTML tags

Version 8.0.1
=============

*   [SECURITY] Check the pforum user of an edited topic or post
*   [BUGFIX] Fill path segments of records of former versions and of records without title
*   [BUGFIX] Render the labels of the submit buttons of the edit forms
*   [BUGFIX] Render links and texts of the plain text mails without HTML entities
*   [BUGFIX] Do not show the generic error message of Extbase above the forms
*   [BUGFIX] Keep paragraphs separated in the teaser of topics
*   [TASK] Remove the rights checkbox of checkfaluploads from the templates

Version 8.0.0
=============

*   [!!!][TASK] Rework access checks: confirmation pages for activation and deletion, own activation token,
    activation by members of the admin group, edited records keep their visibility
*   [!!!][FEATURE] Store descriptions of topics and posts as rich text with an optional CKEditor 5, see
    :ref:`frontend-rich-text-editor`
*   [FEATURE] Add path segments to topics and posts, see :ref:`routes`
*   [!!!][TASK] Use Bootstrap 5 in frontend templates
*   [!!!][BUGFIX] Render the plugin preview with the template of page TSconfig only
*   [!!!][TASK] Remove unused classes
*   [BUGFIX] Render the rights checkbox of checkfaluploads
*   [BUGFIX] Use site settings of topics, posts and images in TypoScript
*   [BUGFIX] Store descriptions of posts longer than 255 characters
*   [BUGFIX] Show the button to create records for members of the user group (`settings.auth = 2`)
*   [TASK] Replace the deprecated magic findBy methods of repositories

Version 7.0.1
=============

*   [TASK] Remove obsolete conversion of an empty `settings.pidOfDetailPage` from 0 to NULL. Since TYPO3 13
    the Fluid link/uri/form ViewHelpers fall back to the current page for a pageUid of 0

Version 7.0.0
=============

*   [TASK] Migrate Post/Topic image uploads to Extbase's native FileUpload API, including duplicate-file handling
    and automatic upload folder creation
*   [BUGFIX] Apply the `settings.new.uploadFolder` TypoScript/FlexForm setting to Post/Topic file uploads again,
    which had no effect since the migration to the native FileUpload API
*   [BUGFIX] Fix duplicate validation of mandatory email/username fields
*   [BUGFIX] Fix missing request context in FluidEmail sent from TopicController::mailToUser()
*   [TASK] Remove Singleton pattern from ExtConf and stop using TSFE in controllers and helpers
*   [TASK] Remove obsolete TCA columns and database fields already provided by TYPO3 core
*   [TASK] Make crdate nullable in Topic and Post
*   [TASK] Configure pforum backend templates via page TSconfig instead of ext_typoscript_setup.typoscript
*   [TASK] Remove obsolete static template registration left over from the Site Sets migration
*   [TASK] Suggest installing jweiland/checkfaluploads for mandatory upload consent
*   [DOCU] Fix typos and improve English wording across the documentation

Version 6.2.4
=============

*   [SECURITY] Restrict edit/update/delete/activate actions on Topics and Posts to their owning frontend user

Version 6.2.3
=============

*   [BUGFIX] Replace hardcoded tab labels with localized labels in tt_content

Version 6.2.2
=============

*  [BUGFIX] PSR Refactoring for controller actions with major fixes
*  [BUGFIX] Fix FluidEmail request context and session type safety in PostController
*  [BUGFIX] Backend Page Module Previews: Migrated the custom backend preview for the pforum plugin to the modern TYPO3 v12/v13 API.

Version 6.2.1
=============

*  [BUGFIX] Initialize error property in PostCheckFileReferenceEvent

Version 6.2.0
=============

*  [BUGFIX] Frontend FileUpload handling fix
*  [BUGFIX] PSR Refactoring for controller actions with major fixes

Version 6.1.2
=============

*  [BUGFIX] Fixed default values inside extension otherwise throws errors in dev.

Version 6.1.1
=============

*  [TASK] Updated wizard title with [extension] name format

Version 6.1.0
=============

*  [TASK] Implemented Upgrade wizard for migrating list_type to custom CType

Version 6.0.2
=============

*  [BUGFIX] SiteSet identifier renamed by removing default

Version 6.0.1
=============

*   [BUGFIX] Fixed Backend Module issues
*   [BUGFIX] Fixed Fluid Formatting Issues
*   [BUGFIX] Fixed TCA type bugs

Version 6.0.0
=============

*   [TASK] Add support for TYPO3 v13 LTS
*   [TASK] Removed old version support
*   [TASK] Replaced deprecated functions

Version 5.0.0
=============

*   [TASK] Add support for TYPO3 v12 LTS
*   [TASK] Removed old version support
*   [TASK] Updated Testing framework
*   [TASK] Replaced deprecated functions

Version 4.0.3
=============

*   [DOCU] Update route section
*   [DOCU] Missing space in Includes.rst.txt

Version 4.0.2
=============

*   [DOCU] Add documentation
*   [BUGFIX] Allow TYPO3 versions higher than 11.5.16

Version 4.0.1
=============

*   [BUGFIX] Use PostCheckFileReferenceEvent of pforum

Version 4.0.0
=============

*   Add TYPO3 11 compatibility
*   Remove TYPO3 9 compatibility
