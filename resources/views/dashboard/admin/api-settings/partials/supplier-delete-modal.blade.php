@if ($isEdit && isset($deleteAction))
    <form method="POST" action="{{ $deleteAction }}" class="jp-is-hidden" id="ota-delete-connection-form">
        @csrf
        @method('DELETE')
    </form>
    <div id="ota-delete-connection-modal" class="ota-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="ota-delete-connection-modal-title" hidden>
        <div class="ota-confirm-modal__backdrop" data-close-delete-confirm></div>
        <div class="ota-confirm-modal__panel" role="document">
            <h4 id="ota-delete-connection-modal-title" class="ota-confirm-modal__title">Delete {{ $providerLabel }} connection</h4>
            <p class="ota-confirm-modal__message">This permanently removes the connection. Continue?</p>
            <div class="ota-confirm-modal__actions">
                <button type="submit" class="jp-btn jp-btn--danger" form="ota-delete-connection-form">Delete</button>
                <button type="button" class="jp-btn jp-btn--ghost" data-close-delete-confirm>Cancel</button>
            </div>
        </div>
    </div>
@endif
