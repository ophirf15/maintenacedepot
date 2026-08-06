# ui.search-bar@0.1.0 — adapted apply (2026-08-06)

## Applied
- Vue `CommandPalette` (Ctrl/Cmd+K + topbar trigger) — no cmdk/Radix drop-in
- Authz-gated `GET /api/search` (items, requests, loans, tickets; permission-scoped)
- Item `q` also matches tool type name
- Search results page uses `/api/search`
- Topbar search is a palette trigger (fixes “type and nothing happens”)

## Skipped
- Ask AI / Sparkles row
- Shadcn command/dialog / Next.js fonts
- OpenSearch / full indexer
- File Cabinets partitions

## Revert
Tag `pre-ui.search-bar@0.1.0` + `.foundation/snapshots/pre-ui.search-bar-0.1.0/`
