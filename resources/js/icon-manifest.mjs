/**
 * Curated icon list for the depot catalogue.
 *
 * Left side is the name we use in the app and store in the database
 * (categories.icon, tool_types.icon). Right side is "pack:icon-name" from an
 * open icon set:
 *   tabler  — MIT (https://tabler.io/icons)
 *   lucide  — ISC (https://lucide.dev)
 *
 * Run `npm run icons` after editing to regenerate resources/js/icons.generated.js.
 * Nothing is fetched at runtime; the geometry is inlined so the app stays offline-safe.
 */
export const ICON_MANIFEST = {
  // Lawn and garden.
  // Mowers, trimmers, saws and blowers are drawn in icons.tools.js — the packs
  // only offer stand-ins (an axe for a chainsaw, a razor blade for a trimmer).
  tractor: 'tabler:tractor',
  axe: 'tabler:axe',
  shovel: 'tabler:shovel',
  pitchfork: 'tabler:shovel-pitchforks',
  pickaxe: 'lucide:pickaxe',
  plant: 'tabler:plant',
  sprout: 'lucide:sprout',
  leaf: 'tabler:leaf',
  tree: 'tabler:tree',
  trees: 'tabler:trees',
  flower: 'tabler:flower',
  fence: 'tabler:fence',
  hedge: 'tabler:plant-2',

  // Cleaning
  vacuum: 'tabler:vacuum-cleaner',
  bucket: 'tabler:bucket',
  'spray-can': 'lucide:spray-can',
  brush: 'tabler:brush',
  paint: 'tabler:paint',
  wash: 'tabler:wash',

  // Hand and power tools
  hammer: 'tabler:hammer',
  'hammer-drill': 'tabler:hammer-drill',
  wrench: 'lucide:wrench',
  toolbox: 'lucide:toolbox',
  'tool-case': 'lucide:tool-case',
  tools: 'tabler:tools',
  scissors: 'tabler:scissors',
  blade: 'tabler:blade',
  knife: 'lucide:pocket-knife',
  ruler: 'tabler:ruler',
  'tape-measure': 'tabler:ruler-measure',
  level: 'lucide:ruler-dimension-line',
  nut: 'lucide:nut',
  bolt: 'lucide:bolt',
  key: 'tabler:key',
  lock: 'tabler:lock',

  // Access and safety
  ladder: 'tabler:ladder',
  'aerial-lift': 'tabler:aerial-lift',
  'hard-hat': 'lucide:hard-hat',
  helmet: 'tabler:helmet',
  construction: 'lucide:construction',
  'first-aid': 'tabler:first-aid-kit',
  'face-mask': 'tabler:face-mask',
  glasses: 'lucide:glasses',
  crane: 'tabler:crane',
  forklift: 'tabler:forklift',

  // Power, fuel and climate
  engine: 'tabler:engine',
  'gas-station': 'tabler:gas-station',
  battery: 'tabler:battery',
  plug: 'tabler:plug',
  power: 'tabler:power',
  bulb: 'tabler:bulb',
  flashlight: 'lucide:flashlight',
  flame: 'tabler:flame',
  fan: 'lucide:fan',
  'air-conditioning': 'tabler:air-conditioning',
  snowflake: 'tabler:snowflake',
  temperature: 'tabler:temperature',
  sun: 'tabler:sun',
  wind: 'tabler:wind',

  // Moving and storage
  truck: 'tabler:truck',
  car: 'tabler:car',
  trailer: 'lucide:caravan',
  container: 'tabler:container',
  barrel: 'tabler:barrel',
  tank: 'tabler:tank',
  'garden-cart': 'tabler:garden-cart',

  // Building and site
  door: 'tabler:door',
  window: 'tabler:window',
  wall: 'lucide:brick-wall',
  sofa: 'tabler:sofa',
  bed: 'tabler:bed',
  shirt: 'tabler:shirt',
  mountain: 'tabler:mountain',
  recycle: 'tabler:recycle',
  stack: 'tabler:stack',

  // Paperwork
  scale: 'tabler:scale',
  certificate: 'tabler:certificate',
  'clipboard-check': 'tabler:clipboard-check',
  barcode: 'tabler:barcode',
  tag: 'tabler:tag',
};
