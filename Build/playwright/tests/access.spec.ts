import { expect, test } from '@playwright/test';
import { createAuthor, createRecordData, ForumPage } from '../support/forum';
import { label } from '../support/labels';

const TOKEN_PARAMETER = 'tx_pforum_forum[token]';

/**
 * Replaces the first character of the token, so the token has the same
 * format but is not valid anymore
 */
function tamperToken(token: string): string {
  return (token.startsWith('0') ? '1' : '0') + token.slice(1);
}

test.describe('Access control of anonymous records', () => {
  test('an edit URL with a tampered token is denied', async ({ page }) => {
    const forum = new ForumPage(page);
    const author = createAuthor('tamper');
    const topic = createRecordData('Tamper');

    // The preview redirects to the edit action with a valid access token,
    // the same token as in the edit link of the manage mail
    await forum.openNewTopicForm();
    await forum.fillForm(author, topic);
    await forum.submit('preview');
    await expect(forum.plugin.getByRole('heading', { level: 3, name: label('editTopic') })).toBeVisible();

    const editUrl = new URL(page.url());
    const token = editUrl.searchParams.get(TOKEN_PARAMETER) ?? '';
    expect(token, 'The edit URL of the preview contains an access token').not.toBe('');

    const topicUid = editUrl.searchParams.get('tx_pforum_forum[topic]') ?? '';
    const tamperedToken = tamperToken(token);

    // GET: the cHash covers the token, so TYPO3 rejects the tampered URL before the plugin
    const tamperedGetUrl = new URL(editUrl);
    tamperedGetUrl.searchParams.set(TOKEN_PARAMETER, tamperedToken);
    const getResponse = await page.goto(tamperedGetUrl.toString());
    expect(getResponse?.status()).toBe(404);

    // POST: plugin arguments in the body are not covered by the cHash, so the token check of pforum decides
    await forum.gotoList();
    await page.evaluate(
      ({ action, fields }) => {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = action;
        for (const [name, value] of Object.entries(fields)) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          input.value = value;
          form.append(input);
        }
        document.body.append(form);
        form.submit();
      },
      {
        action: editUrl.pathname,
        fields: {
          'tx_pforum_forum[action]': 'edit',
          'tx_pforum_forum[controller]': 'Topic',
          'tx_pforum_forum[topic]': topicUid,
          [TOKEN_PARAMETER]: tamperedToken,
        },
      },
    );
    await page.waitForLoadState('domcontentloaded');

    await expect(forum.flashMessage(label('unauthorizedUser'))).toBeVisible();
    await expect(page.locator('form[name="topic"]')).toHaveCount(0);
    await expect(forum.plugin.getByRole('heading', { level: 3, name: label('editTopic') })).toHaveCount(0);
  });
});
