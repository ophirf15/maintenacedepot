/**
 * Renders every icon into public/icon-sheet.html for a quick visual check.
 * Dev aid only: `node scripts/icon-sheet.mjs` then open /icon-sheet.html.
 */
import { writeFile } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

import { TOOL_ICONS } from '../resources/js/icons.tools.js';
import { GENERATED_ICONS } from '../resources/js/icons.generated.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

function section(title, icons) {
  const cells = Object.entries(icons)
    .map(
      ([name, body]) =>
        `<div class="cell"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${body}</svg><span>${name}</span></div>`
    )
    .join('');

  return `<h3>${title} (${Object.keys(icons).length})</h3><div class="grid">${cells}</div>`;
}

const html = `<!doctype html>
<meta charset="utf-8">
<title>Icon sheet</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 20px; color: #18181b; }
  .grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 8px; margin-bottom: 28px; }
  .cell { border: 1px solid #e4e4e7; border-radius: 10px; padding: 8px 4px; text-align: center; }
  .cell span { display: block; margin-top: 4px; font-size: 10px; color: #71717a; word-break: break-word; }
</style>
${section('Bespoke equipment icons', TOOL_ICONS)}
${section('Pack icons', GENERATED_ICONS)}
`;

await writeFile(resolve(root, 'public/icon-sheet.html'), html, 'utf8');
console.log('Icon sheet written to public/icon-sheet.html');
