## [v1.8.0] - 2025-08-31

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

### Highlights
- Redoc for reading, Swagger UI for testing. Together in one page.
- The console follows your navigation. Clicks and scrolls update the context.
- One toggle button. No Redocly Pro needed.

### How to enable
- Replace `resources/views/idoc/documentation.blade.php` with the new hybrid file.
- Optionally set `IDOC_TRYIT_ENABLED=true`.

### Known limits
- CORS must be allowed by your API.
- Anchor selectors assume Redoc generates ids like `tag/...` and `operation/...`. Adjust in the view if your build differs.


## [v1.7.0] - 2024-07-23

### Added

- **@responseResource Annotation**: Easily document complex response structures using Laravel API Resources.
- **Custom Configuration Generator**: Generate API documentation using custom configuration files with the `idoc:custom` command.
- **Multiple API Documentation Sets**: Manage and generate multiple sets of API documentation for different applications within the same Laravel application.
- **Example Responses**: Provide example responses for routes using `@response`, `@responseFile`, and `@responseResource` annotations.
- **Transformer Support**: Define transformers for the result of routes using `@transformer`, `@transformerCollection`, and `@transformerModel` annotations.

### Changed

- **Improved Documentation**: Enhanced and reorganized the README.md file for better readability and presentation.
- **Configuration Options**: Added new configuration options for servers, external descriptions, language tabs, and security schemes.

### Fixed

- **Bug Fixes**: Various bug fixes and performance improvements.

### Removed

- **Deprecated Methods**: Removed deprecated methods and annotations to streamline the documentation process.

### Documentation

- **Extensive Documentation**: Added detailed documentation for new features and configuration options.
- **Examples**: Included examples for using new annotations and configuration settings.

### Migration Notes

- **Configuration Update**: Ensure to update your `config/idoc.php` file with the new configuration options.
- **Custom Routes**: Define custom routes in your `routes/web.php` to serve the generated documentation for each custom configuration.

### Contributors

- **Special Thanks**: A big thank you to all the contributors who helped make this release possible.

### How to Upgrade

1. **Update Package**: Run `composer update ovac/idoc` to get the latest version.
2. **Publish Config**: If you haven't already, publish the configuration file using `php artisan vendor:publish --tag=idoc-config`.
3. **Update Config**: Update your `config/idoc.php` file with the new configuration options.
4. **Generate Documentation**: Run `php artisan idoc:generate` to generate the updated documentation.
