import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Reads the English labels of EXT:pforum from locallang.xlf, so the tests
 * always match the texts rendered by the extension.
 */
const LOCALLANG_FILE = resolve(__dirname, '../../../Resources/Private/Language/locallang.xlf');

const ENTITIES: Record<string, string> = {
  '&quot;': '"',
  '&apos;': "'",
  '&lt;': '<',
  '&gt;': '>',
  '&amp;': '&',
};

function parseLabels(xliff: string): Map<string, string> {
  const labels = new Map<string, string>();
  const pattern = /<trans-unit\s+id="([^"]+)"[^>]*>\s*<source>([\s\S]*?)<\/source>/g;
  for (const match of xliff.matchAll(pattern)) {
    const source = match[2].replace(/&(quot|apos|lt|gt|amp);/g, (entity) => ENTITIES[entity] ?? entity);
    labels.set(match[1], source.trim());
  }

  return labels;
}

const labels = parseLabels(readFileSync(LOCALLANG_FILE, 'utf8'));

/**
 * Returns the label of the given key. Arguments replace %s placeholders in order.
 */
export function label(key: string, ...args: string[]): string {
  const value = labels.get(key);
  if (value === undefined) {
    throw new Error(`Label "${key}" not found in ${LOCALLANG_FILE}`);
  }

  let index = 0;

  return value.replace(/%s/g, () => args[index++] ?? '%s');
}

/** Generic Extbase message, which must not appear for validation errors */
export const EXTBASE_GENERIC_ERROR = 'An error occurred while trying to call';
