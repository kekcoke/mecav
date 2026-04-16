<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $diagram->title }} — Mecav</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="editor-layout">

{{-- ── Toolbar ──────────────────────────────────────────── --}}
<header class="editor-toolbar">
    <div class="toolbar-left">
        <a href="{{ route('diagrams.index') }}" class="btn-ghost">← All Diagrams</a>
        <span class="diagram-title" id="diagramTitle">{{ $diagram->title }}</span>
        <span class="status-badge status-{{ $diagram->status }}">{{ $diagram->status }}</span>
    </div>
    <div class="toolbar-right">
        {{-- AI Toggle --}}
        <label class="toggle-label" for="aiToggle">
            <input type="checkbox" id="aiToggle"
                   {{ $diagram->ai_enabled ? 'checked' : '' }}
                   aria-label="Enable AI suggestions">
            <span class="toggle-track"><span class="toggle-thumb"></span></span>
            <span>AI Assist</span>
        </label>

        {{-- Export --}}
        <div class="dropdown" id="exportDropdown">
            <button class="btn-secondary" aria-haspopup="true">Export ▾</button>
            <ul class="dropdown-menu" role="menu">
                <li><button data-format="svg">SVG</button></li>
                <li><button data-format="png">PNG</button></li>
                <li><button data-format="pdf">PDF</button></li>
                <li><button data-format="json">Snapshot JSON</button></li>
            </ul>
        </div>

        <button class="btn-primary" id="saveBtn">Save</button>
    </div>
</header>

{{-- ── Main Split Pane ──────────────────────────────────── --}}
<main class="editor-main">

    {{-- Left: Code Editor + AI Prompt --}}
    <section class="editor-pane" aria-label="Mermaid source editor">
        <div class="pane-header">
            <span>Mermaid Code</span>
            <span class="save-indicator" id="saveIndicator" aria-live="polite"></span>
        </div>

        <textarea
            id="mermaidSource"
            class="code-editor"
            spellcheck="false"
            aria-label="Mermaid diagram source code"
            data-diagram-id="{{ $diagram->ulid }}"
        >{{ $diagram->mermaid_code }}</textarea>

        {{-- AI Prompt Panel (shown when AI toggled on) --}}
        <div class="ai-panel" id="aiPanel" hidden>
            <textarea id="aiPrompt" placeholder="Describe what to add or change…" rows="3"></textarea>
            <button class="btn-primary" id="aiSubmit">Generate ✨</button>
            <div class="ai-response" id="aiResponse" aria-live="polite"></div>
        </div>
    </section>

    {{-- Right: Live Preview --}}
    <section class="preview-pane" aria-label="Diagram preview">
        <div class="pane-header">
            <span>Preview</span>
            <span class="render-error" id="renderError" role="alert" aria-live="assertive"></span>
        </div>
        <div class="mermaid-output" id="mermaidOutput">
            {{-- Mermaid renders here async --}}
        </div>
    </section>
</main>

{{-- ── Snapshot Sidebar ─────────────────────────────────── --}}
<aside class="snapshot-sidebar" id="snapshotSidebar" aria-label="Version history">
    <div class="sidebar-header">
        <h2>Versions</h2>
        <button class="btn-ghost" id="closeSidebar" aria-label="Close sidebar">✕</button>
    </div>
    <ul class="snapshot-list" id="snapshotList" role="list">
        @foreach($diagram->snapshots as $snap)
        <li class="snapshot-item" data-snapshot-id="{{ $snap->ulid }}" data-version="{{ $snap->version }}">
            <div class="snap-meta">
                <strong>v{{ $snap->version }}</strong>
                @if($snap->label) <em>{{ $snap->label }}</em> @endif
            </div>
            <time class="snap-time" datetime="{{ $snap->created_at->toIso8601String() }}">
                {{ $snap->created_at->diffForHumans() }}
            </time>
            <div class="snap-actions">
                <button class="btn-ghost snap-preview-btn" aria-label="Preview version {{ $snap->version }}">Preview</button>
                <button class="btn-warning snap-revert-btn" aria-label="Revert to version {{ $snap->version }}">Revert</button>
                <button class="btn-ghost snap-export-btn" aria-label="Export version {{ $snap->version }}">Export</button>
            </div>
        </li>
        @endforeach
    </ul>
</aside>

<button class="fab-history" id="toggleSidebar" aria-label="Toggle version history" aria-expanded="false">
    🕐
</button>

{{-- ── Data island ─────────────────────────────────────── --}}
<script id="app-config" type="application/json">
{
    "diagramId": "{{ $diagram->ulid }}",
    "aiEnabled": {{ $diagram->ai_enabled ? 'true' : 'false' }},
    "routes": {
        "update":   "{{ route('diagrams.update', $diagram) }}",
        "snapshots": "{{ route('diagrams.snapshots', $diagram) }}",
        "revert":   "{{ route('diagrams.revert', [$diagram, '__SNAP__']) }}",
        "export":   "{{ route('diagrams.export', $diagram) }}",
        "aiSuggest":"{{ route('diagrams.ai-suggest', $diagram) }}"
    }
}
</script>

{{-- Libraries --}}
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
(function () {
    'use strict';

    const cfg     = JSON.parse(document.getElementById('app-config').textContent);
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf    = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // ── Mermaid init ──────────────────────────────────────
    mermaid.initialize({ startOnLoad: false, theme: 'dark', securityLevel: 'loose' });

    let renderTimer = null;
    const sourceEl  = document.getElementById('mermaidSource');
    const outputEl  = document.getElementById('mermaidOutput');
    const errorEl   = document.getElementById('renderError');

    async function renderDiagram(code) {
        try {
            errorEl.textContent = '';
            const id = 'mmd-' + Date.now();
            const { svg } = await mermaid.render(id, code);
            outputEl.innerHTML = svg;
        } catch (err) {
            errorEl.textContent = '⚠ ' + (err.message || 'Syntax error');
            outputEl.innerHTML  = '';
        }
    }

    // Async debounced render on every keystroke
    sourceEl.addEventListener('input', () => {
        clearTimeout(renderTimer);
        renderTimer = setTimeout(() => renderDiagram(sourceEl.value), 400);
    });

    // Initial render
    renderDiagram(sourceEl.value);

    // ── Save ──────────────────────────────────────────────
    const saveBtn       = document.getElementById('saveBtn');
    const saveIndicator = document.getElementById('saveIndicator');
    let saveTimer = null;

    async function save(code) {
        saveIndicator.textContent = 'Saving…';
        try {
            const res = await fetch(cfg.routes.update, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body:    JSON.stringify({ mermaid_code: code }),
            });
            if (!res.ok) throw new Error(await res.text());
            saveIndicator.textContent = '✓ Saved';
            setTimeout(() => { saveIndicator.textContent = ''; }, 2500);
        } catch (e) {
            saveIndicator.textContent = '✗ Save failed';
            console.error(e);
        }
    }

    // Auto-save after 2 s of inactivity
    sourceEl.addEventListener('input', () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => save(sourceEl.value), 2000);
    });

    saveBtn.addEventListener('click', () => save(sourceEl.value));

    // ── AI Toggle ─────────────────────────────────────────
    const aiToggle = document.getElementById('aiToggle');
    const aiPanel  = document.getElementById('aiPanel');

    aiToggle.addEventListener('change', () => {
        aiPanel.hidden = !aiToggle.checked;
        fetch(cfg.routes.update, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body:    JSON.stringify({ ai_enabled: aiToggle.checked }),
        });
    });

    if (cfg.aiEnabled) aiPanel.hidden = false;

    // ── AI Submit ─────────────────────────────────────────
    document.getElementById('aiSubmit').addEventListener('click', async () => {
        const prompt   = document.getElementById('aiPrompt').value.trim();
        const aiResp   = document.getElementById('aiResponse');
        if (!prompt) return;

        aiResp.textContent = '⏳ Generating…';
        try {
            const res  = await fetch(cfg.routes.aiSuggest, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body:    JSON.stringify({ prompt }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || res.statusText);

            aiResp.textContent = data.explanation || '';
            sourceEl.value     = data.mermaid_code;
            renderDiagram(data.mermaid_code);
        } catch (e) {
            aiResp.textContent = '✗ ' + e.message;
        }
    });

    // ── Snapshot Sidebar ──────────────────────────────────
    const sidebar       = document.getElementById('snapshotSidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    const closeSidebar  = document.getElementById('closeSidebar');

    toggleSidebar.addEventListener('click', () => {
        const open = sidebar.classList.toggle('open');
        toggleSidebar.setAttribute('aria-expanded', String(open));
    });
    closeSidebar.addEventListener('click', () => {
        sidebar.classList.remove('open');
        toggleSidebar.setAttribute('aria-expanded', 'false');
    });

    // Revert
    document.querySelectorAll('.snap-revert-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const item       = btn.closest('.snapshot-item');
            const snapId     = item.dataset.snapshotId;
            const version    = item.dataset.version;
            if (!confirm(`Revert to v${version}? Your current code will be saved as a snapshot first.`)) return;

            const url = cfg.routes.revert.replace('__SNAP__', snapId);
            const res = await fetch(url, {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrf },
            });
            if (res.ok) {
                const data = await res.json();
                sourceEl.value = data.mermaid_code;
                renderDiagram(data.mermaid_code);
                location.reload();
            }
        });
    });

    // Export
    document.querySelectorAll('[data-format]').forEach(btn => {
        btn.addEventListener('click', () => {
            window.location.href = cfg.routes.export + '?format=' + btn.dataset.format;
        });
    });

    document.querySelectorAll('.snap-export-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const snapId = btn.closest('.snapshot-item').dataset.snapshotId;
            window.location.href = cfg.routes.export + '?format=json&snapshot_id=' + snapId;
        });
    });
})();
</script>
</body>
</html>
