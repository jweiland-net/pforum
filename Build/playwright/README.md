# Playwright end-to-end tests of EXT:pforum

Browser tests of the frontend plugin `pforum_forum` against a local DDEV
TYPO3 14 project (default: `https://typo3-14.ddev.site`) with Mailpit.

## Prerequisites

- A running DDEV project with EXT:pforum installed (`ddev composer require jweiland/pforum:@dev`,
  `ddev typo3 extension:setup`).
- A sender address for the mails of pforum. Without it every mail fails with
  "You have forgotten to set a sender email address" (see `var/log/typo3_*.log`)
  and the mail tests fail with the `mailNotSent` warning:

  ```bash
  ddev typo3 configuration:set EXTENSIONS/pforum/emailFromAddress forum-e2e@typo3-14.ddev.site
  ddev typo3 configuration:set EXTENSIONS/pforum/emailFromName "pforum E2E"
  ```

  Alternatively set `MAIL/defaultMailFromAddress` and `MAIL/defaultMailFromName`.
- Node.js 22 (e.g. `nvm use 22`).
- Chromium for Playwright 1.56 (`npx playwright install chromium`, if not cached).

## Seeding

`fixtures/seed.sql` is idempotent. It deletes and re-inserts only these records
(and the test records stored in the storage folder 9101):

| Table                          | uid  | Purpose                                              |
|--------------------------------|------|------------------------------------------------------|
| `pages`                        | 9100 | Page "Forum E2E" (`/forum-e2e`) below root page 1    |
| `pages`                        | 9101 | Storage folder (doktype 254) below 9100              |
| `tx_pforum_domain_model_forum` | 9100 | Forum "E2E Forum" in 9101                            |
| `tt_content`                   | 9100 | Plugin `pforum_forum` on 9100                        |
| `sys_template`                 | 9100 | Non-root template importing the PForum set setup     |

The template sets `pforum.storagePid = 9101`, anonymous users (`pforum.auth = 1`),
mandatory email and visible topics and posts at creation.

```bash
npm run seed        # or: bash scripts/seed.sh
```

The script detects the DDEV project root (the project containing
`Source/Extension/pforum`); override it with `PFORUM_E2E_PROJECT_ROOT`.

## Running

```bash
source ~/.nvm/nvm.sh && nvm use 22
npm install
npm run seed
npm test                      # npx playwright test
npx playwright show-report    # HTML report in playwright-report/
```

Environment variables:

| Variable                 | Default                             |
|--------------------------|-------------------------------------|
| `PFORUM_E2E_BASE_URL`    | `https://typo3-14.ddev.site`        |
| `PFORUM_E2E_MAILPIT_URL` | `https://typo3-14.ddev.site:8026`   |
| `PFORUM_E2E_PAGE_PATH`   | `/forum-e2e`                        |

## Structure

- `support/labels.ts` reads the labels from `Resources/Private/Language/locallang.xlf`,
  so assertions always match the rendered texts.
- `support/forum.ts` is the page object of the plugin and creates unique test data.
- `support/mailpit.ts` fetches the latest mail of a recipient from the Mailpit API
  and extracts the links of the plugin actions.
- `tests/*.spec.ts` are the independent test cases.

Test data must not contain words, which EXT:replacer of the host site replaces
(e.g. "Element", "Image", "Header").
