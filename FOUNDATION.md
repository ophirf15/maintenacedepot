# Foundation participation

This app is bound to the Foundation control plane via `foundation.project.yaml`.

## Operator commands (in Cursor Agent)

Type `/` and choose a Foundation workflow skill:

| Skill | Purpose |
| --- | --- |
| `/foundation-initialize` | Classify project and create manifest |
| `/foundation-plan` | Phased applicability plan (no edits until approved) |
| `/foundation-audit` | Standards/module audit |
| `/foundation-apply` | Apply a pinned module version with divergence checks |
| `/foundation-validate` | Run validations and record evidence |
| `/foundation-harvest` | Propose module revision from local improvements |
| `/foundation-add-requirement` | Capture a requirement proposal |
| `/foundation-whats-new` | Standards/modules changed since last review |
| `/foundation-status` | Manifest, divergence, validation summary |

Skills live in the Foundation repository under `.cursor/skills/`. For slash commands in **every** Cursor project, from the Foundation clone run `npm run skills:link` once (links into `%USERPROFILE%\.cursor\skills`), then reload Cursor. Otherwise open Foundation as a multi-root workspace, or use MCP tools without the slash menu.

## Act on validation findings

After `/foundation-validate` (or a dashboard/MCP validate), Foundation writes into this repo:

| File | Purpose |
| --- | --- |
| `.foundation/FINDINGS.md` | Cursor briefing — open findings, remediation order, what the agent can fix |
| `.foundation/findings.yaml` | Same data, machine-readable |
| `.foundation/FLAGS.md` | High/critical interrupts only |

In this workspace ask: **"review Foundation findings and propose fixes"**.

## CLI fallback (MCP unavailable)

From the Foundation clone:

```bash
npm run cli -- validate-manifest <path-to-app>/foundation.project.yaml
npm run cli -- register-project <path-to-app>/foundation.project.yaml
npm run cli -- list-modules
npm run cli -- standards-changes <path-to-app>/foundation.project.yaml
```

You own Git. Foundation never commits for you.
