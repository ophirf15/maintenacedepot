/**
 * Bespoke equipment icons drawn for this app.
 *
 * The open packs (Tabler/Lucide) stop at generic hand tools, so anything a
 * commercial or multifamily maintenance crew actually signs out — chainsaws,
 * jackhammers, carpet extractors, airless sprayers — is drawn here instead of
 * being approximated by a wrong-looking stock glyph.
 *
 * Drawing rules, so the set stays consistent:
 *   - 24x24 box, shapes kept inside 2..22
 *   - stroke only, no fills; Icon.vue supplies stroke width, colour and caps
 *   - silhouette over detail: it has to read at 20px on a phone
 */
export const TOOL_ICONS = {
  // ---------------------------------------------------------------- landscape
  'lawn-mower':
    '<path d="M20 4.2h-2.6l-2.5 7.8"/><rect x="3.4" y="12" width="11.6" height="5" rx="1.2"/><path d="M15 13.2h4.4a1 1 0 0 1 1 1v1.6a1 1 0 0 1-1 1H15"/><circle cx="6.4" cy="19.2" r="1.7"/><circle cx="12.8" cy="19.2" r="1.7"/>',
  'riding-mower':
    '<circle cx="17.4" cy="16.6" r="3.6"/><circle cx="5.8" cy="18.2" r="2.1"/><path d="M3.8 16.2v-2.6a1.2 1.2 0 0 1 1.2-1.2h5l1.6-3.6h2.6v6.6"/><path d="M9.6 12.4V8.6h2.6"/>',
  'zero-turn-mower':
    '<circle cx="17.6" cy="16.8" r="3.4"/><circle cx="6" cy="17.6" r="2.6"/><path d="M3.6 15.4v-2.2a1.2 1.2 0 0 1 1.2-1.2h6.4l1.4-3.4h2.8v6.6"/><path d="M8.6 12V9h2.2M12.4 12V9h2.2"/>',
  'string-trimmer':
    '<rect x="15.4" y="2.6" width="5.8" height="4.6" rx="1.5"/><path d="M15.8 6.9 8.5 14.5"/><path d="M12.9 9.9c-1.9-.6-3.4.5-3.1 2.3"/><ellipse cx="6.5" cy="16.9" rx="3.7" ry="2.2"/><path d="M3.3 19 1.9 20.6"/>',
  'hedge-trimmer':
    '<rect x="13.4" y="7.8" width="7.2" height="5.6" rx="1.6"/><path d="M15 7.8V6.5A1.5 1.5 0 0 1 16.5 5h2"/><path d="M13.4 9.5H4.2a1.1 1.1 0 0 0-1.1 1.1v1.2a1.1 1.1 0 0 0 1.1 1.1h9.2"/><path d="M5.3 12.9v2M7.9 12.9v2M10.5 12.9v2"/>',
  chainsaw:
    '<rect x="12.8" y="7.6" width="7.8" height="6.6" rx="1.8"/><path d="M14.4 7.6V6.2A1.4 1.4 0 0 1 15.8 4.8h2.6"/><path d="M12.8 9.5H5.6a2.5 2.5 0 0 0 0 5h7.2"/><path d="M6.6 8.1v1.4M9.2 8.1v1.4M11.8 8.1v1.4"/>',
  'pole-saw':
    '<path d="M3 21 11.4 12.6"/><path d="M4.8 16.8 6.6 18.6"/><path d="M12.2 11.8c2.6-3.6 5.4-5.4 8.4-5.4-.4 3.6-2.4 6.6-6 9z"/><path d="m13.4 14.2.8-1.6M15.4 15l.8-1.6M17.4 15.4l.8-1.6"/>',
  'leaf-blower':
    '<rect x="2.8" y="7.9" width="8.8" height="6.6" rx="2.2"/><path d="M5.5 14.5v1.8A1.7 1.7 0 0 0 7.2 18h1.7"/><path d="M11.6 9.4h4.9l3-2.2v10.6l-3-2.2h-4.9"/><path d="M21 10.8h1.2M21 14h1.2"/>',
  'backpack-blower':
    '<rect x="4.4" y="3.4" width="8.2" height="11.2" rx="2.5"/><path d="M4.4 6C2.5 7.2 2.5 11 4.1 12.8"/><path d="M12.6 7.4c3.4 0 4.6 2.2 4.6 4.6v3.8"/><path d="M15.2 15.8h3.8l-1.9 4.2z"/>',
  'snow-blower':
    '<rect x="3.2" y="12.4" width="9.6" height="5.2" rx="1.3"/><path d="M9.6 12.4V8.3A2.4 2.4 0 0 1 12 5.9h1.8"/><path d="M15.6 5 17 3.7"/><path d="M12.8 14.8 20 8.8"/><circle cx="5.8" cy="19.4" r="1.6"/><circle cx="10.4" cy="19.4" r="1.6"/>',
  'wood-chipper':
    '<path d="M3.6 4.6h7.8l-2.2 4.2v2.6H5.8V8.8z"/><rect x="8.6" y="10.8" width="10" height="6.2" rx="1.6"/><path d="M18.6 13h2.6"/><circle cx="11.4" cy="19.4" r="1.6"/><circle cx="16.4" cy="19.4" r="1.6"/>',
  spreader:
    '<path d="M5.8 5.4h9.4l-2.6 6.4H8.4z"/><path d="M15.2 5.4 20.6 3.2"/><path d="M10.2 11.8v2.4"/><circle cx="7.4" cy="17" r="2.7"/><circle cx="15.4" cy="17" r="2.7"/>',
  'backpack-sprayer':
    '<rect x="4" y="4" width="7.4" height="12" rx="2.4"/><path d="M4 6.6C2.1 7.7 2.1 11.2 3.7 13"/><path d="M11.4 9h2.8l4.6-2.8"/><path d="M19.8 4.8 21.4 3.6M19.2 3.4l.4-1.6M21.2 6.6l1.5-.4"/>',
  tiller:
    '<rect x="6.2" y="5.8" width="7" height="5" rx="1.5"/><path d="M13.2 7 20.4 3.8"/><circle cx="8.6" cy="15.8" r="3.9"/><path d="M8.6 11.9v7.8M4.7 15.8h7.8M5.8 13 11.4 18.6M11.4 13 5.8 18.6"/>',
  rake: '<path d="M12 3v8.4"/><path d="M6.4 11.4h11.2"/><path d="M7 11.4 5 20.4M9.8 11.4l-1 9M14.2 11.4l1 9M17 11.4l2 9"/>',
  wheelbarrow:
    '<path d="M4.5 7.4h9.8l3.3 6.5H8.4z"/><path d="M14.3 7.4 20.4 5.2"/><path d="M17.2 14 18.6 17"/><path d="M8.6 13.9 8.1 15.8"/><circle cx="7.6" cy="18" r="2.2"/>',
  'lawn-edger':
    '<circle cx="7.4" cy="15.4" r="4.2"/><path d="M7.4 11.2v8.4"/><path d="M11 12.4 19.4 4.2"/><circle cx="16.4" cy="17.4" r="2"/><path d="M18.2 3 21 5.8"/>',

  // ----------------------------------------------------------------- cleaning
  'carpet-extractor':
    '<rect x="6.8" y="5.8" width="8.4" height="11.4" rx="2"/><path d="M11 5.8V3.4M9 3.4h4"/><path d="M6.8 10.8h8.4"/><path d="M15.2 8.8c3 .6 4.2 3 3.6 6.2"/><circle cx="8.8" cy="18.8" r="1.5"/><circle cx="13.2" cy="18.8" r="1.5"/>',
  'floor-scrubber':
    '<path d="M17.4 4 11.2 10.2"/><rect x="6.2" y="9.8" width="8" height="4.8" rx="1.5"/><ellipse cx="10.2" cy="17" rx="6.4" ry="2.6"/><path d="M5.6 18.4v1.4M10.2 19.6v1.4M14.8 18.4v1.4"/>',
  'shop-vacuum':
    '<path d="M5.6 10.2v6.6a2.4 2.4 0 0 0 2.4 2.4h6a2.4 2.4 0 0 0 2.4-2.4v-6.6"/><rect x="4.6" y="7.4" width="12.8" height="2.8" rx="1.4"/><path d="M9 7.4V6.2a2 2 0 0 1 4 0v1.2"/><path d="M17.4 9c2.6.7 3.6 2.9 3 5.5"/>',
  'upright-vacuum':
    '<path d="M12 3.4v6.4M10 3.4h4"/><rect x="8.4" y="9.8" width="7.2" height="5.2" rx="1.6"/><path d="M5.6 19.4h12.8l-1.8-3.6H7.4z"/>',
  'pressure-washer':
    '<rect x="3.4" y="8.2" width="8.2" height="7" rx="1.6"/><path d="M6 8.2V6.6h3.4v1.6"/><circle cx="5.8" cy="17.2" r="1.6"/><circle cx="9.4" cy="17.2" r="1.6"/><path d="M11.6 10.8c2.6 0 3.4-1.4 4.9-2.5"/><path d="M15.9 8.6 20 5.6"/><path d="M20.8 4.6 22.2 3.4M19.9 3.5l.4-1.6M21.6 6.4l1.5-.4"/>',
  'steam-cleaner':
    '<rect x="3.8" y="9.2" width="10.4" height="8.2" rx="2"/><path d="M6.8 6.6c0-1.3 1.6-1.3 1.6-2.6M10.4 6.6c0-1.3 1.6-1.3 1.6-2.6"/><path d="M14.2 12.2c3 0 4.2 1.6 4.2 4.2v2"/><path d="M6.6 13.4h4.8"/>',
  'air-mover':
    '<rect x="4.4" y="6.2" width="13.2" height="10.4" rx="3"/><circle cx="11" cy="11.4" r="3.3"/><path d="M11 8.1v6.6M7.7 11.4h6.6"/><path d="M6.2 16.6v2M15.8 16.6v2"/>',
  dehumidifier:
    '<rect x="5" y="4.4" width="14" height="15.2" rx="2.5"/><path d="M12 8.4s2.5 2.7 2.5 4.4a2.5 2.5 0 0 1-5 0c0-1.7 2.5-4.4 2.5-4.4z"/><path d="M7.4 17.2h3.2"/>',
  'mop-bucket':
    '<path d="M5.2 12.2h10.2l-1.1 7.4H6.3z"/><path d="M6.2 12.2V9.4h8.2v2.8"/><path d="M17.6 3.4 14 12"/><circle cx="7.2" cy="20.6" r="1"/><circle cx="13.4" cy="20.6" r="1"/>',
  squeegee:
    '<path d="M12 3v8.6"/><path d="M5.4 11.6h13.2"/><path d="M7.4 11.6v3.2M16.6 11.6v3.2"/><path d="M6.4 14.8h11.2"/>',
  'drain-snake':
    '<circle cx="8.6" cy="12" r="5.6"/><circle cx="8.6" cy="12" r="1.6"/><path d="M14.2 11c2.6 0 3 3 5.6 3"/><path d="M8.6 6.4V4.2h2.6"/>',

  // -------------------------------------------------------------- power tools
  drill:
    '<rect x="4.8" y="6.4" width="9.4" height="5.6" rx="2.2"/><path d="M14.2 8h2.8l2.2 1.2-2.2 1.3h-2.8"/><path d="M19.2 9.3h2"/><path d="M6.4 12v3.6a1.7 1.7 0 0 0 1.7 1.7h1.8a1.7 1.7 0 0 0 1.7-1.7V12"/><rect x="6.2" y="17.3" width="5.4" height="2.6" rx="1"/>',
  'circular-saw':
    '<circle cx="9.5" cy="10.8" r="5.6"/><circle cx="9.5" cy="10.8" r="1.1"/><path d="M3.4 16.4h12.4"/><rect x="14.4" y="6.4" width="5.8" height="4.8" rx="1.6"/><path d="M16 6.4V5.1a1.4 1.4 0 0 1 1.4-1.4h2.2"/>',
  'reciprocating-saw':
    '<rect x="3.4" y="8.4" width="9.2" height="6" rx="2.2"/><path d="M12.6 9.6h1.6v3.6h-1.6"/><path d="M14.2 11.2h6.4"/><path d="M15.2 11.2v1.3M16.8 11.2v1.3M18.4 11.2v1.3M20 11.2v1.3"/><path d="M4.2 14.4v2.4a1.8 1.8 0 0 0 1.8 1.8h2"/>',
  'miter-saw':
    '<path d="M3 19.4h18"/><rect x="4.6" y="15.6" width="14.8" height="3.2" rx="1"/><path d="M8.4 15.6v-2.2h7.2v2.2"/><circle cx="9.6" cy="9" r="4.6"/><path d="M13.6 6.7 17.6 9.6v6"/><path d="m12.3 5.4 2.4-2.4"/>',
  'table-saw':
    '<rect x="3.2" y="10.4" width="17.6" height="3.2" rx="1.2"/><path d="M8.6 10.4a3.6 3.6 0 0 1 7.2 0"/><path d="M5.8 13.6 4.8 19.4M18.2 13.6l1 5.8"/><path d="M6.2 19.4h11.6"/>',
  jigsaw:
    '<rect x="4.4" y="6.4" width="11" height="6" rx="2"/><path d="M15.4 6.6h2.4a1.6 1.6 0 0 1 1.6 1.6v1a1.6 1.6 0 0 1-1.6 1.6h-2.4"/><path d="M3.8 15.4h9.4"/><path d="M8.6 12.4v5.4"/><path d="M8.6 13.4h1.2M8.6 15h1.2M8.6 16.6h1.2"/>',
  'concrete-saw':
    '<circle cx="8" cy="12" r="6"/><circle cx="8" cy="12" r="1.2"/><rect x="13.6" y="8.4" width="7" height="6.6" rx="2"/><path d="M15.2 8.4V7a1.4 1.4 0 0 1 1.4-1.4h3"/>',
  'angle-grinder':
    '<rect x="7.4" y="8.8" width="10.2" height="5.4" rx="2.2"/><circle cx="5.2" cy="11.5" r="3.6"/><path d="M7.4 11.5H9"/><path d="M9.4 8.8V7.4A1.4 1.4 0 0 1 10.8 6h1.8"/>',
  sander:
    '<rect x="5.8" y="8.4" width="12.4" height="4.8" rx="2.2"/><path d="M8.6 8.4V7a3.4 3.4 0 0 1 6.8 0v1.4"/><rect x="5.2" y="13.4" width="13.6" height="2.6" rx="1"/><path d="M7.4 17.4v1.4M11.6 17.4v1.4M15.8 17.4v1.4"/>',
  'pole-sander':
    '<circle cx="7.4" cy="16.2" r="4"/><path d="M10.4 13.4 19 4.8"/><path d="M17.4 3.2 21 6.8"/>',
  'nail-gun':
    '<rect x="6" y="6.2" width="9.6" height="4.6" rx="1.6"/><path d="M11.8 10.8v4.2h3v-4.2"/><path d="M6.6 10.8 3.6 17.4h3.6l2.2-4.6"/><path d="M15.6 8.4h3.2"/>',
  'heat-gun':
    '<rect x="3.8" y="7.4" width="9.2" height="5.2" rx="2"/><path d="M13 8.6h4.4v2.8H13"/><path d="M6 12.6v3.4a1.8 1.8 0 0 0 1.8 1.8h1.6"/><path d="M18.8 8.6c1 .9 1 2 0 2.9M20.6 7.4c1.6 1.5 1.6 3.7 0 5.2"/>',
  'rotary-tool':
    '<rect x="2.8" y="9" width="12.4" height="5.4" rx="2.7"/><path d="M5.2 9.4v4.6M7.2 9.4v4.6M9.2 9.4v4.6"/><path d="M15.2 10.2h2.2l1.6 1.5-1.6 1.5h-2.2"/><path d="M19 11.7h2.6"/>',

  // ------------------------------------------------------ demolition/concrete
  jackhammer:
    '<rect x="8.6" y="4.6" width="6.8" height="9.4" rx="2"/><path d="M8.6 6.4H6.2a1.6 1.6 0 0 0 0 3.2h2.4M15.4 6.4h2.4a1.6 1.6 0 0 1 0 3.2h-2.4"/><path d="M12 14v2.8"/><path d="M10.4 16.8h3.2L12 21z"/>',
  'concrete-mixer':
    '<ellipse cx="10.4" cy="9.6" rx="6" ry="5"/><path d="M14.6 5.9 19.4 4"/><path d="M6.4 14 4.6 19.6M15 14l1.8 5.6"/><path d="M4.2 19.6h13"/><circle cx="18.6" cy="18.2" r="1.6"/>',
  'plate-compactor':
    '<rect x="6.8" y="5.6" width="8" height="5.2" rx="1.6"/><path d="M14.8 7.2 20.4 4"/><path d="M4.4 15.6h15.2l-1.6-3.6H6z"/><path d="M5.6 18.4h1.6M10 18.4h1.6M14.4 18.4H16M18.8 18.4h1.2"/>',
  sledgehammer:
    '<path d="M13.4 6.2 16.2 3.4l4.4 4.4-2.8 2.8z"/><path d="M14.6 9.4 5 19"/><path d="M6.6 16.2 8 17.6"/>',

  // ----------------------------------------------------------------- painting
  'paint-sprayer':
    '<rect x="3.4" y="8.8" width="7.2" height="7" rx="1.6"/><circle cx="5.6" cy="18" r="1.5"/><circle cx="9" cy="18" r="1.5"/><path d="M10.6 11.4c2.5 0 3-1.5 4.6-2.4"/><path d="M15.2 9h3.4"/><path d="M16.2 9.4v2.2"/><path d="M19.6 7.6 21 6.4M19.4 9h1.8M19.6 10.6 21 11.8"/>',
  'paint-roller':
    '<rect x="3.8" y="4.6" width="11.2" height="4.6" rx="1.4"/><path d="M9.4 9.2v2.6H7.6v2.2"/><rect x="6.3" y="14" width="2.6" height="5.6" rx="1.2"/>',
  'paint-brush':
    '<path d="M9.4 13.2V4.8a1.7 1.7 0 0 1 3.4 0v8.4"/><rect x="8.4" y="13.2" width="5.4" height="2.3" rx="0.7"/><path d="M8.8 15.5h4.6l-.8 4.7H9.6z"/>',
  'paint-bucket':
    '<path d="M5.4 8h11.2l-1.2 10.6a1 1 0 0 1-1 .9H7.6a1 1 0 0 1-1-.9z"/><path d="M7 8V6.6a4 4 0 0 1 8 0V8"/><path d="M9 12h4"/>',
  'caulk-gun':
    '<rect x="3.8" y="8.8" width="11" height="4.4" rx="1"/><path d="M14.8 10.2 19.4 11l-4.6.8z"/><path d="M3.8 13.2v2.2h8.6"/><path d="M11 15.4v2.8"/><path d="M3.8 11H1.8"/>',

  // ------------------------------------------------------------ power and gas
  generator:
    '<rect x="3.4" y="8" width="17.2" height="8.6" rx="2"/><path d="M7 8V6.4h10V8"/><circle cx="8" cy="12.2" r="1.3"/><circle cx="12" cy="12.2" r="1.3"/><path d="M15.6 10.8h3M15.6 13.6h3"/><circle cx="7" cy="18.2" r="1.6"/><circle cx="17" cy="18.2" r="1.6"/>',
  'gas-can':
    '<path d="M5 8h9a1.5 1.5 0 0 1 1.5 1.5v9a1.5 1.5 0 0 1-1.5 1.5H6.5A1.5 1.5 0 0 1 5 18.5z"/><path d="M15.5 11h2.9l1.8-2.4"/><path d="M7 8V6.4h5V8"/><path d="M8 12.6h4"/>',
  'propane-tank':
    '<path d="M6.6 9.6a5.4 5.4 0 0 1 10.8 0v7.8a2.4 2.4 0 0 1-2.4 2.4H9a2.4 2.4 0 0 1-2.4-2.4z"/><path d="M9.4 6.6V4.4h5.2v2.2"/><path d="M12 4.4V2.8"/>',
  'air-compressor':
    '<rect x="3.4" y="11" width="14.2" height="6.2" rx="3.1"/><rect x="6.8" y="6.4" width="6.2" height="4.6" rx="1.5"/><circle cx="16.6" cy="8.4" r="2.1"/><path d="M6 17.2v2.4M15 17.2v2.4"/><path d="M17.6 14h2.8"/>',
  welder:
    '<rect x="3.4" y="7.4" width="10.2" height="8.2" rx="2"/><circle cx="8.5" cy="11.5" r="1.7"/><path d="M13.6 10c3 0 4 2 6 2.6"/><path d="M19.2 12.8 21.6 14.4"/><path d="m17.8 16.4 1.6 1.6M20.4 15.8l.6 2.2"/>',
  'extension-cord':
    '<rect x="3.4" y="9.4" width="4.2" height="5.2" rx="1.2"/><path d="M3.4 11H1.8M3.4 13H1.8"/><path d="M7.6 12c3 0 3-4.2 6-4.2s3 8.4 6 8.4"/><rect x="18.4" y="13.6" width="4.2" height="5.2" rx="1.2"/><path d="M20 15.4v1.6"/>',
  'work-light':
    '<rect x="5.4" y="4.4" width="13.2" height="5.2" rx="1.6"/><path d="M7 11.6v1.6M12 11.6v1.6M17 11.6v1.6"/><path d="M12 9.6v5.8"/><path d="M12 15.4 7.4 20.2M12 15.4l4.6 4.8M12 15.4v4.8"/>',
  'tool-battery':
    '<path d="M6.8 8h10.4v9a2.4 2.4 0 0 1-2.4 2.4H9.2A2.4 2.4 0 0 1 6.8 17z"/><path d="M9 8V5.8h6V8"/><path d="M9.4 11.2h5.2"/><path d="M12.6 13 11 15.4h2.2L11.6 18"/>',
  'battery-charger':
    '<rect x="3.6" y="12.6" width="16.8" height="6.4" rx="2"/><path d="M9 12.6V8.4h6v4.2"/><path d="M12.6 4.6 11 7.4h2.2l-1.6 3"/><path d="M6.4 15.8h1.6M16 15.8h1.6"/>',
  multimeter:
    '<rect x="4.8" y="4.4" width="11.2" height="15.2" rx="2"/><rect x="6.8" y="6.4" width="7.2" height="3.6" rx="0.8"/><circle cx="10.4" cy="14.6" r="2.7"/><path d="M10.4 14.6v-2.1"/><path d="M16 8.4c3 1 4.2 4 3.6 7"/>',

  // ----------------------------------------------------------------- plumbing
  'pipe-wrench':
    '<path d="M4.4 19.6 12 12"/><path d="M11.6 11.6 14.4 8.8a3.4 3.4 0 1 1 4.8 4.8l-2.8 2.8z"/><path d="M13.8 13.4h4"/>',
  plunger:
    '<path d="M12 3.4v9"/><path d="M6.6 12.4h10.8l-1.4 5.4a2.4 2.4 0 0 1-2.3 1.8h-3.4a2.4 2.4 0 0 1-2.3-1.8z"/>',
  'water-pump':
    '<circle cx="9.8" cy="11.8" r="5"/><circle cx="9.8" cy="11.8" r="1.4"/><path d="M9.8 6.8V4.2h3.4"/><path d="M14.8 11.8h4.4"/><path d="M5.6 18.4h8.4"/>',
  torch:
    '<rect x="7" y="9" width="6" height="10.4" rx="2"/><path d="M10 9V6.6h2.6"/><path d="M15.2 6.2s2.6 1.8 2.6 3.4a2.6 2.6 0 0 1-5.2 0c0-1.6 2.6-3.4 2.6-3.4z"/>',

  // ------------------------------------------------------------------- access
  'step-ladder':
    '<path d="M8 20.4 10.4 3.6M16 20.4 13.6 3.6"/><path d="M10.4 3.6h3.2"/><path d="M9.6 9.2h4.8M9.2 13h5.6M8.8 16.8h6.4"/>',
  'extension-ladder':
    '<path d="M7 3.4 5 20.6M17 3.4l2 17.2"/><path d="M6.6 7.4h10.8M6.2 11.4h11.6M5.8 15.4h12.4"/>',
  scaffold:
    '<path d="M4.6 6.2v12.8M19.4 6.2v12.8"/><path d="M4.6 6.2h14.8"/><path d="M4.6 6.2 19.4 12.6M19.4 6.2 4.6 12.6"/><path d="M3.4 12.6h17.2"/>',
  'scissor-lift':
    '<path d="M4.6 4h14.8"/><path d="M6.6 6 17.4 11.6M17.4 6 6.6 11.6"/><path d="M6.6 11.6 17.4 17M17.4 11.6 6.6 17"/><path d="M5.2 17.4h13.6"/><circle cx="7.6" cy="19.6" r="1.4"/><circle cx="16.4" cy="19.6" r="1.4"/>',
  'hand-truck':
    '<path d="M7 4.2v13.2"/><path d="M7 4.2h2.4"/><path d="M7 17.4h6.4"/><rect x="9.4" y="7" width="8.2" height="7.2" rx="1.2"/><circle cx="8.2" cy="19.4" r="1.9"/>',
  'pallet-jack':
    '<path d="M3.6 14.6h12.2M3.6 17.8h12.2"/><path d="M15.8 15 19.4 7.4"/><path d="M17.8 6h3.6"/><circle cx="6" cy="19.8" r="1.2"/><circle cx="13.6" cy="19.8" r="1.2"/>',

  // ------------------------------------------------------------------ measure
  'laser-level':
    '<rect x="6.4" y="8" width="11.2" height="8" rx="2"/><rect x="9" y="10.8" width="6" height="2.4" rx="1.2"/><path d="M12 8V4.4M12 16v3.6M6.4 12H2.8M17.6 12h3.6"/>',
  'tool-belt':
    '<path d="M2.8 8.4h18.4v3.4H2.8z"/><path d="M6 11.8h4.2v6H6zM13.8 11.8h4.2v5.2h-4.2"/><path d="M10.6 8.4v3.4M13.4 8.4v3.4"/>',
};
