..  include:: /Includes.rst.txt


..  _introduction:

============
Introduction
============


..  _what-it-does:

What does it do?
================

`pforum` is a very tiny forum extension for TYPO3 CMS.

Features
========

*   Create forum records (backend only)
*   Create topic records (e.g. the question)
*   Create post records (e.g. the answer)
*   Store up to two images for each topic and post
*   Unauthenticated mode: every new topic or post creates its own pforum user record
*   Authenticated mode: the frontend user record (`fe_users`) is assigned to topics and posts
*   Inform users about new posts by email, if an email address is provided
*   Frontend admin users can manage topic and post records
*   Backend module to manage topics and posts

What it does not
=================

Please keep in mind that we don't want to provide a full-featured forum extension. If you need one, please use
the forum extension by Mittwald.

*   No images, such as smileys, in the textarea
*   No HTML in the textarea
*   No avatars for users
*   No highlighting of topics and posts
*   No pinned topics or posts at the top of the list
*   No overview of all topics and posts by a user
*   No quoting of previous topics or posts
*   No anchor links to jump to a specific topic or post
*   No birthday reminders
*   No links in general
