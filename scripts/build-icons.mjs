/**
 * Inlines the icons listed in resources/js/icon-manifest.mjs into
 * resources/js/icons.generated.js.
 *
 * The icon packs are dev-only dependencies: nothing ships to the browser except
 * the handful of path strings we actually use, so the app keeps working offline
 * with no icon font or CDN.
 *
 * Usage: npm run icons
 */
import { createRequire } from 'node:module';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { ICON_MANIFEST } from '../resources/js/icon-manifest.mjs';

const require = createRequire(import.meta.url);
const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outputPath = resolve(projectRoot, 'resources/js/icons.generated.js');

const PACKS = {
  tabler: { module: '@iconify-json/tabler/icons.json', licence: 'MIT' },
  lucide: { module: '@iconify-json/lucide/icons.json', licence: 'ISC' },
};

/** Tabler prefixes every icon with an invisible 24x24 hit box we do not need. */
const BOUNDING_BOX = /<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/>/g;

/**
 * Presentation attributes the packs repeat on every element. Icon.vue sets these
 * on the <svg> instead, so dropping them lets the size/stroke props take effect.
 */
const INHERITED_ATTRIBUTES =
  /\s(?:fill="none"|stroke="currentColor"|stroke-width="[\d.]+"|stroke-linecap="round"|stroke-linejoin="round")/g;

const loadedPacks = new Map();

function loadPack(name) {
  if (!loadedPacks.has(name)) {
    if (!PACKS[name]) {
      throw new Error(`Unknown icon pack "${name}". Add it to PACKS first.`);
    }

    loadedPacks.set(name, require(PACKS[name].module));
  }

  return loadedPacks.get(name);
}

/** Follows Iconify aliases so "pack:alias" resolves to the real geometry. */
function resolveIcon(pack, iconName, depth = 0) {
  if (depth > 5) {
    throw new Error(`Alias loop while resolving ${iconName}`);
  }

  const icon = pack.icons?.[iconName];
  if (icon) return icon;

  const alias = pack.aliases?.[iconName];
  if (alias?.parent) return resolveIcon(pack, alias.parent, depth + 1);

  return null;
}

function normalise(body) {
  return body
    .replace(BOUNDING_BOX, '')
    .replace(INHERITED_ATTRIBUTES, '')
    .replace(/<g\s*>/g, '<g>')
    .replace(/\s+/g, ' ')
    .trim();
}

async function build() {
  const entries = [];
  const missing = [];

  for (const [name, reference] of Object.entries(ICON_MANIFEST)) {
    const [packName, iconName] = reference.split(':');
    const pack = loadPack(packName);
    const icon = resolveIcon(pack, iconName);

    if (!icon) {
      missing.push(`${name} -> ${reference}`);
      continue;
    }

    const width = icon.width ?? pack.width ?? 24;
    const height = icon.height ?? pack.height ?? 24;

    if (width !== 24 || height !== 24) {
      missing.push(`${name} -> ${reference} (unsupported ${width}x${height} grid)`);
      continue;
    }

    entries.push([name, normalise(icon.body), reference]);
  }

  if (missing.length) {
    console.error('Could not resolve:\n  ' + missing.join('\n  '));
    process.exitCode = 1;

    return;
  }

  const credits = Object.entries(PACKS)
    .map(([name, meta]) => ` * ${name} icons — ${meta.licence} licensed.`)
    .join('\n');

  const body = entries
    .map(([name, path, reference]) => `  // ${reference}\n  ${JSON.stringify(name)}: ${JSON.stringify(path)},`)
    .join('\n');

  const file = `/**
 * GENERATED FILE — do not edit by hand.
 * Run \`npm run icons\` after changing resources/js/icon-manifest.mjs.
 *
${credits}
 */
export const GENERATED_ICONS = {
${body}
};
`;

  await mkdir(dirname(outputPath), { recursive: true });
  await writeFile(outputPath, file, 'utf8');

  console.log(`Wrote ${entries.length} icons to resources/js/icons.generated.js`);
}

build().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
