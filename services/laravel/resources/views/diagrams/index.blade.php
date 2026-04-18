<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>My Diagrams — Mecav</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-layout">

<header class="dashboard-header">
    <div class="header-left">
        <h1>My Diagrams</h1>
    </div>
    <div class="header-right">
        <a href="{{ route('diagrams.create') }}" class="btn-primary">+ New Diagram</a>
    </div>
</header>

<main class="dashboard-main">
    {{-- Filter Bar --}}
    <div class="filter-bar">
        <input type="search" id="searchInput" placeholder="Search diagrams…" aria-label="Search diagrams">
        <select id="statusFilter" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    {{-- Diagram Grid --}}
    <div class="diagram-grid" id="diagramGrid" role="list">
        @forelse($diagrams as $diagram)
        <article class="diagram-card" role="listitem" data-ulid="{{ $diagram->ulid }}">
            <a href="{{ route('diagrams.show', $diagram) }}" class="card-link" aria-label="Edit {{ $diagram->title }}">
                <div class="card-preview" id="preview-{{ $diagram->ulid }}">
                    {{-- Mermaid preview rendered client-side --}}
                </div>
            </a>
            <div class="card-body">
                <div class="card-header">
                    <h2 class="card-title">{{ $diagram->title }}</h2>
                    <span class="status-badge status-{{ $diagram->status }}">{{ $diagram->status }}</span>
                </div>
                @if($diagram->description)
                <p class="card-description">{{ Str::limit($diagram->description, 80) }}</p>
                @endif
                <div class="card-meta">
                    <time datetime="{{ $diagram->updated_at->toIso8601String() }}">
                        Updated {{ $diagram->updated_at->diffForHumans() }}
                    </time>
                    @if($diagram->latestSnapshot)
                    <span>v{{ $diagram->latestSnapshot->version }}</span>
                    @endif
                    @if($diagram->ai_enabled)
                    <span class="ai-badge" title="AI Assist enabled">✨ AI</span>
                    @endif
                </div>
            </div>
            <div class="card-actions">
                <a href="{{ route('diagrams.show', $diagram) }}" class="btn-secondary">Open</a>
                <button class="btn-ghost export-btn" data-ulid="{{ $diagram->ulid }}" data-format="svg">Export</button>
            </div>
        </article>
        @empty
        <div class="empty-state">
            <p>No diagrams yet.</p>
            <a href="{{ route('diagrams.create') }}" class="btn-primary">Create your first diagram</a>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($diagrams->hasPages())
    <nav class="pagination" aria-label="Pagination">
        @if($diagrams->previousPageUrl())
        <a href="{{ $diagrams->previousPageUrl() }}" class="btn-ghost">← Previous</a>
        @endif
        <span class="page-info">Page {{ $diagrams->currentPage() }} of {{ $diagrams->lastPage() }}</span>
        @if($diagrams->nextPageUrl())
        <a href="{{ $diagrams->nextPageUrl() }}" class="btn-ghost">Next →</a>
        @endif
    </nav>
    @endif
</main>

{{-- Data island --}}
<script id="app-config" type="application/json">
{
    "routes": {
        "store":  "{{ route('api.diagrams.store') }}",
        "export": "{{ route('api.diagrams.export', '__ULID__') }}"
    }
}
</script>

{{-- Mermaid for preview thumbnails --}}
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
(function () {
    'use strict';

    const cfg     = JSON.parse(document.getElementById('app-config').textContent);
    const csrf    = document.querySelector('meta[name="csrf-token"]').content;

    mermaid.initialize({ startOnLoad: false, theme: 'dark', securityLevel: 'loose' });

    // Render preview thumbnails
    document.querySelectorAll('.card-preview').forEach(async (el) => {
        const li    = el.closest('[data-ulid]');
        const ulid  = li.dataset.ulid;
        const card  = li;

        // Fetch diagram data if not server-rendered
        try {
            const res  = await fetch('/api/diagrams/' + ulid, {
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + getToken() }
            });
            if (!res.ok) return;
            const data = await res.json();

            if (data.mermaid_code) {
                const id  = 'prev-' + ulid;
                const { svg } = await mermaid.render(id, data.mermaid_code);
                el.innerHTML = svg;
            }
        } catch (_) {}
    });

    function getToken() {
        return '{{ session('api_token') }}' || localStorage.getItem('token') || '';
    }

    // Client-side filter (live search)
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', () => {
        const term = searchInput.value.toLowerCase();
        document.querySelectorAll('.diagram-card').forEach(card => {
            const title = card.querySelector('.card-title').textContent.toLowerCase();
            card.style.display = title.includes(term) ? '' : 'none';
        });
    });

    // Export buttons
    document.querySelectorAll('.export-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const ulid = btn.dataset.ulid;
            const fmt  = btn.dataset.format || 'svg';
            window.location.href = cfg.routes.export.replace('__ULID__', ulid) + '?format=' + fmt;
        });
    });
})();
</script>
</body>
</html>
