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

  UX MODEL
  --------
  - Users read the docs in Redoc as usual.
  - A floating "Try it" button opens a right-hand panel with Swagger UI.
  - The console is context-aware:
      • If a Redoc click changes the URL hash, we follow that (e.g. #/tag/Auth or
        #/tag/Auth/operation/Login).
      • If the hash does not change during scroll, we infer the active section by
        looking at visible Redoc headings (id="tag/..." or "operation/...").

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

  BUNDLES
  -------
  - Redoc OSS:  https://cdn.jsdelivr.net/npm/redoc@next/bundles/redoc.standalone.js
  - Swagger UI: https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/

  CUSTOMIZATION HOOKS
  -------------------
  - CSS: You can tune typography or theme in the <style> blocks below.
  - JS:  If your Redoc build uses different anchor IDs, edit getHeadings().

  ACCESSIBILITY
  -------------
  - The Try it panel uses aria-hidden to signal open/close state.
  - The toggle button is keyboard accessible.

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

      /* Floating toggle button for the Try it panel */
      .tryit-toggle {
        position: fixed; right: 16px; bottom: 16px; z-index: 9999;
      }
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
      .tryit-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
      .tryit-select { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }

      /* Swagger UI typography tune: shrink the big title */
      .swagger-ui .info h1,
      .swagger-ui .info .title {
        font-size: 20px !important;
        line-height: 1.3 !important;
        font-weight: 600 !important;
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
  </head>
  <body>
    <!-- Redoc root node -->
    <div id="redoc_container"></div>

    @if (config('idoc.tryit.enabled', true))
      <!-- Floating button to open the Try it console -->
      <button class="tryit-toggle btn" id="tryitBtn">Try it</button>

      <!-- Slide-in panel that hosts Swagger UI. aria-hidden toggles for a11y. -->
      <div class="tryit-panel" id="tryitPanel" aria-hidden="true">
        <div class="tryit-header">
          <h2>Try it console</h2>
          <div class="grow"></div>
          <div class="tryit-row">
            <label for="tagSelect">Section:</label>
            <!-- Populated dynamically with tags from the spec -->
            <select id="tagSelect" class="tryit-select"></select>

            <button class="btn" id="refreshBtn" title="Reload current">Reload</button>
            <button class="btn" id="closeTryit">Close</button>

            <!-- NEW: Extra headers JSON (persisted and injected) -->
            <textarea id="extraHeadersInput"
                      class="tryit-select"
                      rows="3"
                      placeholder='Extra headers JSON, e.g. {"X-Tenant":"acme","X-Version":"2024-10"}'
                      style="min-width:240px"></textarea>
          </div>
        </div>
        <!-- Swagger UI mounts here -->
        <div id="swagger" class="tryit-body"></div>
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
         2) Redoc initialization
         ========================================================================= */

      Redoc.init(
        SPEC_URL,
        {
          // UX: use the focused middle panel path layout
          pathInMiddlePanel: true,
          // Group by sections (Redoc feature)
          layout: { scope: "section" },
          // Optional external description (Laravel route). Safe to remove if unused.
          unstable_externalDescription: "{{ route(config('idoc.external_description') ?: 'idoc.info') }}",
          // Hide "Download" button if configured
          hideDownloadButton: {{ config('idoc.hide_download_button') ? 'true' : 'false' }}
        },
        document.getElementById("redoc_container")
      );

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
         6) UI wiring — toggles, dropdown, and navigation listeners
         ========================================================================= */

      const panel = document.getElementById('tryitPanel');
      const openBtn = document.getElementById('tryitBtn');
      const closeBtn = document.getElementById('closeTryit');
      const refreshBtn = document.getElementById('refreshBtn');
      const tagSelect = document.getElementById('tagSelect');

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

        /* =========================================================
           10) Extra: Bearer token persistence helper
           ========================================================= */
        // Returns the raw token without "Bearer " prefix
        function getSavedBearerToken() {
          // 1) Prefer Swagger UI’s own persisted store, if available
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

          // 2) Fallback to your own local key if you decide to store it there
          const fromCustom = localStorage.getItem('idoc_bearer_token');
          return fromCustom ? fromCustom.replace(/^Bearer\s+/i, '') : '';
        }
      })();
      @endif
    </script>
  </body>
</html>
