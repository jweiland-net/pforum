..  include:: /Includes.rst.txt


..  _changelog:

=========
ChangeLog
=========

Version 5.0.1
=============

*   [SECURITY] Restrict edit/update/delete/activate actions on Topics and Posts to their owning frontend user
*   [BUGFIX] Use Extbase HashService, TYPO3\CMS\Core\Crypto\HashService doesn't exist in v12
*   [TASK] Migrate starttime/endtime TCA fields from inputDateTime to type=datetime
*   [TASK] Migrate test cases from @test annotation to #[Test] attribute
*   [TASK] Allow composer install against currently advisory-flagged TYPO3 12.4 core

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
