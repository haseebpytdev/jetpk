@extends(client_layout('dashboard', 'admin'))

@section('title', 'Create custom page')

@section('content')
<div class="jp-card">
    <h1>Create custom content page</h1>
    <form method="post" action="{{ route('admin.page-settings.custom-pages.store') }}" class="jp-stack">
        @csrf
        <label class="jp-label">Internal name</label>
        <input class="jp-input" name="internal_name" value="{{ old('internal_name') }}" required>
        <label class="jp-label">Public title</label>
        <input class="jp-input" name="public_title" value="{{ old('public_title') }}" required>
        <label class="jp-label">Slug</label>
        <input class="jp-input" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
        <label class="jp-label">Navigation label</label>
        <input class="jp-input" name="nav_label" value="{{ old('nav_label') }}">
        <button type="submit" class="jp-btn">Create page</button>
    </form>
</div>
@endsection
