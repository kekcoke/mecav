<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create New Diagram — Mecav</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="editor-layout">

<header class="dashboard-header">
    <div class="header-left">
        <a href="{{ route('diagrams.index') }}" class="btn-ghost">← Back</a>
        <h1>New Diagram</h1>
    </div>
</header>

<main class="container">
    <div class="card">
        <form action="{{ route('api.diagrams.store') }}" method="POST" id="createForm">
            @csrf
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" required placeholder="e.g. System Architecture">
            </div>

            <div class="form-group">
                <label for="diagram_type">Type</label>
                <select name="diagram_type" id="diagram_type">
                    <option value="flowchart">Flowchart</option>
                    <option value="sequence">Sequence Diagram</option>
                    <option value="class">Class Diagram</option>
                    <option value="er">ER Diagram</option>
                </select>
            </div>

            <input type="hidden" name="mermaid_code" value="flowchart TD\n    Start --> End">
            <input type="hidden" name="ai_enabled" value="1">

            <div class="form-actions">
                <button type="submit" class="btn-primary">Create & Open Editor</button>
            </div>
        </form>
    </div>
</main>

<script>
document.getElementById('createForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            const result = await response.json();
            window.location.href = `/diagrams/${result.ulid}`;
        } else {
            alert('Failed to create diagram. Please check your input.');
        }
    } catch (error) {
        console.error('Error:', error);
    }
});
</script>
</body>
</html>
