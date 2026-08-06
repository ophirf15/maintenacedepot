# Foundation flags — maintenance-depot

Foundation writes this file after a validation run. It is a to-do list for this repository's own agent, not canon.

Generated: 2026-08-06T06:06:44.384Z
Open flags: 2 of 5 total

Ask your agent: **"review Foundation findings and propose fixes"**.

Also read `.foundation/FINDINGS.md` for the full open-findings briefing.

## HIGH — foundation.data: Schema/migration artifacts present (database/migrations) but migrate/rollback was not executed

- Flag: `flag.maintenance-depot.data.migrations.existence-not-pass`
- Finding: `find.data.migrations.existence-not-pass`
- Run: `run.validate-1785996404093`
- Status: open

**What was found.** Schema/migration artifacts present (database/migrations) but migrate/rollback was not executed

**Suggested fix.** Execute migrate on a disposable DB and document rollback; file presence is not a pass

## HIGH — Updater tests overlay files into the real application tree instead of a sandbox

- Flag: `flag.maintenance-depot.updater-tests-overlay-files-into-the-real-application-tree-ins`
- Status: open

**What was found.** UpdaterFlattenedDeployTest applies a real update package against base_path()/public_path(), so the suite rewrites public/index.php, public/build/**, and base_path('build/**') in the working copy. Cleanup restores public/build/manifest.json and removes base_path('build'), but nothing restores public/index.php or files the overlay adds under public/build/assets. A Foundation intake run on 2026-08-05 found public/index.php replaced with package content after `php artisan test`, which had to be recovered with `git checkout`.

**Why it matters.** A test suite that mutates the deployed front controller can leave a developer or CI checkout in a broken, silently-wrong state; if the same tree is ever deployed or committed, the app boots from package paths that do not exist on shared hosting.

**Suggested fix.** Point the updater at an isolated temp root in tests (inject the deploy root, or copy the relevant public/ and build/ files into a per-test directory), and assert the working tree is clean after the suite runs.

**Where.**

| File | Line | Rule | Excerpt |
| --- | --- | --- | --- |
| `tests/Feature/UpdaterFlattenedDeployTest.php` | 60 | test_writes_to_app_root | $result = app(UpdaterService::class)->applyUpdate(); |
| `tests/Feature/UpdaterFlattenedDeployTest.php` | 66 | test_writes_to_app_root | $this->assertFileExists(base_path('public/build/manifest.json')); |
| `tests/Feature/UpdaterFlattenedDeployTest.php` | 68 | incomplete_cleanup | } finally { |
| `public/index.php` |  | clobbered_artifact |  |

---

When a flag is fixed, tell Foundation (`foundation flag-resolve <id> --note "…"`) or re-run validation; flags no longer reproduced are closed automatically.
