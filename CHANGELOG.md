
---

# 4) Add release notes

Create or update `CHANGELOG.md` and your GitHub Release body.

**CHANGELOG.md** (top):

```markdown
## v2.3.0 — Hybrid Redoc + Try it

### Added
- New hybrid documentation view that keeps Redoc OSS for reading and mounts a Swagger UI console in a slide-in panel.
- Console auto-detects the active tag or operation by hash or scroll.
- Config key `idoc.tryit.enabled` to toggle the panel.

### Changed
- Shrunk Swagger UI title size for a tighter header.

### Fixed
- Safer fetch wrapper to normalize `Accept: application/json` on /api requests without breaking Redoc.

### Notes
- No breaking changes. Existing routes and config continue to work.
- If you self host the JS bundles, update the script tags in the Blade view.
