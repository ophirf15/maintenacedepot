# Revert checkpoint — before ui.tenant-shell@0.2.0

Created: 2026-08-06

## Git

- Branch: `pre-ui.tenant-shell-0.2.0` → `25bd0f8841541a96c7a85de98d8245111dff1eae`
- Tag: `pre-ui.tenant-shell@0.2.0`

Restore working tree to this checkpoint:

```bash
git checkout pre-ui.tenant-shell@0.2.0 -- resources/js/components/AppLayout.vue resources/js/components/MobileNavSheet.vue resources/js/components/PageHeader.vue resources/js/components/EmptyState.vue resources/js/components/StatTile.vue resources/css/app.css resources/views/app.blade.php
```

Or reset the whole tree (destructive):

```bash
git reset --hard pre-ui.tenant-shell@0.2.0
```

## File copies

Mirrored under `.foundation/snapshots/pre-ui.tenant-shell-0.2.0/` (path separators → `__`).

## Apply intent

`ui.tenant-shell@0.2.0` adapted lightly for Laravel/Vue — keep Maintenance Depot brand/UI; borrow shell composition cues only (rounded main panel, card elevation, page header wash). Not a Next.js file drop.
