<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingPayment;
use App\Services\Documents\BookingDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookingDocumentController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected BookingDocumentService $documentService,
    ) {}

    public function bookingConfirmation(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        return $this->generate($request, $booking, fn () => $this->documentService->generateBookingConfirmation($booking, $request->user()));
    }

    public function invoice(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        return $this->generate($request, $booking, fn () => $this->documentService->generateInvoice($booking, $request->user()));
    }

    public function ticketItinerary(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        return $this->generate($request, $booking, fn () => $this->documentService->generateTicketItinerary($booking, $request->user()));
    }

    public function paymentReceipt(Request $request, BookingPayment $bookingPayment): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', [BookingDocument::class, $bookingPayment->booking]);
        try {
            $document = $this->documentService->generatePaymentReceipt($bookingPayment, $request->user());
        } catch (RuntimeException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 422, 'document_generation_failed');
            }

            return back()->withErrors(['documents' => $e->getMessage()]);
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'document-generated',
                'document' => $this->presentDocument($request, $document),
            ]);
        }

        return back()->with('status', 'document-generated');
    }

    public function download(BookingDocument $bookingDocument): BinaryFileResponse
    {
        Gate::authorize('view', $bookingDocument);
        if ($bookingDocument->file_path === null || ! Storage::disk('local')->exists($bookingDocument->file_path)) {
            abort(404);
        }

        return response()->download(
            Storage::disk('local')->path($bookingDocument->file_path),
            basename((string) $bookingDocument->file_path)
        );
    }

    public function refundNote(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        return $this->generate($request, $booking, fn () => $this->documentService->generateRefundNote($booking, $request->user()));
    }

    public function cancellationConfirmation(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        return $this->generate($request, $booking, fn () => $this->documentService->generateCancellationConfirmation($booking, $request->user()));
    }

    /**
     * @param  callable(): BookingDocument  $generator
     */
    protected function generate(Request $request, Booking $booking, callable $generator): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', [BookingDocument::class, $booking]);
        try {
            $document = $generator();
        } catch (RuntimeException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 422, 'document_generation_failed');
            }

            return back()->withErrors(['documents' => $e->getMessage()]);
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'document-generated',
                'document' => $this->presentDocument($request, $document),
            ]);
        }

        return back()->with('status', 'document-generated');
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentDocument(Request $request, BookingDocument $document): array
    {
        $prefix = $request->is('staff/*') || str_starts_with(trim($request->path(), '/'), 'staff/')
            ? 'staff'
            : 'admin';

        return [
            'id' => (string) $document->id,
            'document_type' => (string) ($document->document_type?->value ?? $document->document_type ?? 'document'),
            'title' => (string) ($document->title ?: $document->document_number ?: 'Document'),
            'status' => (string) ($document->status?->value ?? $document->status ?? 'generated'),
            'download_url' => route("{$prefix}.bookings.documents.download", $document),
        ];
    }
}
