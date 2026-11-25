import { expect, test } from '@playwright/test';
import { ForumPage, SEEDED_FORUM_TITLE } from '../support/forum';
import { label } from '../support/labels';

test.describe('Forum list and detail', () => {
  test('the forum list shows the seeded forum', async ({ page }) => {
    const forum = new ForumPage(page);
    await forum.gotoList();

    await expect(forum.plugin.getByRole('link', { name: SEEDED_FORUM_TITLE })).toBeVisible();
    await expect(forum.plugin.getByText('Forum for automated end-to-end tests')).toBeVisible();
  });

  test('the forum detail page shows its topics and a link to create a topic', async ({ page }) => {
    const forum = new ForumPage(page);
    await forum.openSeededForum();

    await expect(forum.plugin.getByRole('navigation', { name: 'breadcrumb' })).toBeVisible();
    await expect(forum.newTopicLink()).toBeVisible();
    // Either the list of topics or the hint, that there are no topics yet
    const topicsOrHint = forum.plugin.locator('.row.border-bottom').or(forum.plugin.getByText(label('noTopics')));
    await expect(topicsOrHint.first()).toBeVisible();
  });
});
