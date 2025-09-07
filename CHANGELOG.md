**CHANGELOG.md** (top):

```markdown
## [v2.1.1] - 2025-09-07

### Added
- Endpoint-aware actions in Chat: "Open endpoint" and "Try it with this data" when replies include `### METHOD /path`.
- Attachment capability gating: hide attachments UI for unsupported providers; disable images when model lacks vision.
- Export conversation as plain text, Markdown, or JSON.
- Configurable system prompt via Markdown file (env `IDOC_CHAT_SYSTEM_PROMPT` or `idoc.chat.system_prompt_md`). If you publish the default prompt (`idoc-prompts`), iDoc will automatically read the published file at `resources/vendor/idoc/prompts/chat-system.md` when present.

### Changed
- Headings are not escaped; assistant headings render as Markdown.
- Enhanced system prompt to encourage endpoint headings and JSON examples.

### Fixed
- Removed em dashes from user-facing strings.

---

## [v2.1.0] - 2025-09-07

### Added
- Optional AI Chat assistant integrated into the documentation view.
  - Provider‑agnostic backend (configurable via `idoc.chat.*`): DeepSeek, OpenAI (ChatGPT), Google Gemini, Groq, Hugging Face Inference API, Together AI, and OpenAI‑compatible local servers (LM Studio, llama.cpp server).
  - Provider chooser UI appears when no API key is configured; clicking a provider shows a tailored setup guide with a copyable `.env` snippet.
  - Chat UI enhancements: Markdown rendering with syntax highlighting, Copy action on assistant replies, event‑delegated buttons remain interactive after theme changes, stable floating action stack.
  - Request Tester panel inside Chat: make HTTP calls to your routes, automatically merge Authorization from Swagger Authorize and your Extra headers, and render formatted responses with status and timing.
- Dual highlight.js themes (light/dark) and automatic switching based on the current theme.

### Changed
- Light theme styling for chat bubbles, buttons and code blocks for better contrast and readability.
- Floating controls (Theme / Chat / Try it) consolidated into a single fixed stack so layout stays stable when features are disabled.

### Migration Notes
- Views: `php artisan vendor:publish --tag=idoc-views --force` to publish the updated `resources/views/vendor/idoc/documentation.blade.php` if you previously published and customized it.
- Config: `php artisan vendor:publish --tag=idoc-config` (optional) to review the new `idoc.chat.*` keys. Common `.env` examples:
  - DeepSeek (default)
    ```env
    IDOC_CHAT_ENABLED=true
    IDOC_CHAT_PROVIDER=deepseek
    IDOC_CHAT_MODEL=deepseek-chat
    IDOC_CHAT_BASE_URL=https://api.deepseek.com/v1
    DEEPSEEK_API_KEY=your_deepseek_key
    ```
  - OpenAI (ChatGPT): set `OPENAI_API_KEY` and `IDOC_CHAT_PROVIDER=openai`.
  - Google Gemini: set `GOOGLE_API_KEY` and `IDOC_CHAT_PROVIDER=google`.
  - Groq: set `GROQ_API_KEY` and `IDOC_CHAT_PROVIDER=groq`.
  - HF Inference API: set `HF_API_TOKEN` and `IDOC_CHAT_PROVIDER=huggingface`.
- Disable Chat if not needed: `IDOC_CHAT_ENABLED=false`.
- Clear caches after changes: `php artisan config:clear && php artisan cache:clear`.

```

**CHANGELOG.md** (top):

```markdown
## [v2.0.0] - 2025-08-31

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
