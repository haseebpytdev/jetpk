@php
    $statusBadgeFor = static fn (string $status): string => match ($status) {
        'pending' => 'badge-soft-warning',
        'approved' => 'badge-soft-success',
        'rejected' => 'badge-soft-danger',
        'needs_more_info' => 'badge-soft-purple',
        default => 'badge-soft-neutral',
    };
    $statusLabelFor = static fn (string $status): string => match ($status) {
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'needs_more_info' => 'Needs info',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
    $duplicateKeys = $duplicateEmailKeys ?? [];
    $convertedKeys = $convertedEmailKeys ?? [];
    $duplicateCounts = $duplicateEmailCounts ?? [];
    $hasFilters = (bool) ($hasFilters ?? false);
@endphp

@if ($applications->count() === 0)
    <div class="jp-card__body">
        <div class="applications-empty-state" data-testid="ota-agent-applications-empty">
            @if ($hasFilters)
                <h3>No applications match your filters</h3>
                <p class="mb-0">Try a different status, date range, or duplicate filter.</p>
            @else
                <h3>No applications yet</h3>
                <p class="mb-3">New partner requests will appear here after agents submit the registration form.</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ route('agent.register') }}"
                       class="jp-btn jp-btn--primary btn-sm"
                       data-testid="ota-agent-applications-empty-registration">
                        View agent registration page
                    </a>
                    <a href="{{ route('admin.agents') }}"
                       class="jp-btn jp-btn--ghost btn-sm"
                       data-testid="ota-agent-applications-empty-back-agents">
                        Back to agents
                    </a>
                </div>
            @endif
        </div>
    </div>
@else
    <div class="applications-table-wrap">
        <table class="table applications-table mb-0" data-testid="ota-agent-applications-table">
            <thead>
                <tr>
                    <th class="col-applicant">Applicant</th>
                    <th class="col-company">Company</th>
                    <th class="col-contact">Contact</th>
                    <th class="col-status">Status</th>
                    <th class="col-submitted">Submitted</th>
                    <th class="col-flags">Flags</th>
                    <th class="col-action">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($applications as $application)
                    @php
                        $applicantName = trim($application->first_name.' '.$application->last_name);
                        $emailKey = strtolower((string) $application->email);
                        $isDuplicate = in_array($emailKey, $duplicateKeys, true);
                        $isConverted = in_array($emailKey, $convertedKeys, true);
                        $missingPhone = trim((string) $application->mobile) === '';
                        $duplicateCount = (int) ($duplicateCounts[$emailKey] ?? 0);
                        $status = (string) $application->status;
                    @endphp
                    <tr>
                        <td class="col-applicant" data-label="Applicant">
                            <a href="{{ route('admin.agent-applications.show', $application) }}"
                               class="application-primary">
                                {{ $applicantName !== '' ? $applicantName : '—' }}
                            </a>
                        </td>
                        <td class="col-company" data-label="Company">
                            {{ $application->company_name ?: '—' }}
                        </td>
                        <td class="col-contact" data-label="Contact">
                            <span class="application-contact-email">{{ $application->email }}</span>
                            @if (! $missingPhone)
                                <span class="application-contact-phone">{{ $application->mobile }}</span>
                            @endif
                        </td>
                        <td class="col-status" data-label="Status">
                            <span class="badge {{ $statusBadgeFor($status) }}"
                                  data-testid="ota-agent-application-status-{{ $status }}">
                                {{ $statusLabelFor($status) }}
                            </span>
                        </td>
                        <td class="col-submitted" data-label="Submitted">
                            {{ $application->created_at?->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td class="col-flags" data-label="Flags">
                            <div class="applications-flags">
                                @if ($isDuplicate)
                                    <span class="badge badge-soft-duplicate"
                                          data-testid="ota-agent-application-risk-duplicate"
                                          title="{{ $duplicateCount > 0 ? $duplicateCount.' applications from this email' : 'Duplicate email detected' }}">
                                        Duplicate email
                                    </span>
                                @endif
                                @if ($isConverted)
                                    <span class="badge badge-soft-converted"
                                          data-testid="ota-agent-application-risk-converted">
                                        Converted
                                    </span>
                                @endif
                                @if ($missingPhone)
                                    <span class="badge badge-soft-warning"
                                          data-testid="ota-agent-application-risk-missing-phone">
                                        Missing phone
                                    </span>
                                @endif
                                @if (! $isDuplicate && ! $isConverted && ! $missingPhone)
                                    <span class="text-secondary small">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="col-action" data-label="Action">
                            <a href="{{ route('admin.agent-applications.show', $application) }}"
                               class="jp-btn jp-btn--sm jp-btn--outline"
                               data-testid="ota-agent-application-action-open-review">
                                Open review
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif