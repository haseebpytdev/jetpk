@extends(client_layout('dashboard', 'admin'))

@section('title', 'Media Library')

@section('page-header')
    <h1 class="jp-page-title">Settings / Media Library</h1>
@endsection

@section('content')
    @if (session('status'))
        <div class="jp-alert jp-alert--success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ route('admin.settings.media.store') }}" enctype="multipart/form-data" class="jp-card">
        @csrf
        <div class="card-body jp-form-grid jp-form-grid--filter">
            <div class="col-md-5"><label class="jp-label">Image</label><input type="file" class="jp-control" name="file" required></div>
            <div class="col-md-3"><label class="jp-label">Collection</label><select class="jp-control" name="collection"><option value="general">general</option><option value="branding">branding</option><option value="homepage">homepage</option></select></div>
            <div class="col-md-3"><label class="jp-label">Alt text</label><input class="jp-control" name="alt_text"></div>
            <div class="col-md-1"><button class="jp-btn jp-btn--primary w-100">Upload</button></div>
        </div>
    </form>

    <div class="jp-card">
        <div class="table-responsive">
            <table class="table card-jp-table">
                <thead><tr><th>Preview</th><th>Name</th><th>Collection</th><th>Path</th><th></th></tr></thead>
                <tbody>
                @forelse($mediaItems as $media)
                    <tr>
                        <td><img src="{{ asset('storage/'.$media->file_path) }}" alt="" style="width:70px;height:45px;object-fit:cover"></td>
                        <td>{{ $media->file_name }}</td>
                        <td>{{ $media->collection }}</td>
                        <td><code>{{ $media->file_path }}</code></td>
                        <td>
                            <form method="post" action="{{ route('admin.settings.media.destroy', $media) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-secondary">No media uploaded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="jp-card__body">{{ $mediaItems->links() }}</div>
    </div>
@endsection

