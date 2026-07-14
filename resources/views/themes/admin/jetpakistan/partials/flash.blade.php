@if (session('status') === 'supplier-status-toggled')
    <div class="jp-alert jp-alert--info">Supplier connection status updated.</div>
@elseif (session('status') === 'supplier-connection-updated')
    <div class="jp-alert jp-alert--info">Supplier connection saved.</div>
@elseif (session('status') === 'supplier-connection-created')
    <div class="jp-alert jp-alert--info">Supplier connection created.</div>
@elseif (session('status') === 'supplier-connection-deleted')
    <div class="jp-alert jp-alert--info">Supplier connection deleted.</div>
@elseif (session('status') === 'supplier-test-ran')
    <div class="jp-alert jp-alert--info">Readiness check completed.@if (session('test_result.status')) Last status: {{ session('test_result.status') }}.@endif</div>
@elseif (session('status'))
    <div class="jp-alert jp-alert--info">{{ session('status') }}</div>
@endif

@if (session('warning'))
    <div class="jp-alert jp-alert--warn">{{ session('warning') }}</div>
@endif

@if (session('info'))
    <div class="jp-alert jp-alert--info">{{ session('info') }}</div>
@endif

@if ($errors->any())
    <div class="jp-alert jp-alert--warn">
        <ul style="margin: 0; padding-left: 1.2rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif
