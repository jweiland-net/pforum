import { expect, type Locator, type Page } from '@playwright/test';
import { label } from './labels';
import { randomUUID } from 'node:crypto';

export const FORUM_PAGE_PATH = process.env.PFORUM_E2E_PAGE_PATH ?? '/forum-e2e';
export const SEEDED_FORUM_TITLE = 'E2E Forum';

export interface AuthorData {
  name: string;
  username: string;
  email: string;
}

export interface RecordData {
  title: string;
  description: string;
}

/**
 * Unique test data per test and run. Avoid words replaced by EXT:replacer
 * of the host site (e.g. "Element", "Image", "Header").
 */
export function uniqueSuffix(): string {
  return `${Date.now()}-${randomUUID().slice(0, 8)}`;
}

export function createAuthor(prefix: string): AuthorData {
  const suffix = uniqueSuffix();

  return {
    name: `E2E ${prefix} Author ${suffix}`,
    username: `e2e-${prefix}-${suffix}`,
    email: `e2e-${prefix}-${suffix}@example.com`.toLowerCase(),
  };
}

export function createRecordData(prefix: string): RecordData {
  const suffix = uniqueSuffix();

  return {
    title: `E2E ${prefix} ${suffix}`,
    description: `Description of ${prefix} ${suffix} written by Playwright.`,
  };
}

/**
 * Page object of the pforum plugin on the E2E page
 */
export class ForumPage {
  readonly plugin: Locator;

  constructor(readonly page: Page) {
    this.plugin = page.locator('.tx-pforum');
  }

  async gotoList(): Promise<void> {
    await this.page.goto(FORUM_PAGE_PATH);
    await expect(this.plugin.getByRole('heading', { level: 2, name: label('tx_pforum_domain_model_forum') })).toBeVisible();
  }

  async openSeededForum(): Promise<void> {
    await this.gotoList();
    await this.plugin.getByRole('link', { name: SEEDED_FORUM_TITLE }).click();
    await expect(this.plugin.getByRole('heading', { level: 2, name: SEEDED_FORUM_TITLE })).toBeVisible();
  }

  newTopicLink(): Locator {
    return this.plugin.getByRole('link', { name: label('newTopic') });
  }

  topicLink(title: string): Locator {
    return this.plugin.getByRole('link', { name: title, exact: true });
  }

  flashMessage(text: string): Locator {
    return this.plugin.getByText(text, { exact: false });
  }

  async openNewTopicForm(): Promise<void> {
    await this.openSeededForum();
    await this.newTopicLink().click();
    await expect(this.page.locator('form[name="topic"]')).toBeVisible();
  }

  /**
   * Fills the fields of the anonymous user and of the topic or post
   */
  async fillForm(author: AuthorData, record: RecordData): Promise<void> {
    await this.plugin.getByLabel(label('tx_pforum_domain_model_anonymoususer.name'), { exact: true }).fill(author.name);
    await this.plugin.getByLabel(label('tx_pforum_domain_model_anonymoususer.username')).fill(author.username);
    await this.plugin.getByLabel(label('tx_pforum_domain_model_anonymoususer.email')).fill(author.email);
    await this.plugin.getByLabel(label('tx_pforum_domain_model_topic.title')).fill(record.title);
    await this.plugin.getByLabel(label('tx_pforum_domain_model_topic.description')).fill(record.description);
  }

  /**
   * Disables the HTML5 validation of the browser, so the server side
   * validation of Extbase is tested
   */
  async disableBrowserValidation(): Promise<void> {
    await this.plugin.locator('form').evaluateAll((forms) => {
      for (const form of forms) {
        (form as HTMLFormElement).noValidate = true;
      }
    });
  }

  async submit(buttonLabelKey: string): Promise<void> {
    await this.plugin.getByRole('button', { name: label(buttonLabelKey), exact: true }).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  async createTopic(author: AuthorData, topic: RecordData): Promise<void> {
    await this.openNewTopicForm();
    await this.fillForm(author, topic);
    await this.submit('newTopic');
    await expect(this.flashMessage(label('topicCreated'))).toBeVisible();
  }

  /**
   * pforum shows a warning, if the mail to the author could not be sent
   * (e.g. no sender in the extension configuration of pforum)
   */
  async expectMailWasSent(): Promise<void> {
    await expect(
      this.flashMessage(label('mailNotSent')),
      'pforum could not send the mail. Check EXTENSIONS/pforum/emailFromAddress and var/log/typo3_*.log',
    ).toHaveCount(0);
  }

  async openTopic(title: string): Promise<void> {
    await this.openSeededForum();
    await this.topicLink(title).click();
    await expect(this.plugin.getByRole('heading', { level: 5, name: title })).toBeVisible();
  }
}
