import { expect, test } from '@playwright/test';
import { createAuthor, createRecordData, ForumPage } from '../support/forum';
import { EXTBASE_GENERIC_ERROR, label } from '../support/labels';

test.describe('Topics of anonymous users', () => {
  test('an anonymous user creates a topic', async ({ page }) => {
    const forum = new ForumPage(page);
    const author = createAuthor('create');
    const topic = createRecordData('Topic');

    await forum.openNewTopicForm();
    await forum.fillForm(author, topic);
    await forum.submit('newTopic');

    await expect(forum.flashMessage(label('topicCreated'))).toBeVisible();
    await expect(page).toHaveURL(/tx_pforum_forum%5Baction%5D=show.*tx_pforum_forum%5Bcontroller%5D=Forum/);
    await expect(forum.topicLink(topic.title)).toBeVisible();
  });

  test('an invalid email shows the field error and no generic Extbase error', async ({ page }) => {
    const forum = new ForumPage(page);
    const author = { ...createAuthor('invalid'), email: 'not-an-email' };
    const topic = createRecordData('Invalid');

    await forum.openNewTopicForm();
    await forum.fillForm(author, topic);
    await forum.disableBrowserValidation();
    await forum.submit('newTopic');

    const emailField = forum.plugin.getByLabel(label('tx_pforum_domain_model_anonymoususer.email'));
    await expect(emailField).toHaveClass(/is-invalid/);
    await expect(
      forum.plugin.locator('.invalid-feedback').filter({ hasText: label('validator.anonymousUser.validEmail') }),
    ).toBeVisible();
    await expect(page.getByText(EXTBASE_GENERIC_ERROR)).toHaveCount(0);
    await expect(forum.flashMessage(label('topicCreated'))).toHaveCount(0);
  });

  test('the preview shows the topic in the edit form and publishing lists it', async ({ page }) => {
    const forum = new ForumPage(page);
    const author = createAuthor('preview');
    const topic = createRecordData('Preview');

    await forum.openNewTopicForm();
    await forum.fillForm(author, topic);
    await forum.submit('preview');

    await expect(forum.plugin.getByRole('heading', { level: 3, name: label('preview'), exact: true })).toBeVisible();
    await expect(forum.plugin.getByRole('heading', { level: 3, name: label('editTopic') })).toBeVisible();
    const previewCard = forum.plugin.locator('.card');
    await expect(previewCard.getByRole('heading', { level: 5, name: topic.title })).toBeVisible();
    await expect(previewCard.getByText(topic.description)).toBeVisible();
    await expect(forum.plugin.getByLabel(label('tx_pforum_domain_model_topic.title'))).toHaveValue(topic.title);

    await forum.submit('publish');

    await expect(forum.flashMessage(label('topicCreated'))).toBeVisible();
    await expect(forum.topicLink(topic.title)).toBeVisible();
  });
});
