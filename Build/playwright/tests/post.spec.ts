import { expect, test } from '@playwright/test';
import { createAuthor, createRecordData, ForumPage } from '../support/forum';
import { label } from '../support/labels';
import { waitForLatestMessage } from '../support/mailpit';

test.describe('Posts of anonymous users', () => {
  test('creating a post notifies the author of the topic', async ({ page, request }) => {
    const forum = new ForumPage(page);
    const topicAuthor = createAuthor('topicowner');
    const topic = createRecordData('Discussion');
    await forum.createTopic(topicAuthor, topic);

    await forum.topicLink(topic.title).click();
    await expect(forum.plugin.getByText(label('noPosts'))).toBeVisible();
    await forum.plugin.getByRole('link', { name: label('newPost') }).click();
    await expect(page.locator('form[name="post"]')).toBeVisible();

    const postAuthor = createAuthor('poster');
    const post = createRecordData('Reply');
    await forum.fillForm(postAuthor, post);
    await forum.submit('newPost');

    await expect(forum.flashMessage(label('postCreated'))).toBeVisible();
    await expect(forum.plugin.getByRole('heading', { level: 5, name: post.title })).toBeVisible();
    await expect(forum.plugin.getByText(post.description)).toBeVisible();

    const mail = await waitForLatestMessage(request, topicAuthor.email, label('email.newPost.subject', topic.title));
    expect(mail.Text).toContain(post.description);
  });
});
