# Revert checkpoint — before ui.search-bar@0.1.0

Created: 2026-08-06

## Git
Tag after this snapshot if desired: `pre-ui.search-bar@0.1.0`

Restore:
```bash
git checkout HEAD -- resources/js/components/AppLayout.vue resources/js/views/SearchView.vue app/Http/Controllers/Api/ItemController.php routes/api.php
# plus remove CommandPalette.vue / SearchController if added
```

File copies under this folder.
