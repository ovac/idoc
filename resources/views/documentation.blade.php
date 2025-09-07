<!DOCTYPE html>
<!--
  =============================================================================
  Laravel iDoc — Hybrid API Docs View
  =============================================================================

  PURPOSE
  -------
  This Blade view renders your OpenAPI 3.x specification using:
    1) Redoc OSS for a beautiful, fast, read-focused reference.
    2) A slide-in panel that mounts Swagger UI for "Try it" requests.
    3) An optional AI Chat assistant (ChatGPT‑style) that answers questions
       about your API using your generated OpenAPI and optional extra context.

  UX MODEL
  --------
  - Users read the docs in Redoc as usual.
  - A floating action stack (bottom‑right) contains Theme / Chat / Try it.
    • Chat opens a right-hand panel with a conversational assistant.
    • Try it opens a right-hand panel with Swagger UI.
  - The console is context-aware:
      • If a Redoc click changes the URL hash, we follow that (e.g. #/tag/Auth or
        #/tag/Auth/operation/Login).
      • If the hash does not change during scroll, we infer the active section by
        looking at visible Redoc headings (id="tag/..." or "operation/...").
  - Chat notes:
      • If no API key is configured, the assistant shows a clickable provider
        chooser (DeepSeek, OpenAI, Google Gemini, Groq, Hugging Face, Together,
        Free/Open‑Source self‑hosted). Clicking a provider displays a tailored
        setup guide with a copyable .env snippet.
      • Chat bubbles are Markdown‑aware with syntax highlighting; Copy buttons
        are available on assistant messages; buttons remain interactive across
        theme changes.

  NOTE ON REDOCLY PRO
  -------------------
  This view does not require Redocly Pro. Redoc's open-source bundle does not
  include a console; that is why we embed Swagger UI to provide "Try it".

  CONFIG OVERVIEW (see config/idoc.php)
  -------------------------------------
  - idoc.title                 : Page <title> (also used in headers/logos).
  - idoc.output                : Directory containing openapi.json (public path).
  - idoc.external_description  : Optional route for external description content.
  - idoc.hide_download_button  : Redoc's download button visibility.
  - idoc.tryit.enabled         : Toggle the Swagger UI panel globally.
  - idoc.chat.enabled          : Toggle the AI Chat panel globally.
  - idoc.chat.provider         : Chat provider id (e.g., deepseek, openai, google,
                                 groq, huggingface, openai_compat). Defaults to
                                 deepseek in this build; override via env.
  - idoc.chat.model            : Model name for the provider (e.g., deepseek-chat,
                                 gpt-4o-mini, gemini-1.5-flash, mixtral-8x7b-32768,
                                 Qwen/Qwen2.5-7B-Instruct).
  - idoc.chat.base_url         : Optional base URL override (useful for LM Studio,
                                 llama.cpp server, or other OpenAI‑compatible servers).
  - idoc.chat.api_key_env      : Optional env var name for the API key (else the
                                 controller looks for provider‑specific defaults).
  - idoc.chat.info_view        : Optional Blade view name rendered as extra context
                                 for the assistant (e.g., 'idoc.info').

  Provider API key envs (examples):
  - DeepSeek: DEEPSEEK_API_KEY
  - OpenAI:  OPENAI_API_KEY
  - Google:  GOOGLE_API_KEY (or GEMINI_API_KEY)
  - Groq:    GROQ_API_KEY
  - HF:      HF_API_TOKEN (or HUGGINGFACE_API_KEY)
  - Local OpenAI‑compatible: set IDOC_CHAT_BASE_URL and a placeholder key

  BUNDLES
  -------
  - Redoc OSS:  https://cdn.jsdelivr.net/npm/redoc@next/bundles/redoc.standalone.js
  - Swagger UI: https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/
  - Markdown + sanitize: marked + DOMPurify
  - Syntax highlight: highlight.js (auto‑switched for dark/light)

  CUSTOMIZATION HOOKS
  -------------------
  - CSS: You can tune typography or theme in the <style> blocks below.
  - JS:  If your Redoc build uses different anchor IDs, edit getHeadings().

  ACCESSIBILITY
  -------------
  - Both panels use aria-hidden to signal open/close state.
  - Floating action buttons are keyboard accessible.

  CORS / SECURITY
  ---------------
  - Live requests are made client-side from Swagger UI. Ensure your API allows
    the docs origin via CORS and supports the HTTP methods you expose.

  =============================================================================
-->
<html>
  <head>
    <title>{{ config('idoc.title') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('idoc.chat.enabled', false))
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @endif

    <!-- =========================
         Base + Redoc cosmetics
         ========================= -->
    <style>
      /* System font for speed + Verdana for legibility */
      @import url(//fonts.googleapis.com/css?family=Roboto:400,700);
      body { margin: 0; padding: 0; font-family: Verdana, Geneva, sans-serif; }

      /* Optional logo in Redoc's sidebar (only if you inject an <img>) */
      #redoc_container .menu-content div>img {
        padding: 30px 0 30px 0; max-width: 100px; margin: auto; display: block;
        object-fit: contain; object-position: center;
      }

      /* Hide "API docs by Redocly" badge in OSS build */
      [href="https://redocly.com/redoc/"] { display: none !important; }

      /* Floating action stack (stable even if some buttons are hidden) */
      .floating-actions { position: fixed; right: 16px; z-index: 998; bottom:16px; display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
      .floating-actions .tryit-toggle { position: static; }
      .btn {
        cursor: pointer; border: 1px solid #e5e7eb; background: #fff;
        padding: 8px 12px; border-radius: 8px;
      }

      /* Slide-in panel shell */
      .tryit-panel {
        position: fixed; right: 0; top: 0; height: 100%; width: min(860px, 92vw);
        background: #fff; border-left: 1px solid #e5e7eb; box-shadow: -4px 0 12px rgba(0,0,0,.08);
        transform: translateX(100%); transition: transform .25s ease; z-index: 9998; overflow: auto;
        display: flex; flex-direction: column;
      }
      .tryit-panel.open { transform: translateX(0); }

      /* Panel header + controls */
      .tryit-header {
        position: sticky; top: 0; background: #fff; border-bottom: 1px solid #eee;
        padding: 12px 16px; display: flex; align-items: center; gap: 8px;
      }
      .tryit-header h2 { margin: 0; font-size: 16px; }
      .tryit-header .grow { flex: 1; }
      .tryit-body { padding: 8px 16px; }
      .tryit-row { position: relative; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
      .tryit-select { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }

      /* Headers popover */
      .headers-popover { position: absolute; right: 0; top: 44px; width: min(520px, 88vw); max-height: 70vh; overflow: auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); padding: 10px; display: none; z-index: 2; }
      .headers-popover.open { display: block; }
      .headers-popover .title-row { display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 6px; }
      .headers-popover textarea { width: 100%; }

      /* Secondary button styling */
      :root{ --btn-sec-bg: #f3f4f6; --btn-sec-bd: #d1d5db; --btn-sec-tx: #111827; --btn-sec-hv: #e5e7eb; --btn-sec-ac: #d1d5db; --btn-sec-ring: #2563eb; }
      @media (prefers-color-scheme: dark){ :root{ --btn-sec-bg: #1f2937; --btn-sec-bd: #374151; --btn-sec-tx: #f9fafb; --btn-sec-hv: #374151; --btn-sec-ac: #4b5563; --btn-sec-ring: #60a5fa; } }
      .btn-secondary{ background: var(--btn-sec-bg); border: 1px solid var(--btn-sec-bd); color: var(--btn-sec-tx); }
      .btn-secondary:hover{ background: var(--btn-sec-hv); }
      .btn-secondary:active{ background: var(--btn-sec-ac); }
      .btn-secondary[disabled], .btn-secondary:disabled{ opacity: .6; cursor: not-allowed; }

      /* Swagger UI typography tune: shrink the big title */
      .swagger-ui .info h1,
      .swagger-ui .info .title {
        font-size: 20px !important;
        line-height: 1.3 !important;
        font-weight: 600 !important;
      }
      .version-stamp { display: none !important; }

      /* Dark theme shell */
      body.theme-dark { background-color: #0b1220; color: #e5e7eb; }
      body.theme-dark .tryit-panel { background: #0f172a; border-left: 1px solid #1f2937; color: #e5e7eb; }
      body.theme-dark .tryit-header { background: #0f172a; border-bottom: 1px solid #1f2937; }
      body.theme-dark .btn, body.theme-dark .btn-secondary { background: #1f2937; border-color: #374151; color: #f9fafb; }
      body.theme-dark .btn-secondary:hover { background: #374151; }
      body.theme-dark .headers-popover { background: #0f172a; border-color: #1f2937; color: #e5e7eb; }
      body.theme-dark .headers-popover textarea { background: #0b1220; color: #e5e7eb; border: 1px solid #374151; }

      /* Swagger UI dark tweaks */
      body.theme-dark .swagger-ui, body.theme-dark .swagger-ui .topbar { background: #0b1220; }
      body.theme-dark .swagger-ui .info, body.theme-dark .swagger-ui .markdown, body.theme-dark .swagger-ui .opblock, body.theme-dark .swagger-ui .opblock .opblock-summary, body.theme-dark .swagger-ui .responses-inner { color: #e5e7eb; background-color: #0f172a; }
      body.theme-dark .swagger-ui .scheme-container, body.theme-dark .swagger-ui .opblock-tag, body.theme-dark .swagger-ui .tab, body.theme-dark .swagger-ui .response-control-media-type__accept-message, body.theme-dark .swagger-ui .opblock .opblock-section-header { background-color: #111827; border-color: #1f2937; color: #e5e7eb; }
      body.theme-dark .swagger-ui .model, body.theme-dark .swagger-ui .prop-format { color: #a7f3d0; }
      body.theme-dark .swagger-ui .prop-type { color: #93c5fd; }
      body.theme-dark .swagger-ui .response-col_status { color: #60a5fa; }
      body.theme-dark .swagger-ui .btn { background: #1f2937; color: #f9fafb; border-color: #374151; }

      /* ChatGPT-like chat styles */
      .idoc-chat-list { flex: 1; overflow: auto; display: flex; flex-direction: column; gap: 12px; padding: 6px 0 8px; }
      .idoc-chat-row { display: flex; gap: 10px; align-items: flex-start; }
      .idoc-chat-row.ai { flex-direction: row; }
      .idoc-chat-row.user { flex-direction: row-reverse; }
      .idoc-chat-avatar { width: 28px; height: 28px; border-radius: 50%; display:flex; align-items:center; justify-content:center; font-size:14px; background:#eef2ff; color:#1d4ed8; border:1px solid #e5e7eb; }
      .idoc-chat-bubble { max-width: min(740px, 86%); border: 1px solid #e5e7eb; border-radius: 14px; padding: 12px 14px; line-height: 1.6; font-size: 14.5px; background: #fff; color: #111827; box-shadow: 0 2px 6px rgba(0,0,0,.06); }
      .idoc-chat-bubble.user { background:#eef2ff; border-color:#c7d2fe; }
      .idoc-chat-bubble.ai { background:#fff; border-color:#e5e7eb; }
      .idoc-chat-bubble.error { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
      .idoc-chat-meta { font-size: 11px; color:#6b7280; margin-bottom:4px; display:flex; align-items:center; gap:6px; }
      .idoc-chat-actions { text-align:right; margin-top:6px; }
      .idoc-chat-actions .btn { padding:4px 8px; font-size:12px; border-radius:6px; }
      .idoc-chip { padding:8px 14px; border-radius:14px; border:1px solid #e5e7eb; background:#f9fafb; color:#111827; }
      .idoc-chip:hover { background:#f3f4f6; }
      .idoc-chat-inputrow { display:flex; gap:8px; align-items:flex-end; margin-top:10px; }
      .idoc-chat-input { flex:1; min-height:42px; max-height:180px; resize:vertical; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; font-size:14px; line-height:1.4; }
      .idoc-chat-input:focus { outline:2px solid #2563eb; outline-offset:1px; }
      .idoc-chat-send { padding:10px 14px; border-radius:10px; }
      .typing { display:inline-block; min-width:18px; }
      .typing span { display:inline-block; width:4px; height:4px; margin:0 1px; background:#9ca3af; border-radius:50%; animation: blink 1.2s infinite ease-in-out; }
      .typing span:nth-child(2){ animation-delay:.2s; }
      .typing span:nth-child(3){ animation-delay:.4s; }
      @keyframes blink { 0%,80%,100%{ opacity:.2 } 40%{ opacity:1 } }
      /* Code blocks: ensure high contrast on light */
      .idoc-chat-bubble pre { background:#0b1220; color:#e5e7eb; padding:12px; border-radius:10px; overflow:auto; border:1px solid #111827; }
      .idoc-chat-bubble :not(pre) > code { background:#f1f5f9; padding:2px 4px; border-radius:4px; }
      /* Light theme fine‑tuning */
      body:not(.theme-dark) .idoc-chat-avatar { background:#eef2ff; color:#1d4ed8; border-color:#dbeafe; }
      body:not(.theme-dark) .idoc-chat-bubble { background:#fff; border-color:#e5e7eb; color:#111827; box-shadow: 0 2px 6px rgba(0,0,0,.06); }
      body:not(.theme-dark) .idoc-chat-bubble.user { background:#eff6ff; border-color:#bfdbfe; }
      body:not(.theme-dark) .idoc-chat-bubble.ai { background:#fff; border-color:#e5e7eb; }
      body:not(.theme-dark) .idoc-chat-bubble.error { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
      body:not(.theme-dark) .idoc-chat-actions .btn { background:#f9fafb; border-color:#e5e7eb; color:#111827; }
      body:not(.theme-dark) .idoc-chat-actions .btn:hover { background:#f3f4f6; }
      body:not(.theme-dark) .idoc-chat-input { background:#fff; border-color:#e5e7eb; color:#111827; }
      body.theme-dark .idoc-chat-avatar { background:#111827; color:#e0e7ff; border-color:#1f2937; }
      body.theme-dark .idoc-chat-bubble { background:#0f172a; border-color:#1f2937; color:#e5e7eb; }
      body.theme-dark .idoc-chat-bubble.user { background:#111827; }
      body.theme-dark .idoc-chat-bubble.error { background:#7f1d1d; border-color:#fecdd3; color:#fee2e2; }
      body.theme-dark .idoc-chat-bubble :not(pre) > code { background:#1f2937; color:#e5e7eb; }

      /* Redoc sidebar + headings */
      .swagger-ui .opblock-tag.no-desc[data-tag^="!"],
      .swagger-ui .opblock[id^="operations-\\!"] span.opblock-summary-method,
      #redoc_container li[data-item-id^="tag/!"] > label > span:first-child,
      #redoc_container li[data-item-id^="tag/%21"] > label > span:first-child,
      #redoc_container [id^="tag/!"] h1,
      #redoc_container [id^="tag/%21"] h1,
      #redoc_container li[data-item-id*="/operation/!"] span,
      #redoc_container h3[id^="operation/!"],
      #redoc_container div[id^="operation/!"] h2,
      #redoc_container h2[id^="operation/!"] {
        position: relative;
        padding-left: 1.6em;
        color: #eab308 !important;
      }

      /* Add caution icon to operation title */
      .swagger-ui .opblock-tag.no-desc[data-tag^="!"] span::before,
      #redoc_container li[data-item-id^="tag/!"] > label > span:first-child::before,
      #redoc_container li[data-item-id^="tag/%21"] > label > span:first-child::before,
      #redoc_container [id^="tag/!"] h1::before,
      #redoc_container [id^="tag/%21"] h1::before,
      #redoc_container li[data-item-id*="/operation/!"] span::before,
      #redoc_container h3[id^="operation/!"]::before,
      #redoc_container div[id^="operation/!"] h2::before,
      #redoc_container h2[id^="operation/!"]::before {
        content: "⚠️ ";
        position: absolute;
        left: 0;
        font-size: 1.1em;
      }

      .swagger-ui .opblock[id^="operations-\\!"] span.opblock-summary-method::before {
        content: "⚠️";
        position: absolute;
        left: 4px;
        top: 50%;
        transform: translateY(-55%) translateX(50%);
        font-size: 1.1em;
      }

      .swagger-ui .information-container.wrapper .info {
        bachground-color: none !important;
      }
    </style>

    <!-- Favicons -->
    <link rel="icon" type="image/png" href="/favicon.ico">
    <link rel="apple-touch-icon-precomposed" href="/favicon.ico">

    <!-- Redoc OSS (read-only renderer) -->
    <script src="https://cdn.jsdelivr.net/npm/redoc@next/bundles/redoc.standalone.js"></script>

    <!-- Swagger UI (interactive console) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>

    @if (config('idoc.chat.enabled', false))
      <!-- Optional libs for nicer chat rendering -->
      <script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
      <!-- Syntax highlighting for Markdown code blocks (light + dark themes) -->
      <link id="hljsLight" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/styles/github.min.css">
      <link id="hljsDark" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/styles/github-dark.min.css" media="not all">
      <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/common.min.js"></script>
    @endif
  </head>
  <body>
    <!-- Redoc root node -->
    <div id="redoc_container"></div>

    <!-- Floating action stack: Theme / Chat / Try it -->
    <div class="floating-actions" id="floatingActions">
      @if (config('idoc.chat.enabled', false))
        <button class="btn tryit-toggle" id="chatBtn">💬 Chat</button>
      @endif
      @if (config('idoc.tryit.enabled', true))
        <button class="btn tryit-toggle" id="tryitBtn">⚡️ Try it</button>
      @endif
      <button class="btn tryit-toggle" id="themeBtn" title="Toggle theme">🌗 Auto</button>
    </div>
    
    @if (config('idoc.tryit.enabled', true))
      <!-- Slide-in panel that hosts Swagger UI. aria-hidden toggles for a11y. -->
      <div class="tryit-panel" id="tryitPanel" aria-hidden="true">
        <div class="tryit-header">
          <h2>Try it console</h2>
          <div class="grow"></div>
          <div class="tryit-row">
            <label for="tagSelect">Section:</label>
            <!-- Populated dynamically with tags from the spec -->
            <select id="tagSelect" class="tryit-select"></select>

            <button class="btn btn-secondary" id="refreshBtn" title="Reload current">Reload</button>

            <!-- Headers popover control -->
            <button class="btn btn-secondary" id="headersBtn" title="Extra request headers">Headers</button>
            <div id="headersPopover" class="headers-popover" aria-hidden="true">
              <div class="title-row">
                <div>Extra headers (JSON)</div>
                <button class="btn btn-secondary" id="headersClose">Close</button>
              </div>
              <textarea id="extraHeadersInput" rows="8" placeholder='{"X-Tenant":"acme","X-Version":"2024-10"}'></textarea>
              <div style="margin-top:6px; font-size:12px; color:#6b7280;">Saved in your browser. Invalid JSON will be highlighted.</div>
            </div>

            <button class="btn btn-secondary" id="closeTryit">Close</button>
          </div>
        </div>
        <!-- Swagger UI mounts here -->
        <div id="swagger" class="tryit-body"></div>
      </div>
    @endif

    @if (config('idoc.chat.enabled', false))
      <!-- Slide-in panel that hosts the AI chat assistant (ChatGPT-like) -->
      <div class="tryit-panel" id="chatPanel" aria-hidden="true">
        <div class="tryit-header" style="align-items:center; gap:10px;">
          <h2 style="display:flex; align-items:center; gap:8px; margin:0;">💬 AI Chat</h2>
          <div class="grow"></div>
          <button class="btn btn-secondary" id="chatClear" title="Clear chat">Clear</button>
          <button class="btn btn-secondary" id="chatExport" title="Export chat">Export</button>
          <button class="btn btn-secondary" id="closeChat">Close</button>
        </div>
        <div class="tryit-body" id="chatBody" style="display:flex; flex-direction:column; height:calc(100% - 60px); padding-bottom:14px;">
          <div id="chatMessages" class="idoc-chat-list"></div>
          <div class="idoc-chat-inputrow">
            <textarea id="chatInput" class="idoc-chat-input" rows="1" placeholder="Ask about endpoints, params, errors... (Shift+Enter for newline)"></textarea>
            <button id="chatSend" class="btn idoc-chat-send">Send</button>
          </div>
        </div>
      </div>
    @endif

    <script>
      /* =========================================================================
         1) Configuration
         ========================================================================= */

      // Where your OpenAPI JSON is served. Ensure it is publicly readable.
      // Example default generated path: public/docs/openapi.json
      const SPEC_URL = "{{ rtrim(config('idoc.output'), '/') }}/openapi.json";

      /* =========================================================================
         2) THEME: Redoc + Swagger unified toggle (Auto/Dark/Light)
         ========================================================================= */
      const redocCommonOpts = {
        pathInMiddlePanel: true,
        layout: { scope: 'section' },
        unstable_externalDescription: "{{ route(config('idoc.external_description') ?: 'idoc.info') }}",
        hideDownloadButton: {{ config('idoc.hide_download_button') ? 'true' : 'false' }}
      };

      const redark = {
        codeBlock: { backgroundColor: '#18181b' },
        colors: {
          error: { main: '#ef4444' },
          border: { light: '#27272a', dark: '#a1a1aa' },
          http: { basic: '#71717a', delete: '#ef4444', get: '#22c55e', head: '#d946ef', link: '#06b6d4', options: '#eab308', patch: '#f97316', post: '#3b82f6', put: '#ec4899' },
          primary: { main: '#71717a' },
          responses: {
            error:   { backgroundColor: 'rgba(239,68,68,0.1)', borderColor: '#fca5a5', color: '#ef4444', tabTextColor: '#ef4444' },
            info:    { backgroundColor: 'rgba(59,131,246,0.1)', borderColor: '#93c5fd', color: '#3b82f6', tabTextColor: '#3b82f6' },
            redirect:{ backgroundColor: 'rgba(234,179,8,0.1)', borderColor: '#fde047', color: '#eab308', tabTextColor: '#eab308' },
            success: { backgroundColor: 'rgba(34,197,94,0.1)', borderColor: '#86efac', color: '#22c55e', tabTextColor: '#22c55e' },
            warning: { main: '#eab308' }
          },
          secondary: { main: '#3f3f46', light: '#27272a' },
          success: { main: '#22c55e' },
          text: { primary: '#fafafa', secondary: '#d4d4d8', light: '#3f3f46' }
        },
        fab: { backgroundColor: '#52525b', color: '#67e8f9' },
        rightPanel: { backgroundColor: '#27272a', servers: { overlay: { backgroundColor: '#27272a' }, url: { backgroundColor: '#18181b' } } },
        schema: { linesColor: '#d8b4fe', typeNameColor: '#93c5fd', typeTitleColor: '#1d4ed8' },
        sidebar: { activeTextColor: '#ffffff', backgroundColor: '#18181b', textColor: '#a1a1aa' },
        typography: {
          code: { backgroundColor: '#18181b', color: '#fde047' },
          links: { color: '#0ea5e9', hover: '#0ea5e9', textDecoration: 'none', hoverTextDecoration: 'underline', visited: '#0ea5e9' }
        }
      };
      const relight = { colors: { primary: { main: '#2563eb' } } };

      const THEME_KEY = 'idoc_theme';
      const ORDER = ['auto','dark','light'];
      const themeBtn = document.getElementById('themeBtn');
      const prefersDark = () => window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      const getMode = () => localStorage.getItem(THEME_KEY) || 'auto';
      const setMode = (m) => localStorage.setItem(THEME_KEY, m);
      const effectiveIsDark = (mode) => mode==='dark' || (mode==='auto' && prefersDark());
      const labelFor = (mode) => mode==='auto'?'🌗 Auto':(mode==='dark'?'🌙 Dark':'☀️ Light');

      function sanitizeTheme(theme){ return theme ? JSON.parse(JSON.stringify(theme)) : undefined; }
      function applyPageClass(isDark){ document.body.classList.toggle('theme-dark', !!isDark); document.body.classList.toggle('theme-light', !isDark); }

      let redocObserver = null;
      function observeRedoc(){ try{ if (redocObserver) redocObserver.disconnect(); redocObserver = new MutationObserver(()=>{}); const el=document.getElementById('redoc_container'); if (el) redocObserver.observe(el, { childList:true, subtree:true }); }catch{} }

      async function mountRedoc(themeObj){
        const old = document.getElementById('redoc_container');
        const tmp = document.createElement('div'); tmp.id = 'redoc_container_tmp';
        old.insertAdjacentElement('afterend', tmp);
        const theme = sanitizeTheme(themeObj);
        try {
          await Redoc.init(SPEC_URL, { ...redocCommonOpts, theme }, tmp);
          old.replaceWith(tmp); tmp.id = 'redoc_container'; observeRedoc();
        } catch (err) { console.error('Redoc init failed:', err); tmp.remove(); }
      }

      async function renderRedocFor(mode){
        const isDark = effectiveIsDark(mode);
        applyPageClass(isDark);
        if (themeBtn) themeBtn.textContent = labelFor(mode);
        // Toggle highlight theme
        try {
          const light = document.getElementById('hljsLight');
          const dark = document.getElementById('hljsDark');
          if (light && dark) {
            if (isDark) { light.media = 'not all'; dark.media = 'all'; }
            else { light.media = 'all'; dark.media = 'not all'; }
          }
        } catch {}
        await mountRedoc(isDark ? redark : relight);
      }

      renderRedocFor(getMode()).then(() => observeRedoc());
      if (window.matchMedia) { window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (getMode()==='auto') renderRedocFor('auto'); }); }
      themeBtn?.addEventListener('click', () => { const cur=getMode(); const next=ORDER[(ORDER.indexOf(cur)+1)%ORDER.length]; setMode(next); renderRedocFor(next); });

      // Optional: normalize fetch headers for your API. Adjust the regex to your routes.
      // Safe: Redoc passes through, Swagger UI requests get Accept if missing.
      const nativeFetch = window.fetch.bind(window);
      window.fetch = (input, init = {}) => {
        init.headers = new Headers(init.headers || {});
        if (typeof input === 'string' && /\/api/.test(input) && !init.headers.has('Accept')) {
          init.headers.set('Accept', 'application/json');
        }
        return nativeFetch(input, init);
      };

      @if (config('idoc.tryit.enabled', true))
      /* =========================================================================
         3) Spec loading and indexing helpers (for filtering by tag/operation)
         ========================================================================= */

      let fullSpec = null;              // Cached OpenAPI document
      let tagList = [];                 // Unique list of tags in the spec
      const opToTag = {};               // Map operationId -> first tag (for context)
      let ui = null;                    // Swagger UI instance
      let activeTag = null;             // Currently shown tag in Swagger UI
      let activeOperation = null;       // Currently shown operation in Swagger UI

      // Loads the OpenAPI spec once and builds tag/op indexes.
      async function loadSpec() {
        if (fullSpec) return fullSpec;
        const res = await fetch(SPEC_URL);
        if (!res.ok) throw new Error('Failed to load OpenAPI spec');
        fullSpec = await res.json();

        // Build a unique tag list and an operationId -> tag map for quick lookups.
        const set = new Set();
        for (const [p, methods] of Object.entries(fullSpec.paths || {})) {
          for (const [m, op] of Object.entries(methods)) {
            if (!op || typeof op !== 'object') continue;
            (op.tags || []).forEach(t => set.add(t));
            if (op.operationId && op.tags && op.tags.length) {
              opToTag[op.operationId] = op.tags[0];
            }
          }
        }
        tagList = Array.from(set);
        return fullSpec;
      }

      // Returns a spec that includes only operations for the given tag.
      function filterSpecByTag(spec, tag) {
        const filtered = {
          openapi: spec.openapi || "3.0.0",
          info: { ...(spec.info || {}), title: ((spec.info?.title || "API") + " — " + tag) },
          servers: spec.servers || [],
          tags: (spec.tags || []).filter(t => t.name === tag),
          components: spec.components ? JSON.parse(JSON.stringify(spec.components)) : undefined,
          paths: {}
        };
        for (const [path, methods] of Object.entries(spec.paths || {})) {
          for (const [method, op] of Object.entries(methods)) {
            if (!op || typeof op !== 'object') continue;
            if ((op.tags || []).includes(tag)) {
              if (!filtered.paths[path]) filtered.paths[path] = {};
              filtered.paths[path][method] = op;
            }
          }
        }
        return filtered;
      }

      // Returns a spec that includes only the operation with the specified operationId.
      function filterSpecByOperationId(spec, operationId) {
        const filtered = {
          openapi: spec.openapi || "3.0.0",
          info: { ...(spec.info || {}), title: ((spec.info?.title || "API") + " — " + operationId) },
          servers: spec.servers || [],
          tags: spec.tags || [],
          components: spec.components ? JSON.parse(JSON.stringify(spec.components)) : undefined,
          paths: {}
        };
        for (const [path, methods] of Object.entries(spec.paths || {})) {
          for (const [method, op] of Object.entries(methods)) {
            if (!op || typeof op !== 'object') continue;
            if (op.operationId === operationId) {
              filtered.paths[path] = { [method]: op };
              return filtered;
            }
          }
        }
        // Operation not found — return an empty spec with same top-level shape.
        return filtered;
      }

      // Parses Redoc-style hashes like:
      //   #/tag/Users
      //   #/tag/Users/operation/CreateUser
      function parseHash() {
        const raw = decodeURIComponent(location.hash || "");
        const h = raw.startsWith("#/") ? raw : raw.replace(/^#/, "#/");
        const tagMatch = h.match(/#\/tag\/([^/]+)/);
        const tag = tagMatch ? tagMatch[1] : null;
        const opMatch = h.match(/#\/tag\/[^/]+\/operation\/([^/?#]+)/);
        const operationId = opMatch ? opMatch[1] : null;
        return { tag, operationId };
      }

      /* =========================================================================
         4) Scroll tracking — discover active tag/op when hash does not change
         ========================================================================= */

      // Redoc typically emits anchors like id="tag/<Name>" and id="operation/<OpId>".
      // If your build differs, update these selectors.
      function getHeadings() {
        return {
          tagHeads: Array.from(document.querySelectorAll('[id^="tag/"]')),
          opHeads:  Array.from(document.querySelectorAll('[id^="operation/"]'))
        };
      }

      // Heuristic: pick the heading nearest to 120px from the top of the viewport.
      function findActiveByScroll() {
        const { tagHeads, opHeads } = getHeadings();
        const topTarget = 120;

        // Prefer an operation if one is nearest
        let nearestOp = null, nearestOpDelta = Infinity;
        for (const el of opHeads) {
          const t = el.getBoundingClientRect().top;
          const d = Math.abs(t - topTarget);
          if (t <= window.innerHeight && d < nearestOpDelta) {
            nearestOp = el; nearestOpDelta = d;
          }
        }
        if (nearestOp) {
          const opId = nearestOp.id.replace(/^operation\//, '');
          const tag = opToTag[opId] || activeTag || null;
          return { tag, operationId: opId };
        }

        // Otherwise fall back to nearest tag heading
        let nearestTag = null, nearestTagDelta = Infinity;
        for (const el of tagHeads) {
          const t = el.getBoundingClientRect().top;
          const d = Math.abs(t - topTarget);
          if (t <= window.innerHeight && d < nearestTagDelta) {
            nearestTag = el; nearestTagDelta = d;
          }
        }
        if (nearestTag) return { tag: nearestTag.id.replace(/^tag\//, ''), operationId: null };

        // No anchors found (e.g., still rendering). Keep current context.
        return { tag: activeTag, operationId: activeOperation };
      }

      /* =========================================================================
         5) Swagger UI mounting — create/update console for a tag or operation
         ========================================================================= */

      // Debounce remounts to avoid thrashing during scroll
      let mountTimer = null;
      function debouncedMount(ctx) {
        clearTimeout(mountTimer);
        mountTimer = setTimeout(() => mountSwaggerForContext(ctx), 120);
      }

      // --- Extra headers helpers (persisted in localStorage) ---
      function readExtraHeaders() {
        try { return JSON.parse(localStorage.getItem('idoc_extra_headers') || '{}'); }
        catch { return {}; }
      }
      (function bindExtraHeaders(){
        const el = document.getElementById('extraHeadersInput');
        if (!el) return;
        const saved = localStorage.getItem('idoc_extra_headers');
        if (saved) el.value = saved;
        el.addEventListener('change', () => {
          // Keep raw JSON string; parse only when sending
          localStorage.setItem('idoc_extra_headers', el.value.trim());
        });
      })();

      // Mount or remount Swagger UI with a filtered spec
      async function mountSwaggerForContext(context) {
        const spec = await loadSpec();
        const tagSelect = document.getElementById('tagSelect');

        // Populate the tag dropdown once
        if (!tagSelect.dataset.filled) {
          tagSelect.innerHTML = tagList.map(t => `<option value="${t}">${t}</option>`).join("");
          tagSelect.dataset.filled = "1";
        }

        // Decide what to render: single operation or whole tag
        let toRender;
        let nextTag = context.tag;
        let nextOp = context.operationId;

        if (nextOp) {
          toRender = filterSpecByOperationId(spec, nextOp);
          nextTag = opToTag[nextOp] || nextTag || null; // keeps dropdown meaningful
        } else {
          if (!nextTag || !tagList.includes(nextTag)) nextTag = tagList[0] || null;
          toRender = nextTag ? filterSpecByTag(spec, nextTag) : spec;
        }

        // Avoid unnecessary re-renders
        if (nextTag === activeTag && nextOp === activeOperation && ui) return;

        // Persist context
        activeTag = nextTag || activeTag;
        activeOperation = nextOp || null;

        // Sync dropdown with current tag
        if (tagSelect && activeTag && tagSelect.value !== activeTag) tagSelect.value = activeTag;

        // Destroy any previous Swagger UI instance by clearing the mount node
        const mount = document.getElementById('swagger');
        mount.innerHTML = "";

        // Instantiate Swagger UI with the filtered spec
        ui = SwaggerUIBundle({
          spec: toRender,
          dom_id: '#swagger',
          deepLinking: true,
          tryItOutEnabled: true,
          displayRequestDuration: true,
          persistAuthorization: true,
          requestInterceptor: (req) => {
            if (!req.headers) req.headers = {};
            if (/\/api/.test(req.url) && !req.headers['Accept']) {
              req.headers['Accept'] = 'application/json';
            }

            // ✅ Inject Authorization from Swagger UI "Authorize" modal (so 401s stop)
            if (!req.headers['Authorization']) {
              const bearer = getSwaggerBearer && getSwaggerBearer();
              if (bearer) {
                req.headers['Authorization'] = bearer;
              } else {
                const tok = localStorage.getItem('idoc_bearer_token');
                if (tok) req.headers['Authorization'] = tok.match(/^Bearer\s/i) ? tok : `Bearer ${tok}`;
              }
            }

            // ✅ Inject Extra headers from LIVE textarea (fallback to localStorage)
            try {
              const el = document.getElementById('extraHeadersInput');
              const raw = (el && el.value.trim().length ? el.value.trim()
                        : (localStorage.getItem('idoc_extra_headers') || '')).trim();

              const obj = parseJsonStrict(raw);
              setHeadersInputValidity && setHeadersInputValidity(el, obj !== null);

              if (obj && typeof obj === 'object') {
                for (const k of Object.keys(obj)) {
                  if (!k) continue;
                  const v = obj[k];
                  if (v === undefined || v === null) continue;
                  req.headers[k] = String(v);
                }
              }
            } catch (_) {
              // Ignore malformed JSON; user can correct in the box
            }

            // Example: inject a Bearer token from localStorage (optional)
            // const token = localStorage.getItem('api_token');
            // if (token) req.headers['Authorization'] = `Bearer ${token}`;

            return req;
          }
        });

        // Enhance logout actions to clear persisted auth
        setTimeout(() => {
          try {
            const origLogout = ui?.authActions?.logout;
            if (typeof origLogout === 'function') {
              ui.authActions.logout = (name) => {
                const out = origLogout(name);
                try { clearAllAuthStorage(); } catch (_) {}
                return out;
              };
            }
            const origLogoutAll = ui?.authActions?.logoutAll;
            if (typeof origLogoutAll === 'function') {
              ui.authActions.logoutAll = () => {
                const out = origLogoutAll();
                try { clearAllAuthStorage(); } catch (_) {}
                return out;
              };
            }
          } catch (_) {}
        }, 0);

        // Mirror token changes to a simple key so requestInterceptor can read it fast
        setInterval(() => {
          try {
            if (!ui || !ui.authSelectors || !ui.authSelectors.authorized) return;
            const state = ui.authSelectors.authorized();
            const js = typeof state?.toJS === 'function' ? state.toJS() : state;
            const entry = js?.BearerAuth;
            let token = null;
            if (typeof entry?.value === 'string') token = entry.value;
            else if (entry?.value?.token) token = entry.value.token;
            if (token) localStorage.setItem('idoc_bearer_token', token);
          } catch (_) {}
        }, 500);

        // Re-apply BearerAuth on every mount from either Swagger's persisted store
        // or your own custom localStorage key (fallback).
        setTimeout(() => {
          try {
            const token = getSavedBearerToken(); // defined below
            if (token) {
              // Works for http bearer and apiKey styles as long as the scheme name matches
              ui.preauthorizeApiKey && ui.preauthorizeApiKey('BearerAuth', token);
            }
          } catch (_) {}
        }, 0);

        // After mount, enhance response blocks with Copy/Download
        setTimeout(enhanceResponses, 300);
      }

      /* =========================================================================
         6) UI wiring — toggles, dropdown, headers popover, navigation
         ========================================================================= */

      const panel = document.getElementById('tryitPanel');
      const openBtn = document.getElementById('tryitBtn');
      const closeBtn = document.getElementById('closeTryit');
      const refreshBtn = document.getElementById('refreshBtn');
      const tagSelect = document.getElementById('tagSelect');
      const headersBtn = document.getElementById('headersBtn');
      const headersPopover = document.getElementById('headersPopover');
      const headersClose = document.getElementById('headersClose');

      function setHeadersOpen(open){ if (!headersPopover) return; headersPopover.classList.toggle('open', !!open); headersPopover.setAttribute('aria-hidden', open ? 'false' : 'true'); }
      setHeadersOpen(false);
      headersBtn?.addEventListener('click', (e) => { e.stopPropagation(); setHeadersOpen(!headersPopover.classList.contains('open')); });
      headersClose?.addEventListener('click', (e) => { e.stopPropagation(); setHeadersOpen(false); });
      document.addEventListener('click', (e) => { if (!headersPopover?.classList.contains('open')) return; const within = headersPopover.contains(e.target) || headersBtn.contains(e.target); if (!within) setHeadersOpen(false); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setHeadersOpen(false); });

      // Open the panel and mount Swagger UI for current context (hash or scroll)
      async function openPanelForCurrent() {
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');

        const fromHash = parseHash();
        if (fromHash.tag || fromHash.operationId) {
          await mountSwaggerForContext(fromHash);
        } else {
          await loadSpec();                    // ensure tag/op indices exist
          const ctx = findActiveByScroll();    // infer when hash is silent
          await mountSwaggerForContext(ctx);
        }
      }
      openBtn?.addEventListener('click', openPanelForCurrent);

      // Close the panel
      closeBtn?.addEventListener('click', () => {
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
      });

      // Manual tag switch via dropdown. Also scroll Redoc to keep visuals aligned.
      tagSelect?.addEventListener('change', async (e) => {
        activeOperation = null; // changing tags clears op focus
        const el = document.getElementById('tag/' + e.target.value);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        await mountSwaggerForContext({ tag: e.target.value, operationId: null });
      });

      // Force a refresh of the current context (useful if headers changed)
      refreshBtn?.addEventListener('click', async () => {
        await mountSwaggerForContext({ tag: activeTag, operationId: activeOperation });
      });

      // Follow Redoc hash changes produced by clicks
      window.addEventListener('hashchange', async () => {
        if (!panel.classList.contains('open')) return;
        const ctx = parseHash();
        await mountSwaggerForContext(ctx);
      });

      // Keep console synced while scrolling, even if Redoc does not touch the hash
      let scrollTicking = false;
      async function onScroll() {
        if (!panel.classList.contains('open')) return; // only when panel is visible
        if (scrollTicking) return;
        scrollTicking = true;
        requestAnimationFrame(async () => {
          const ctx = findActiveByScroll();
          // Only remount when the context actually changes
          if (ctx.tag !== activeTag || ctx.operationId !== activeOperation) {
            debouncedMount(ctx);
          }
          // Keep dropdown synced with the current tag
          if (ctx.tag && document.getElementById('tagSelect').value !== ctx.tag) {
            document.getElementById('tagSelect').value = ctx.tag;
          }
          scrollTicking = false;
        });
      }
      window.addEventListener('scroll', onScroll, { passive: true });

      // Observe Redoc DOM changes (lazy renders). We don't act here, but this
      // ensures queries in findActiveByScroll() see new anchors soon after render.
      new MutationObserver(() => {}).observe(
        document.getElementById('redoc_container'),
        { childList: true, subtree: true }
      );

      /* =========================================================================
         7) Swagger response enhancements — Copy and Download buttons
         ========================================================================= */

      function enhanceResponses() {
        // Target common Swagger UI code blocks inside responses and examples
        const codeBlocks = document.querySelectorAll(
          '#swagger .opblock .responses-wrapper .highlight-code, ' +
          '#swagger .opblock .responses-wrapper pre, ' +
          '#swagger .model-example .highlight-code'
        );

        codeBlocks.forEach(block => {
          if (block.dataset.tools) return;

          // Create a small action bar
          const bar = document.createElement('div');
          bar.style.textAlign = 'right';
          bar.style.margin = '6px 0';

          const copyBtn = document.createElement('button');
          copyBtn.className = 'btn';
          copyBtn.textContent = 'Copy JSON';

          const dlBtn = document.createElement('button');
          dlBtn.className = 'btn';
          dlBtn.style.marginLeft = '6px';
          dlBtn.textContent = 'Download';

          bar.appendChild(copyBtn);
          bar.appendChild(dlBtn);

          // Find the PRE element with the raw text
          const pre = block.matches('pre') ? block : block.querySelector('pre');

          copyBtn.onclick = async () => {
            const txt = pre ? pre.textContent : block.textContent || '';
            try {
              await navigator.clipboard.writeText(txt || '');
              copyBtn.textContent = 'Copied!';
              setTimeout(() => (copyBtn.textContent = 'Copy JSON'), 1200);
            } catch {
              copyBtn.textContent = 'Copy failed';
              setTimeout(() => (copyBtn.textContent = 'Copy JSON'), 1200);
            }
          };

          dlBtn.onclick = () => {
            const txt = pre ? pre.textContent : block.textContent || '';
            const blob = new Blob([txt], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'response.json';
            a.click();
            URL.revokeObjectURL(a.href);
          };

          // Insert action bar just before the code block
          block.parentNode.insertBefore(bar, block);
          block.dataset.tools = '1';
        });
      }

      // Watch the Swagger mount for new/updated responses
      new MutationObserver(() => enhanceResponses()).observe(
        document.getElementById('swagger'),
        { childList: true, subtree: true }
      );

      /* =========================================================================
         8) Optional: Bearer token helper
         ========================================================================= */
      // Example: if you store an API token in localStorage, you can auto-inject it
      // into the Swagger UI requests. Adjust to your auth scheme and storage.
      // To use, uncomment the requestInterceptor lines above and this function.=
      
      function getSwaggerBearer() {
        try {
          if (!ui || !ui.authSelectors || !ui.authSelectors.authorized) return null;
          // authorized() can be an Immutable Map or a plain object depending on version
          const authState = ui.authSelectors.authorized();
          const authObj = typeof authState?.toJS === 'function' ? authState.toJS() : authState;

          // Your scheme name is "BearerAuth" (http, bearer) per idoc config
          const entry = authObj?.BearerAuth;
          if (!entry) return null;

          const value = entry.value ?? entry; // string or object
          if (typeof value === 'string') return `Bearer ${value}`;
          if (value && value.token) return `${value.token_type || 'Bearer'} ${value.token}`;
        } catch (_) {}
        return null;
      }

      /* =========================================================
          9) Extra headers input box (persisted in localStorage)
          ========================================================= */
      function parseJsonStrict(s) {
        try { return s ? JSON.parse(s) : {}; } catch { return null; }
      }
      function setHeadersInputValidity(el, ok) {
        if (!el) return;
        el.style.borderColor = ok ? '#e5e7eb' : '#ef4444';
        el.title = ok ? '' : 'Invalid JSON';
      }
      (function bindExtraHeaders(){
        const el = document.getElementById('extraHeadersInput');
        if (!el) return;

        // Load saved value
        const saved = localStorage.getItem('idoc_extra_headers');
        if (saved != null) el.value = saved;

        // Save on each keystroke and show validity
        const handler = () => {
          const val = el.value.trim();
          localStorage.setItem('idoc_extra_headers', val);
          setHeadersInputValidity(el, parseJsonStrict(val) !== null);
        };
        el.addEventListener('input', handler);
        el.addEventListener('change', handler);
        // Initial validity paint
        handler();
      })();
      @endif

      // Clear persisted auth from browser storage (used on Swagger logout)
      function clearAllAuthStorage() {
        try {
          localStorage.removeItem('idoc_bearer_token');
          localStorage.removeItem('authorized');
          localStorage.removeItem('swagger_ui_auth');
          localStorage.removeItem('swagger-auth');
          localStorage.removeItem('swagger_authorization');
          for (let i = localStorage.length - 1; i >= 0; i--) {
            const k = localStorage.key(i);
            try {
              const v = localStorage.getItem(k);
              if (/BearerAuth/i.test(k || '') || /BearerAuth/i.test(v || '')) localStorage.removeItem(k);
            } catch {}
          }
        } catch {}
      }

      // Global helper: Returns the raw token without "Bearer " prefix
      function getSavedBearerToken() {
        try {
          if (ui && ui.authSelectors && ui.authSelectors.authorized) {
            const state = ui.authSelectors.authorized();
            const js = typeof state?.toJS === 'function' ? state.toJS() : state;
            const entry = js?.BearerAuth;
            if (entry) {
              const v = entry.value ?? entry;
              if (typeof v === 'string') return v.replace(/^Bearer\s+/i, '');
              if (v && v.token) return v.token;
            }
          }
        } catch (_) {}
        const fromCustom = localStorage.getItem('idoc_bearer_token');
        return fromCustom ? fromCustom.replace(/^Bearer\s+/i, '') : '';
      }

      @if (config('idoc.chat.enabled', false))
      // ============================
      // AI Chat (ChatGPT-like)
      // ============================
      const chatBtn = document.getElementById('chatBtn');
      const chatPanel = document.getElementById('chatPanel');
      const closeChat = document.getElementById('closeChat');
      const chatClear = document.getElementById('chatClear');
      const chatExport = document.getElementById('chatExport');
      const chatMessages = document.getElementById('chatMessages');
      const chatInput = document.getElementById('chatInput');
      const chatSend = document.getElementById('chatSend');

      // Configure marked + highlight.js
      try {
        if (window.marked) {
          marked.setOptions({
            breaks: true,
            highlight: function(code, lang){
              try {
                if (window.hljs) {
                  return lang ? hljs.highlight(code, { language: lang }).value : hljs.highlightAuto(code).value;
                }
              } catch(_){}
              return code;
            }
          });
        }
      } catch(_){}

      function safeHTML(html){ return window.DOMPurify ? DOMPurify.sanitize(html, { ADD_ATTR: ['target'] }) : html; }
      function renderMarkdown(md){
        try {
          const raw = window.marked ? marked.parse(md || '') : (md || '').replace(/\n/g,'<br>');
          const html = safeHTML(raw);
          const tmp = document.createElement('div'); tmp.innerHTML = html;
          tmp.querySelectorAll('a[href]').forEach(a => a.setAttribute('target','_blank'));
          return tmp.innerHTML;
        } catch (_) { return (md || '').replace(/\n/g,'<br>'); }
      }

      function bubble(role, content, opts={}){
        const row = document.createElement('div');
        row.className = `idoc-chat-row ${role==='user'?'user':'ai'}`;
        const avatar = document.createElement('div');
        avatar.className = 'idoc-chat-avatar';
        avatar.textContent = role==='user' ? 'You' : 'AI';
        const bub = document.createElement('div');
        bub.className = `idoc-chat-bubble ${role==='user'?'user':'ai'}${opts.error?' error':''}`;
        const meta = document.createElement('div'); meta.className = 'idoc-chat-meta'; meta.textContent = role==='user' ? 'You' : 'AI';
        const body = document.createElement('div'); body.className = 'idoc-chat-body';
        body.innerHTML = role==='user' ? (content || '') : renderMarkdown(content || '');
        const actions = document.createElement('div'); actions.className = 'idoc-chat-actions';
        if (role !== 'user') {
          const copyBtn = document.createElement('button');
          copyBtn.className = 'btn btn-secondary';
          copyBtn.textContent = 'Copy';
          copyBtn.onclick = async () => {
            try { await navigator.clipboard.writeText((content || '').toString()); copyBtn.textContent='Copied!'; setTimeout(()=>copyBtn.textContent='Copy', 1200); }
            catch { copyBtn.textContent = 'Copy failed'; setTimeout(()=>copyBtn.textContent='Copy', 1200); }
          };
          actions.appendChild(copyBtn);
        }
        bub.appendChild(meta); bub.appendChild(body); bub.appendChild(actions);
        row.appendChild(avatar); row.appendChild(bub);
        chatMessages.appendChild(row);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        try { if (window.hljs) row.querySelectorAll('pre code').forEach(el => hljs.highlightElement(el)); } catch(_){}
        return { row, bub, body };
      }

      function typingBubble(){
        const { row, body } = bubble('ai', '');
        const t = document.createElement('div'); t.className = 'typing'; t.innerHTML = '<span></span><span></span><span></span>';
        body.innerHTML = ''; body.appendChild(t);
        return row;
      }

      function openChat(){ chatPanel.classList.add('open'); chatPanel.setAttribute('aria-hidden','false'); chatInput?.focus(); restoreChat(); }
      chatBtn?.addEventListener('click', openChat);
      closeChat?.addEventListener('click', ()=>{ chatPanel.classList.remove('open'); chatPanel.setAttribute('aria-hidden','true'); });

      const CHAT_KEY = 'idoc_chat_history';
      function saveChat(){ try{ localStorage.setItem(CHAT_KEY, chatMessages.innerHTML); }catch(_){} }
      function restoreChat(){ try{ const v = localStorage.getItem(CHAT_KEY); if (v){ chatMessages.innerHTML = v; chatMessages.scrollTop = chatMessages.scrollHeight; } }catch(_){} }
      function clearChat(){ chatMessages.innerHTML = ''; saveChat(); }

      // Provider chooser when API key is missing
      // Robust clipboard helper with fallback
      async function copyText(text){
        try { if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); return true; } } catch {}
        try { const ta = document.createElement('textarea'); ta.value = text; ta.style.position='fixed'; ta.style.opacity='0'; document.body.appendChild(ta); ta.focus(); ta.select(); const ok=document.execCommand('copy'); ta.remove(); return ok; } catch { return false; }
      }

      // One-time event delegation for provider chooser actions
      let providerDelegatesBound = false;
      function bindProviderDelegates(){
        if (providerDelegatesBound) return; providerDelegatesBound = true;
        chatMessages.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-provider-id], [data-action]');
          if (!btn) return;
          const row = btn.closest('.idoc-chat-row'); if (!row) return;
          const body = row.querySelector('.idoc-chat-body'); if (!body) return;
          const payload = (() => { try{ return JSON.parse(row.dataset.providers || 'null'); }catch{ return null; } })();
          const postNote = row.dataset.postSetupNote || '';
          // Provider button clicked
          if (btn.dataset.providerId) {
            e.preventDefault(); e.stopPropagation();
            const p = (payload || []).find(x => (x.id||'') === btn.dataset.providerId);
            if (!p) return;
            const envText = (p.env_lines||[]).join('\n');
            const envSnippet = '```env\n' + envText + '\n```';
            const md = [
              `### Set up ${p.title}`,
              '',
              `${p.id==='oss_local' ? '- Install: ' : '- Get key: '}<${p.keys_url}>`,
              `- Docs: <${p.docs_url}>`,
              '',
              '**.env entries:**',
              envSnippet,
              '',
              postNote || '',
            ].join('\n');
            body.innerHTML = renderMarkdown(md);
            const actions = document.createElement('div'); actions.className='idoc-chat-actions';
            const copy = document.createElement('button'); copy.className='btn btn-secondary'; copy.type='button'; copy.dataset.action='copy-env'; copy.dataset.env = envText; copy.textContent='Copy .env';
            const back = document.createElement('button'); back.className='btn btn-secondary'; back.type='button'; back.dataset.action='back'; back.style.marginLeft='6px'; back.textContent='Back';
            actions.appendChild(copy); actions.appendChild(back); body.appendChild(actions);
            return;
          }
          // Actions
          const action = btn.dataset.action;
          if (action === 'back') {
            body.innerHTML = '';
            // Rebuild chooser UI from providers list
            const title = document.createElement('div'); title.className='idoc-chat-meta'; title.textContent='Choose a chat provider to see setup steps:';
            const btns = document.createElement('div'); btns.style.display='flex'; btns.style.flexWrap='wrap'; btns.style.gap='8px'; btns.style.margin='8px 0';
            (payload || []).forEach(p => { const b=document.createElement('button'); b.className='btn btn-secondary idoc-chip'; b.type='button'; b.dataset.providerId=p.id; b.textContent=p.title; btns.appendChild(b); });
            const hint = document.createElement('div'); hint.style.fontSize='12px'; hint.style.color='#6b7280'; hint.textContent='You can change the provider later via IDOC_CHAT_PROVIDER, or disable chat by setting IDOC_CHAT_ENABLED=false.';
            body.appendChild(title); body.appendChild(btns); body.appendChild(hint);
            return;
          }
          if (action === 'copy-env') {
            const ok = btn.dataset.env ? (copyText(btn.dataset.env)) : false;
            btn.textContent = ok ? 'Copied!' : 'Copy failed';
            setTimeout(()=> btn.textContent='Copy .env', 1200);
            return;
          }
        }, true);
      }

      function renderProviderChooser(data){
        const { providers = [], current_provider = '', post_setup_note = '' } = data || {};
        const { row, body } = bubble('ai', '');
        // Persist providers on this row for event delegation to work after theme changes
        try { row.dataset.providers = JSON.stringify(providers); } catch {}
        row.dataset.postSetupNote = post_setup_note || '';
        bindProviderDelegates();

        const title = document.createElement('div');
        title.className = 'idoc-chat-meta';
        title.textContent = 'Choose a chat provider to see setup steps:';
        const btns = document.createElement('div');
        btns.style.display = 'flex'; btns.style.flexWrap = 'wrap'; btns.style.gap = '8px'; btns.style.margin = '8px 0';
        (providers||[]).forEach(p => {
          const b = document.createElement('button'); b.className = 'btn btn-secondary idoc-chip'; b.type='button'; b.textContent = p.title; b.dataset.providerId = p.id;
          if ((p.id||'') === (current_provider||'')) { b.title = 'Current setting'; }
          btns.appendChild(b);
        });
        const hint = document.createElement('div'); hint.style.fontSize='12px'; hint.style.color='#6b7280'; hint.textContent = 'You can change the provider later via IDOC_CHAT_PROVIDER, or disable chat by setting IDOC_CHAT_ENABLED=false.';
        body.innerHTML = ''; body.appendChild(title); body.appendChild(btns); body.appendChild(hint);
        return row;
      }

      async function sendChat(){
        const msg=(chatInput.value||'').trim(); if(!msg) return;
        chatSend.disabled = true; chatSend.textContent = 'Sending…';
        bubble('user', msg); chatInput.value='';
        const trow = typingBubble();
        try{
          const res = await fetch('{{ route('idoc.chat') }}',{
            method:'POST',
            headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':(document.head.querySelector('meta[name="csrf-token"]').content||'') },
            body: JSON.stringify({ message: msg })
          });
          const json = await res.json();
          trow.remove();
          if (json?.reason === 'missing_api_key' && json?.data?.providers) {
            renderProviderChooser(json.data);
          } else if (!res.ok || json?.status === 'error') {
            bubble('ai', json?.message || 'Error calling assistant', { error:true });
          } else {
            bubble('ai', json?.data?.reply || json?.message || 'No response');
          }
          saveChat();
        }catch(e){
          trow.remove(); bubble('ai', 'Error calling assistant', { error:true }); saveChat();
        } finally {
          chatSend.disabled = false; chatSend.textContent = 'Send'; chatInput.focus();
        }
      }
      chatSend?.addEventListener('click', sendChat);
      chatInput?.addEventListener('keydown', (e)=>{ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendChat(); } });
      chatClear?.addEventListener('click', () => clearChat());
      chatExport?.addEventListener('click', () => {
        try{
          const blob = new Blob([chatMessages.innerText || ''], { type:'text/plain;charset=utf-8' });
          const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'idoc-chat.txt'; a.click(); URL.revokeObjectURL(a.href);
        }catch(_){}
      });
      @endif
    </script>
  </body>
</html>
