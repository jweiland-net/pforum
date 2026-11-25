import { type APIRequestContext, expect } from '@playwright/test';

const MAILPIT_URL = process.env.PFORUM_E2E_MAILPIT_URL ?? 'https://typo3-14.ddev.site:8026';

interface MessageSummary {
  ID: string;
  Subject: string;
  Created: string;
  To: Array<{ Address: string; Name: string }>;
}

export interface MailMessage {
  ID: string;
  Subject: string;
  Text: string;
  HTML: string;
}

/**
 * Uses the request fixture of Playwright, which ignores the self-signed
 * certificate of DDEV (ignoreHTTPSErrors in playwright.config.ts)
 */
async function getJson<T>(request: APIRequestContext, path: string): Promise<T> {
  const response = await request.get(`${MAILPIT_URL}${path}`);
  if (!response.ok()) {
    throw new Error(`Mailpit request ${path} failed with HTTP ${response.status()}`);
  }

  return (await response.json()) as T;
}

async function findLatestSummary(
  request: APIRequestContext,
  recipient: string,
  subject: string,
): Promise<MessageSummary | undefined> {
  const query = encodeURIComponent(`to:"${recipient}"`);
  const result = await getJson<{ messages: MessageSummary[] }>(request, `/api/v1/search?query=${query}&limit=50`);

  return result.messages
    .filter((message) => message.Subject === subject)
    .sort((a, b) => b.Created.localeCompare(a.Created))[0];
}

/**
 * Polls Mailpit until a message with the subject arrived for the recipient
 * and returns the latest one.
 */
export async function waitForLatestMessage(
  request: APIRequestContext,
  recipient: string,
  subject: string,
  timeout = 15_000,
): Promise<MailMessage> {
  let summary: MessageSummary | undefined;
  await expect
    .poll(
      async () => {
        summary = await findLatestSummary(request, recipient, subject);

        return summary !== undefined;
      },
      { timeout, message: `Mail "${subject}" to ${recipient} did not arrive in Mailpit` },
    )
    .toBe(true);

  return getJson<MailMessage>(request, `/api/v1/message/${summary!.ID}`);
}

/**
 * All absolute links of the plain text (or HTML) body of a mail
 */
export function extractLinks(message: MailMessage): string[] {
  const body = message.Text !== '' ? message.Text : message.HTML;
  const links = body.match(/https?:\/\/[^\s"'<>]+/g) ?? [];

  return [...new Set(links.map((link) => link.replace(/&amp;/g, '&')))];
}

/**
 * The link to the given Extbase action of the plugin
 */
export function findActionLink(message: MailMessage, action: string): string {
  const actionParameter = `tx_pforum_forum%5Baction%5D=${action}`;
  const link = extractLinks(message).find(
    (candidate) => candidate.includes(actionParameter) || decodeURIComponent(candidate).includes(`tx_pforum_forum[action]=${action}`),
  );
  if (link === undefined) {
    throw new Error(`No link to action "${action}" in mail "${message.Subject}":\n${message.Text}`);
  }

  return link;
}
