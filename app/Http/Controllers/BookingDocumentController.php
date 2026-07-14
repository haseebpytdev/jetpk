<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingPayment;
use App\Services\Documents\BookingDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookingDocumentController extends Controller
{
    public function __construct(
        protected BookingDocumentService $documentService,
    ) {}

    public function bookingConfirmation(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('create', [BookingDocument::class, $booking]);
        try {
            $this->documentService->generateBookingConfirmation($booking, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['documents' => $e->getMessage()]);
        }

        return back()->with('status', 'document-generated');
    }

    public function invoice(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('create', [BookingDocument::class, $booking]);
        try {
            $this->documentService->generateInvoice($booking, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['documents' => $e->getMessage()]);
        }

        return back()->with('status', 'document-generated');
    }

    public function ticketItinerary(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('create', [BookingDocument::class, $booking]);
        try {
            $this->documentService->generateTicketItinerary($booking, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['documents' => $e->getMessage()]);
        }

        return back()->with('status', 'document-generated');
    }

    public function paymentReceipt(Request $request, BookingPayment $bookingPayment): RedirectResponse
    {
        Gate::authorize('create', [BookingDocument::class, $bookingPayment->booking]);
        try {
            $this->documentService->generatePaymentReceipt($bookingPayment, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['documents' => $e->getMessage()]);
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

    public function refundNote(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('create', [BookingDocument::class, $booking]);
        try {
            $this->documentService->generateRefundNote($booking, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['documents' => $e->getMessage()]);
        }

        return back()->with('status', 'document-generated');
    }

    public function cancellationConfirmation(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('create', [BookingDocument::class, $booking]);
        try {
            $this->documentService->generateCancellationConfirmation($booking, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['documents' => $e->getMessage()]);
        }

        return back()->with('status', 'document-generated');
    }
}
