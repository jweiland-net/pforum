..  include:: /Includes.rst.txt


..  _extensionSettings:

==================
Extension settings
==================

Some general settings for `pforum` can be configured in :guilabel:`Admin Tools > Settings`.


Tab: Basic
==========

From email address
-------------------

Default: <empty>

Define the email address that is used to inform users about new topics and posts.

If this value is empty, `pforum` tries to use the email address from
`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']`. If this is not set either, sending an email
fails with an exception.

From email name
-----------------

Default: <empty>

Define the sender name that is used to inform users about new topics and posts.

If this value is empty, `pforum` tries to use the sender name from
`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName']`. If this is not set either, sending an email
fails with an exception.
