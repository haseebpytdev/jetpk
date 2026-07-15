<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Bookings\PiaNdcEticketDeliveryService;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcCancellationException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcTicketingException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingStatusRefreshService;
use App\Services\Suppliers\PiaNdc\PiaNdcRetrieveService;
use App\Services\Suppliers\PiaNdc\PiaNdcTicketPreviewService;
use App\Services\Suppliers\PiaNdc\PiaNdcVoidTicketService;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AdminBookingSupplierActionAuditor;
use App\Support\Bookings\AdminPiaNdcTicketingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

trait HandlesPiaNdcTicketing
{
    public function previewPiaNdcTicket(
        Request $request,
        Booking $booking,
        PiaNdcBookingStatusRefreshService $refreshService,
        PiaNdcTicketPreviewService $previewService,
        PiaNdcRetrieveService $retrieveService,
        AdminPiaNdcTicketingPresenter $presenter,
    ): RedirectResponse {
        Gate::authorize('previewPiaNdcTicket', $booking);

        return $this->runPiaNdcTicketingAction(
            booking: $booking,
            presenter: $presenter,
            lockKey: 'ota:pia-ndc-ticket-preview:'.$booking->id,
            errorBag: 'pia_ndc_ticket_preview',
            confirmPhrase: '',
            request: $request,
            canRun: fn (Booking $booking, array $panel): bool => (bool) ($panel['can_preview'] ?? false),
            blockedMessage: fn (array $panel): string => (string) ($panel['preview_blocked_reason'] ?? 'Ticket preview is not available.'),
            execute: function (Booking $booking, SupplierConnection $connection) use ($refreshService, $previewService, $retrieveService, $request): void {
                $refreshService->refreshBooking($booking, $request->user(), 'admin_before_ticket_preview');
                $previewService->preview($booking->fresh() ?? $booking, $connection);
                try {
                    $retrieveService->retrieveAndSync($booking->fresh() ?? $booking, $connection);
                } catch (\Throwable) {
                    // Non-blocking: preview succeeded; PNR sync can be retried manually.
                }
            },
            successStatus: 'PIA NDC ticket preview completed successfully.',
        );
    }

    public function voidPiaNdcTicket(
        Request $request,
        Booking $booking,
        PiaNdcBookingStatusRefreshService $refreshService,
        PiaNdcVoidTicketService $voidTicketService,
        AdminPiaNdcTicketingPresenter $presenter,
    ): RedirectResponse {
        Gate::authorize('voidPiaNdcTicket', $booking);

        return $this->runPiaNdcTicketingAction(
            booking: $booking,
            presenter: $presenter,
            lockKey: 'ota:pia-ndc-void-ticket:'.$booking->id,
            errorBag: 'pia_ndc_void_ticket',
            confirmPhrase: PiaNdcVoidTicketService::VOID_CONFIRM_PHRASE,
            request: $request,
            canRun: fn (Booking $booking, array $panel): bool => (bool) ($panel['can_void'] ?? false),
            blockedMessage: fn (array $panel): string => (string) ($panel['void_blocked_reason'] ?? 'Void ticket is not available.'),
            execute: function (Booking $booking, SupplierConnection $connection) use ($refreshService, $voidTicketService, $request): void {
                $refreshService->refreshBooking($booking, $request->user(), 'admin_before_void_ticket');
                $voidTicketService->voidTicket($booking->fresh() ?? $booking, $connection, $request->user());
            },
            successStatus: 'PIA NDC ticket void completed successfully.',
            requireReason: true,
        );
    }

    public function resendPiaNdcEticket(
        Request $request,
        Booking $booking,
        PiaNdcEticketDeliveryService $eticketDeliveryService,
        AdminPiaNdcTicketingPresenter $presenter,
    ): RedirectResponse {
        Gate::authorize('resendPiaNdcEticket', $booking);

        $validated = $request->validate([
            'confirm_phrase' => ['required', 'string'],
            'operator_note' => ['nullable', 'string', 'max:500'],
        ]);

        if (trim((string) $validated['confirm_phrase']) !== PiaNdcEticketDeliveryService::RESEND_CONFIRM_PHRASE) {
            return back()->withErrors(['pia_ndc_eticket_resend' => 'Confirmation phrase is incorrect.']);
        }

        $ticketingEligible = app(TicketingService::class)->isBookingEligibleForTicketing($booking);
        $panel = $presenter->panel($booking, $ticketingEligible);
        if (! ($panel['can_resend_eticket'] ?? false)) {
            return back()->withErrors([
                'pia_ndc_eticket_resend' => (string) ($panel['resend_blocked_reason'] ?? 'E-ticket resend is not available.'),
            ]);
        }

        $result = $eticketDeliveryService->resend(
            $booking,
            $request->user(),
            trim((string) ($validated['operator_note'] ?? '')) ?: null,
        );

        if (! ($result['sent'] ?? false)) {
            return back()->withErrors(['pia_ndc_eticket_resend' => (string) ($result['message'] ?? 'E-ticket resend failed.')]);
        }

        return back()->with('status', (string) ($result['message'] ?? 'E-ticket email resent.'));
    }

    /**
     * @param  callable(Booking, SupplierConnection): void  $execute
     * @param  callable(Booking, array<string, mixed>): bool  $canRun
     * @param  callable(array<string, mixed>): string  $blockedMessage
     */
    private function runPiaNdcTicketingAction(
        Booking $booking,
        AdminPiaNdcTicketingPresenter $presenter,
        string $lockKey,
        string $errorBag,
        string $confirmPhrase,
        Request $request,
        callable $canRun,
        callable $blockedMessage,
        callable $execute,
        string $successStatus,
        bool $requireReason = false,
    ): RedirectResponse {
        $rules = [];
        if ($requireReason) {
            $rules['operator_reason'] = ['required', 'string', 'min:3', 'max:500'];
        }
        if ($confirmPhrase === '') {
            $rules['admin_confirm_reviewed'] = ['accepted'];
        } else {
            $rules['confirm_phrase'] = ['required', 'string'];
        }
        $validated = $request->validate($rules);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return back()->withErrors([$errorBag => 'This booking is not a PIA NDC supplier booking.']);
        }

        if ($confirmPhrase !== '' && trim((string) $validated['confirm_phrase']) !== $confirmPhrase) {
            return back()->withErrors([$errorBag => 'Confirmation phrase is incorrect.']);
        }

        if ($confirmPhrase === '' && ! $request->boolean('admin_confirm_reviewed')) {
            return back()->withErrors([$errorBag => 'Confirm you reviewed this booking before continuing.']);
        }

        $ticketingEligible = app(TicketingService::class)->isBookingEligibleForTicketing($booking);
        $panel = $presenter->panel($booking, $ticketingEligible);
        if (! $canRun($booking, $panel)) {
            return back()->withErrors([$errorBag => $blockedMessage($panel)]);
        }

        if (($panel['requires_admin_confirm'] ?? false) && ! $request->boolean('admin_confirm_reviewed')) {
            return back()->withErrors([$errorBag => 'Confirm you reviewed this booking before continuing.']);
        }

        $connection = $this->resolvePiaNdcConnectionForBooking($booking);
        if ($connection === null) {
            return back()->withErrors([$errorBag => 'PIA NDC supplier connection not found.']);
        }

        $lock = Cache::lock($lockKey, 120);
        if (! $lock->get()) {
            return back()->withErrors([$errorBag => 'PIA NDC action is already in progress for this booking.']);
        }

        $preStatus = (string) ($booking->ticketing_status ?? 'not_started');
        $actionName = match ($lockKey) {
            str_starts_with($lockKey, 'ota:pia-ndc-ticket-preview:') => 'ticket_preview',
            str_starts_with($lockKey, 'ota:pia-ndc-void-ticket:') => 'void_ticket',
            default => 'pia_ndc_action',
        };

        try {
            $execute($booking, $connection);
        } catch (PiaNdcValidationException|PiaNdcTicketingException|PiaNdcCancellationException $exception) {
            app(AdminBookingSupplierActionAuditor::class)->log(
                $booking,
                $request->user(),
                $actionName,
                true,
                $preStatus,
                $preStatus,
                true,
                'failed',
                $exception->safeMessage,
            );

            return back()->withErrors([$errorBag => $exception->safeMessage]);
        } catch (\Throwable $e) {
            app(AdminBookingSupplierActionAuditor::class)->log(
                $booking,
                $request->user(),
                $actionName,
                true,
                $preStatus,
                $preStatus,
                true,
                'failed',
                Str::limit($e->getMessage(), 120, ''),
            );

            return back()->withErrors([
                $errorBag => 'PIA NDC action failed. '.Str::limit($e->getMessage(), 120, ''),
            ]);
        } finally {
            $lock->release();
        }

        $fresh = $booking->fresh() ?? $booking;
        app(AdminBookingSupplierActionAuditor::class)->log(
            $fresh,
            $request->user(),
            $actionName,
            true,
            $preStatus,
            (string) ($fresh->ticketing_status ?? $preStatus),
            true,
            'success',
        );

        return back()->with('status', $successStatus);
    }

    private function resolvePiaNdcConnectionForBooking(Booking $booking): ?SupplierConnection
    {
        $booking->loadMissing('latestSupplierBooking.supplierConnection');
        $connection = $booking->latestSupplierBooking?->supplierConnection;
        if ($connection !== null) {
            return $connection;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId <= 0) {
            return null;
        }

        return SupplierConnection::query()->find($connectionId);
    }
}
