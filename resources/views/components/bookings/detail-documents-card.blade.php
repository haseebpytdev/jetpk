@props([
    'summary',
    'audience' => 'customer',
    'guest' => false,
    'guestToken' => null,
    'viewerMode' => 'customer',
    'shell' => 'account',
])

@php
    $isAccount = $shell === 'account';
    $isGuest = $viewerMode === 'guest';
    $documents = collect($summary['documents'] ?? [])->filter(fn ($row) => ($row['status'] ?? '') === 'available' && ! empty($row['document']))->values()->all();
    $availableDocs = collect($documents);
    $hasAnyAvailable = $availableDocs->isNotEmpty();
    $canDownload = in_array($viewerMode, ['customer', 'guest'], true);
    $downloadUrlFor = function ($document) use ($guest, $guestToken) {
        if ($guest) {
            return route('guest.documents.download', ['bookingDocument' => $document, 'token' => $guestToken]);
        }

        return route('customer.documents.download', $document);
    };
@endphp

<div class="{{ $isAccount ? 'ota-account-card mb-3' : 'card mb-3 border-0 shadow-sm' }}">
    <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }} d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">Documents</h3>
        @if ($canDownload)
            <div class="dropdown">
                <button class="{{ $isAccount ? 'ota-account-btn ota-account-btn--secondary ota-account-btn--sm' : 'btn btn-sm btn-outline-secondary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Bulk actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        @if ($hasAnyAvailable)
                            <button type="button" class="dropdown-item" data-testid="booking-documents-download-all"
                                onclick="window.otaBookingDocumentsDownloadAll && window.otaBookingDocumentsDownloadAll(this)"
                                data-download-urls="{{ $availableDocs->map(fn ($row) => $downloadUrlFor($row['document']))->values()->toJson() }}">
                                Download all available
                            </button>
                        @else
                            <span class="dropdown-item disabled text-secondary" data-testid="booking-documents-download-all-disabled">Download all available</span>
                        @endif
                    </li>
                    @unless ($isGuest)
                        <li>
                            <span class="dropdown-item disabled text-secondary" title="Email all PDFs is not available from this portal yet." data-testid="booking-documents-email-all-disabled">
                                Email all PDFs
                            </span>
                        </li>
                        <li>
                            <span class="dropdown-item disabled text-secondary" title="Email summary without PDF is not available from this portal yet." data-testid="booking-documents-email-summary-disabled">
                                Email summary without PDF
                            </span>
                        </li>
                    @endunless
                </ul>
            </div>
        @endif
    </div>
    <div class="{{ $isAccount ? 'ota-account-card__body ota-account-card__body--flush' : 'card-body p-0' }}" data-testid="booking-documents-center">
        <div class="ota-booking-detail-doc-list">
            @foreach ($documents as $docRow)
                @php
                    $document = $docRow['document'] ?? null;
                @endphp
                <div class="ota-booking-detail-doc-row" data-testid="booking-document-row-{{ $docRow['key'] }}">
                    <div class="ota-booking-detail-doc-row__main">
                        <div class="ota-booking-detail-doc-row__title">{{ $docRow['label'] }}</div>
                        @if ($audience === 'agent' && ! empty($docRow['agent_note']))
                            <div class="ota-booking-detail-doc-row__hint small text-secondary">{{ $docRow['agent_note'] }}</div>
                        @endif
                    </div>
                    <div class="ota-booking-detail-doc-row__meta">
                        <span class="{{ $isAccount ? 'ota-account-badge ota-account-badge--success' : 'badge bg-success-lt' }}">Available</span>
                    </div>
                    <div class="ota-booking-detail-doc-row__actions">
                        @if ($canDownload && $document)
                            <a class="ota-booking-detail-doc-action" href="{{ $downloadUrlFor($document) }}" title="Download {{ $docRow['label'] }}" data-testid="booking-document-download-{{ $docRow['key'] }}">
                                <i class="ti ti-download" aria-hidden="true"></i>
                                <span class="ota-booking-detail-doc-action__label">Download</span>
                            </a>
                        @endif
                        @unless ($isGuest)
                            <span class="ota-booking-detail-doc-action is-disabled" title="Email document as PDF is not available from this portal yet." data-testid="booking-document-email-{{ $docRow['key'] }}-disabled">
                                <i class="ti ti-mail" aria-hidden="true"></i>
                                <span class="ota-booking-detail-doc-action__label">Email</span>
                            </span>
                            <span class="ota-booking-detail-doc-action is-disabled" title="Send or share document is not available from this portal yet." data-testid="booking-document-share-{{ $docRow['key'] }}-disabled">
                                <i class="ti ti-send" aria-hidden="true"></i>
                                <span class="ota-booking-detail-doc-action__label">Send</span>
                            </span>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
        @if (! $hasAnyAvailable && $audience !== 'agent')
            <p class="small text-secondary mb-0 px-3 py-3">Documents will appear here when they are ready for download.</p>
        @elseif (! $hasAnyAvailable && $audience === 'agent')
            <p class="small text-secondary mb-0 px-3 py-3">No customer documents are available yet for this booking.</p>
        @endif
        @if ($canDownload && $hasAnyAvailable)
            <div class="ota-booking-detail-doc-bulk {{ $isAccount ? 'ota-account-card__footer' : 'card-footer bg-white border-top' }}">
                <button type="button" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--secondary ota-account-btn--block' : 'btn btn-outline-secondary w-100 mb-2' }}"
                    data-testid="booking-documents-download-all-btn"
                    onclick="window.otaBookingDocumentsDownloadAll && window.otaBookingDocumentsDownloadAll(this)"
                    data-download-urls="{{ $availableDocs->map(fn ($row) => $downloadUrlFor($row['document']))->values()->toJson() }}">
                    <i class="ti ti-download me-1" aria-hidden="true"></i> Download all available
                </button>
                @unless ($isGuest)
                    <button type="button" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--secondary ota-account-btn--block' : 'btn btn-outline-secondary w-100' }} disabled" disabled
                        title="Email all PDFs is not available from this portal yet." data-testid="booking-documents-email-all-btn">
                        <i class="ti ti-mail me-1" aria-hidden="true"></i> Email all PDFs
                    </button>
                @endunless
            </div>
        @endif
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.otaBookingDocumentsDownloadAll = function (trigger) {
                var raw = trigger.getAttribute('data-download-urls');
                if (!raw) return;
                try {
                    var urls = JSON.parse(raw);
                    urls.forEach(function (url, index) {
                        setTimeout(function () {
                            var frame = document.createElement('iframe');
                            frame.style.display = 'none';
                            frame.src = url;
                            document.body.appendChild(frame);
                            setTimeout(function () { frame.remove(); }, 15000);
                        }, index * 400);
                    });
                } catch (e) {}
            };
        </script>
    @endpush
@endonce
