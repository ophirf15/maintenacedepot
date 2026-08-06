# ui.tenant-shell@0.2.0 — adapted apply (2026-08-06)

## Applied (light)
- AppLayout: ink-950 outer shell; main column `md:rounded-l-2xl` + soft left shadow + border
- app.css: `shadow-card`, `header-wash` (brand orange), `fade-in` / `fade-in-up`, row-hover; card elevation
- PageHeader: brand gradient icon well + fade-in-up
- EmptyState: dashed border chrome + brand icon well
- StatTile: header-wash + shadow-card
- Nav: kept pill shape; subtle inset ring on active

## Skipped (preserve current UI)
- Hover-collapse sidebar 76→268
- Next.js / Shadcn component drop
- Purple `--primary` tokens
- Workspace switcher / blurred glass topbar rewrite
- Mobile nav sheet rewrite

## Revert
See `.foundation/snapshots/pre-ui.tenant-shell-0.2.0/README.md`
Tag: `pre-ui.tenant-shell@0.2.0`
