**CHANGELOG.md** (top):

```markdown
## [v2.0.0] — 2025-08-31

### Breaking
- All prior versions are non-functional due to the discontinued Redoc CDN.
  The docs view now ships with a supported Redoc bundle and a new hybrid renderer.
  Action required: publish the new view and redeploy.

### Added
- Hybrid documentation view: Redoc OSS for reading + a slide-in Swagger UI “Try it” panel.
- Context awareness: console follows `#/tag/...` and `#/tag/.../operation/...` and updates on scroll.
- Extra request headers (JSON) input in the panel header, persisted and auto-injected into requests.
- Click-to-copy and Download buttons for Swagger responses.
- Extensive inline documentation in the Blade view for maintainers.

### Changed
- Reduced Swagger UI title size for a tighter header.
- Updated default Markdown docs to document the hybrid model and quick start.
- Safer fetch normalization adds `Accept: application/json` to `/api` calls when missing.

### Fixed
- Redoc CDN 404 errors (legacy CDN removed). Now using `redoc@next/bundles/redoc.standalone.js`.

### Migration Notes
- Run `composer update ovac/idoc`.
- `php artisan vendor:publish --tag=idoc-views --force` to replace `documentation.blade.php`.
- (If needed) `php artisan vendor:publish --tag=idoc-config` and set:
  - `IDOC_TRYIT_ENABLED=true`
  - `IDOC_TITLE="API Reference"`
- Ensure your spec is at `public/docs/openapi.json` or update `config('idoc.output')`.
- Clear caches: `php artisan view:clear && php artisan cache:clear`.

[Full Changelog]: v1.7.0...v2.0.0