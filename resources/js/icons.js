/**
 * Inline 24x24 stroke icons. Nothing is fetched at runtime so the app keeps
 * working offline.
 *
 * Three sources feed the set, later ones winning on a name clash:
 *   - LOCAL below: hand-trimmed interface icons.
 *   - icons.generated.js: general shapes from the Tabler (MIT) and Lucide (ISC)
 *     packs. Edit icon-manifest.mjs and run `npm run icons`.
 *   - icons.tools.js: equipment the open packs simply do not draw.
 */
import { GENERATED_ICONS } from './icons.generated';
import { TOOL_ICONS } from './icons.tools';
import { ALIASES, KEYWORDS } from './icon-names';

const LOCAL = {
  home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.8V20h14V9.8"/><path d="M9.5 20v-6h5v6"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
  grid: '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
  package:
    '<path d="M21 8v8a2 2 0 0 1-1.1 1.8l-7 3.6a2 2 0 0 1-1.8 0l-7-3.6A2 2 0 0 1 3 16V8"/><path d="m3.3 7.2 8.7 4.4 8.7-4.4"/><path d="M12 21.8V11.6"/><path d="M12 2.2 3.3 6.6l8.7 4.4 8.7-4.4Z"/>',
  cart: '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3h2.2l2.3 11.2a1.6 1.6 0 0 0 1.6 1.3h9.6a1.6 1.6 0 0 0 1.6-1.3L21 7H6"/>',
  clipboard:
    '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9.5 4V3a1.5 1.5 0 0 1 1.5-1.5h2A1.5 1.5 0 0 1 14.5 3v1"/><path d="M9.5 10h5"/><path d="M9.5 14h5"/><path d="M9.5 18h3"/>',
  check: '<path d="m4 12.5 5 5L20 6.5"/>',
  'check-circle': '<circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.6 2.6L16 9.4"/>',
  x: '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
  'x-circle': '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/>',
  plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
  minus: '<path d="M5 12h14"/>',
  'chevron-right': '<path d="m9 5 7 7-7 7"/>',
  'chevron-left': '<path d="m15 5-7 7 7 7"/>',
  'chevron-down': '<path d="m5 9 7 7 7-7"/>',
  'chevron-up': '<path d="m5 15 7-7 7 7"/>',
  'arrow-right': '<path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>',
  'arrow-left': '<path d="M20 12H5"/><path d="m11 18-6-6 6-6"/>',
  bell: '<path d="M18 8.5a6 6 0 1 0-12 0c0 6-2.5 7.5-2.5 7.5h17S18 14.5 18 8.5"/><path d="M13.7 20a2 2 0 0 1-3.4 0"/>',
  user: '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
  users:
    '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20.5a6.6 6.6 0 0 1 13 0"/><path d="M16.5 5.2a3.4 3.4 0 0 1 0 6.6"/><path d="M18 20.5a6.7 6.7 0 0 0-2-4.7"/>',
  logout: '<path d="M9 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3"/><path d="M16 8l4 4-4 4"/><path d="M20 12H9"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2.2"/><path d="M12 19.3v2.2"/><path d="m4.9 4.9 1.6 1.6"/><path d="m17.5 17.5 1.6 1.6"/><path d="M2.5 12h2.2"/><path d="M19.3 12h2.2"/><path d="m4.9 19.1 1.6-1.6"/><path d="m17.5 6.5 1.6-1.6"/>',
  moon: '<path d="M20.5 14.2A8.2 8.2 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"/>',
  monitor: '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8 20.5h8"/><path d="M12 16.5v4"/>',
  settings:
    '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3.1 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.2-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4.1a2 2 0 1 1 4 0A1.7 1.7 0 0 0 16.9 5.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9 2 2 0 1 1 0 4 1.7 1.7 0 0 0-1.5 1.9Z"/>',
  shield: '<path d="M12 22s7-3.2 7-9V5.5L12 3 5 5.5V13c0 5.8 7 9 7 9Z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
  building:
    '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2"/><path d="M13 7h2"/><path d="M9 11h2"/><path d="M13 11h2"/><path d="M9 15h6v6H9z"/>',
  depot: '<path d="M3 10 12 4l9 6"/><path d="M5 9.5V20h14V9.5"/><path d="M9 20v-5h6v5"/><path d="M9 12h6"/>',
  truck:
    '<path d="M3 16V6.5A1.5 1.5 0 0 1 4.5 5h8.7A1.5 1.5 0 0 1 14.7 6.5V16"/><path d="M14.7 9h3.1l3.2 3.4V16"/><circle cx="7.5" cy="18" r="2"/><circle cx="17" cy="18" r="2"/><path d="M9.5 18h5.5"/>',
  qr: '<rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1.5"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1.5"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1.5"/><path d="M14 14h2.5v2.5H14z"/><path d="M18.5 18.5H21V21h-2.5z"/><path d="M14 20.5h2"/><path d="M20.5 14h.5"/>',
  scan: '<path d="M4 8V6a2 2 0 0 1 2-2h2"/><path d="M16 4h2a2 2 0 0 1 2 2v2"/><path d="M20 16v2a2 2 0 0 1-2 2h-2"/><path d="M8 20H6a2 2 0 0 1-2-2v-2"/><path d="M4 12h16"/>',
  wrench:
    '<path d="M15.5 3a5.5 5.5 0 0 0-4.7 8.3L3.6 18.5a1.8 1.8 0 0 0 2.5 2.5l7.2-7.2A5.5 5.5 0 1 0 15.5 3Z"/><circle cx="16.5" cy="7.5" r="1.2"/>',
  tool: '<path d="M14.5 5.5a3.5 3.5 0 1 1 4.9 4.9L8.6 21.2a2 2 0 0 1-2.8-2.8Z"/><path d="m12.5 7.5 4 4"/>',
  hammer:
    '<path d="M14.8 3.2 12 6l2 2 2.8-2.8a2 2 0 0 0-2.8-2.8Z"/><path d="m11 7-6.6 6.6a2 2 0 0 0 0 2.9l2.1 2.1a2 2 0 0 0 2.9 0L16 12Z"/><path d="m5.5 12.5 2.6 2.6"/>',
  scissors:
    '<circle cx="6" cy="6.5" r="2.5"/><circle cx="6" cy="17.5" r="2.5"/><path d="M8.2 8.2 20 19"/><path d="M8.2 15.8 20 5"/>',
  droplet: '<path d="M12 3s6 6.3 6 10.4A6 6 0 0 1 6 13.4C6 9.3 12 3 12 3Z"/>',
  mower:
    '<path d="M3 16h7l1.5-4H21"/><path d="M3 16v2h18v-2"/><circle cx="6" cy="19.5" r="1.6"/><circle cx="17" cy="19.5" r="1.6"/><path d="M11 12V6h4"/>',
  gauge: '<path d="M12 20a8 8 0 1 1 8-8"/><path d="M12 20a8 8 0 0 0 8-8"/><path d="m12 12 4.2-3.2"/><circle cx="12" cy="12" r="1.4"/>',
  fuel: '<path d="M6 21V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v16"/><path d="M6 12h8"/><path d="M14 8h2.5a2.5 2.5 0 0 1 2.5 2.5V17a1.8 1.8 0 0 0 3.5 0"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
  calendar:
    '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17"/><path d="M8 3v3.5"/><path d="M16 3v3.5"/>',
  alert: '<path d="M12 3.5 21 19H3Z"/><path d="M12 9.5v4"/><path d="M12 16.8h.01"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
  ticket:
    '<path d="M4 8.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H6a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M14 6.5v10"/>',
  chart: '<path d="M4 20V4"/><path d="M4 20h16"/><rect x="7.5" y="12" width="3" height="5"/><rect x="12.5" y="8" width="3" height="9"/><rect x="17" y="10" width="3" height="7"/>',
  money: '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6.5 12h.01"/><path d="M17.5 12h.01"/>',
  download: '<path d="M12 4v10"/><path d="m7.5 10 4.5 4.5L16.5 10"/><path d="M4.5 19h15"/>',
  upload: '<path d="M12 15V5"/><path d="m7.5 9 4.5-4.5L16.5 9"/><path d="M4.5 19h15"/>',
  refresh: '<path d="M20 11a8 8 0 0 0-13.7-4.6L4 8.5"/><path d="M4 4.5v4h4"/><path d="M4 13a8 8 0 0 0 13.7 4.6L20 15.5"/><path d="M20 19.5v-4h-4"/>',
  camera:
    '<path d="M4 8.5h3l1.4-2.2h7.2L17 8.5h3a1 1 0 0 1 1 1V18a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13.5" r="3.2"/>',
  file: '<path d="M14 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5Z"/><path d="M14 3.5V8.5h5"/><path d="M9 13h6"/><path d="M9 16.5h4"/>',
  book: '<path d="M4 5.5A2 2 0 0 1 6 3.5h13v17H6a2 2 0 0 0-2 2Z"/><path d="M19 16.5H6"/>',
  boxes:
    '<rect x="3" y="9" width="8" height="7" rx="1.5"/><rect x="13" y="9" width="8" height="7" rx="1.5"/><rect x="8" y="2.5" width="8" height="6" rx="1.5"/>',
  history: '<path d="M3.5 12a8.5 8.5 0 1 0 3-6.5"/><path d="M3 4v4h4"/><path d="M12 8v4.5l3 1.8"/>',
  star: '<path d="m12 3.8 2.6 5.3 5.9.8-4.3 4.1 1 5.8-5.2-2.8-5.2 2.8 1-5.8L3.5 9.9l5.9-.8Z"/>',
  key: '<circle cx="8" cy="15.5" r="3.5"/><path d="m10.6 13 7.4-7.4"/><path d="m15.5 8.1 2.4 2.4"/><path d="m18 5.6 2.4 2.4"/>',
  mail: '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m3.8 7 8.2 5.6L20.2 7"/>',
  phone: '<path d="M6.5 3.5h3l1.5 4-2 1.5a10 10 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A15.5 15.5 0 0 1 4.5 5.7 2 2 0 0 1 6.5 3.5Z"/>',
  help: '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3A2.5 2.5 0 0 1 14.5 10c0 1.7-2.5 2-2.5 3.5"/><path d="M12 17h.01"/>',
  menu: '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
  filter: '<path d="M4 5.5h16"/><path d="M7 12h10"/><path d="M10 18.5h4"/>',
  pin: '<path d="M12 21s6.5-6 6.5-10.5a6.5 6.5 0 1 0-13 0C5.5 15 12 21 12 21Z"/><circle cx="12" cy="10.5" r="2.4"/>',
  eye: '<path d="M2.5 12S6 6 12 6s9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="3"/>',
  edit: '<path d="M4 20h4L20 8l-4-4L4 16Z"/><path d="m14.5 5.5 4 4"/>',
  trash: '<path d="M4.5 7h15"/><path d="M9 7V4.5h6V7"/><path d="M6.5 7v12.5a1.5 1.5 0 0 0 1.5 1.5h8a1.5 1.5 0 0 0 1.5-1.5V7"/><path d="M10 11v6"/><path d="M14 11v6"/>',
  hourglass: '<path d="M7 3h10"/><path d="M7 21h10"/><path d="M7 3c0 4 5 5 5 9s-5 5-5 9"/><path d="M17 3c0 4-5 5-5 9s5 5 5 9"/>',
  handshake:
    '<path d="m3 12 4-3.5 4 3 2-1.5 4 3"/><path d="M3 12v3.5l5 4 3-2 3 2 5-4V12"/><path d="m13 10 4-3 4 3.5"/>',
  sparkles: '<path d="m12 4 1.4 3.6L17 9l-3.6 1.4L12 14l-1.4-3.6L7 9l3.6-1.4Z"/><path d="m18 15 .8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8Z"/><path d="M5.5 15.5 6 17l1.5.5L6 18l-.5 1.5L5 18l-1.5-.5L5 17Z"/>',
};

export const ICONS = { ...LOCAL, ...GENERATED_ICONS, ...TOOL_ICONS };

/** Names offered in the icon picker, equipment first. */
export const ICON_NAMES = [
  ...new Set([...Object.keys(TOOL_ICONS), ...Object.keys(GENERATED_ICONS), ...Object.keys(LOCAL)]),
];

function resolve(name) {
  const key = String(name || '').trim().toLowerCase();

  return ICONS[key] || ICONS[ALIASES[key]] || null;
}

export function iconPath(name) {
  return resolve(name) || ICONS.package;
}

export function hasIcon(name) {
  return Boolean(resolve(name));
}

/**
 * Best-guess icon name for a free-text label such as "Gas Pressure Washer",
 * used when a category or tool type has no icon chosen yet.
 */
export function guessIcon(text, fallback = 'package') {
  const haystack = String(text || '').trim().toLowerCase();

  if (!haystack) return fallback;
  if (hasIcon(haystack)) return haystack;

  const match = KEYWORDS.find(([word]) => haystack.includes(word));

  return match ? match[1] : fallback;
}

/** Icon for a category or tool type: the chosen one, else a guess from its name. */
export function iconFor(record, fallback = 'package') {
  if (!record) return fallback;
  if (record.icon && hasIcon(record.icon)) return record.icon;

  return guessIcon(record.name, fallback);
}

/** Icon for a single unit: its tool type first, then the group it belongs to. */
export function iconForItem(item, fallback = 'package') {
  const toolType = item?.tool_type || item?.toolType;

  return iconFor(toolType, iconFor(toolType?.category, fallback));
}

/** Picker search: matches the icon name and the words that map onto it. */
export function searchIcons(term) {
  const query = String(term || '').trim().toLowerCase();

  if (!query) return ICON_NAMES;

  const viaKeyword = new Set(
    KEYWORDS.filter(([word]) => word.includes(query)).map(([, icon]) => icon)
  );
  const viaAlias = new Set(
    Object.entries(ALIASES)
      .filter(([alias]) => alias.includes(query))
      .map(([, icon]) => icon)
  );

  return ICON_NAMES.filter(
    (name) => name.includes(query) || viaKeyword.has(name) || viaAlias.has(name)
  );
}
