@if (session('status'))
    <div class="jp-portal-alert jp-portal-alert--info">{{ is_string(session('status')) ? session('status') : 'Saved.' }}</div>
@endif
@if (session('warning'))
    <div class="jp-portal-alert jp-portal-alert--warn">{{ session('warning') }}</div>
@endif
@if (session('info'))
    <div class="jp-portal-alert jp-portal-alert--info">{{ session('info') }}</div>
@endif
@if ($errors->any())
    <div class="jp-portal-alert jp-portal-alert--warn">
        <ul style="margin:0;padding-left:1.2rem">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif
