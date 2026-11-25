import { expect, test } from '@playwright/test';
import { createAuthor, createRecordData, ForumPage } from '../support/forum';
import { label } from '../support/labels';
import { findActionLink, waitForLatestMessage } from '../support/mailpit';

test.describe('Management of topics by the links of the mail', () => {
  test('the edit link of the manage mail opens the edit form and updates the topic', async ({ page, request }) => {
    const forum = new ForumPage(page);
    const author = createAuthor('edit');
    const topic = createRecordData('Edit');
    await forum.createTopic(author, topic);
    await forum.expectMailWasSent();

    const mail = await waitForLatestMessage(request, author.email, label('email.topic.subject'));
    await page.goto(findActionLink(mail, 'edit'));

    await expect(forum.plugin.getByRole('heading', { level: 3, name: label('editTopic') })).toBeVisible();
    const titleField = forum.plugin.getByLabel(label('tx_pforum_domain_model_topic.title'));
    await expect(titleField).toHaveValue(topic.title);

    const updatedTitle = `${topic.title} updated`;
    await titleField.fill(updatedTitle);
    await forum.submit('save');

    await expect(forum.flashMessage(label('topicUpdated'))).toBeVisible();
    await expect(forum.topicLink(updatedTitle)).toBeVisible();
  });

  test('the delete link of the manage mail asks for confirmation and deletes the topic', async ({ page, request }) => {
    const forum = new ForumPage(page);
    const author = createAuthor('delete');
    const topic = createRecordData('Delete');
    await forum.createTopic(author, topic);
    await forum.expectMailWasSent();

    const mail = await waitForLatestMessage(request, author.email, label('email.topic.subject'));
    await page.goto(findActionLink(mail, 'delete'));

    // GET shows the confirmation only, the topic still exists
    await expect(forum.plugin.getByRole('heading', { level: 2, name: label('deleteTopic') })).toBeVisible();
    await expect(forum.plugin.getByText(label('delete.description'))).toBeVisible();
    await expect(forum.plugin.locator('form')).toHaveAttribute('method', /post/i);

    await forum.submit('deleteTopic');

    await expect(forum.flashMessage(label('topicDeleted'))).toBeVisible();
    await expect(forum.topicLink(topic.title)).toHaveCount(0);
  });
});
