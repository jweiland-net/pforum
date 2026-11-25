-- Seed data for the Playwright end-to-end suite of EXT:pforum.
--
-- Idempotent: only the fixed uids 9100-9102 (pages), 9100 (forum, tt_content,
-- sys_template) and the records stored in the E2E storage folder (pid 9101)
-- are deleted and re-inserted. Nothing else is touched.
--
--   9100  page "Forum E2E" (/forum-e2e) below the root page 1 of site "sitepackage"
--   9101  storage folder "Forum E2E Storage" (doktype 254) below 9100
--   9100  tx_pforum_domain_model_forum "E2E Forum" in 9101
--   9100  tt_content CType pforum_forum on 9100
--   9100  sys_template (non-root) on 9100 importing the PForum set setup

SET @now = UNIX_TIMESTAMP();

-- Records created by previous test runs in the storage folder
DELETE FROM tx_pforum_domain_model_post WHERE pid = 9101;
DELETE FROM tx_pforum_domain_model_topic WHERE pid = 9101;
DELETE FROM tx_pforum_domain_model_anonymoususer WHERE pid = 9101;

DELETE FROM pages WHERE uid IN (9100, 9101);
DELETE FROM tx_pforum_domain_model_forum WHERE uid = 9100;
DELETE FROM tt_content WHERE uid = 9100;
DELETE FROM sys_template WHERE uid = 9100;

INSERT INTO pages
    (uid, pid, tstamp, crdate, sorting, doktype, title, slug, hidden, deleted,
     perms_userid, perms_groupid, perms_user, perms_group, perms_everybody, nav_hide)
VALUES
    (9100, 1, @now, @now, 99100, 1, 'Forum E2E', '/forum-e2e', 0, 0, 1, 0, 31, 27, 0, 1),
    (9101, 9100, @now, @now, 99101, 254, 'Forum E2E Storage', '/forum-e2e/storage', 0, 0, 1, 0, 31, 27, 0, 1);

INSERT INTO tx_pforum_domain_model_forum
    (uid, pid, tstamp, crdate, sorting, hidden, deleted, sys_language_uid, title, teaser, topics)
VALUES
    (9100, 9101, @now, @now, 1, 0, 0, 0, 'E2E Forum', 'Forum for automated end-to-end tests', 0);

INSERT INTO tt_content
    (uid, pid, tstamp, crdate, sorting, hidden, deleted, sys_language_uid, colPos, CType, header, header_layout, pi_flexform)
VALUES
    (9100, 9100, @now, @now, 256, 0, 0, 0, 0, 'pforum_forum', '', 100, '');

INSERT INTO sys_template
    (uid, pid, tstamp, crdate, sorting, hidden, deleted, title, root, clear, constants, config)
VALUES
    (9100, 9100, @now, @now, 256, 0, 0, 'pforum E2E', 0, 0,
'pforum.templateRootPath = EXT:pforum/Resources/Private/Templates/
pforum.partialRootPath = EXT:pforum/Resources/Private/Partials/
pforum.layoutRootPath = EXT:pforum/Resources/Private/Layouts/
pforum.storagePid = 9101
pforum.auth = 1
pforum.emailIsMandatory = 1
pforum.usernameIsMandatory = 0
pforum.useImages = 0
pforum.uidOfAdminGroup = 0
pforum.uidOfUserGroup = 0
pforum.pidOfDetailPage = 0
pforum.topic.hideAtCreation = 0
pforum.topic.activateByAdmin = 0
pforum.post.hideAtCreation = 0
pforum.post.activateByAdmin = 0
pforum.new.uploadFolder = 1:/user_upload/tx_pforum/
pforum.form.richText.enable = 0
pforum.form.richText.elements = description
pforum.form.richText.translationsPath = EXT:rte_ckeditor/Resources/Public/Contrib/translations/
pforum.image.width = 120c
pforum.image.height = 90c
pforum.image.minWidth = 120
pforum.image.maxWidth = 120
pforum.image.minHeight = 90
pforum.image.maxHeight = 90
pforum.pageBrowser.itemsPerPage = 15',
'@import ''EXT:pforum/Configuration/Sets/PForum/setup.typoscript''');
