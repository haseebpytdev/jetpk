<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\SupplierProvider;
use App\Http\Controllers\Controller;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Checkout\OneApiCheckoutFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One API checkout steps: catalog, selections, final price (server-validated).
 */
class OneApiCheckoutController extends Controller
{
    public function __construct(
        private readonly OneApiCheckoutFlowService $checkoutFlow,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workflow_context_id' => 'required|string',
            'supplier_connection_id' => 'required|integer',
        ]);

        $connection = $this->resolveConnection((int) $validated['supplier_connection_id']);
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $data = $this->checkoutFlow->loadCatalog(
            $connection,
            (string) $validated['workflow_context_id'],
            [],
            $user,
            (string) $request->session()->getId(),
        );

        return response()->json($data);
    }

    public function saveSelections(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workflow_context_id' => 'required|string',
            'supplier_connection_id' => 'required|integer',
            'bundles' => 'nullable|array',
            'bundle_selection_ids' => 'nullable|array',
            'meal_selection_ids' => 'nullable|array',
            'seat_selection_ids' => 'nullable|array',
            'baggage' => 'nullable|array',
            'meals' => 'nullable|array',
            'seats' => 'nullable|array',
            'acknowledge_reprice' => 'nullable|boolean',
            'client_total' => 'prohibited',
            'posted_supplier_amount' => 'prohibited',
            'fixture_path' => 'prohibited',
            'fixture_paths' => 'prohibited',
            'fixture_key' => 'prohibited',
        ]);

        $connection = $this->resolveConnection((int) $validated['supplier_connection_id']);
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $result = $this->checkoutFlow->saveSelectionsAndFinalPrice(
            $connection,
            (string) $validated['workflow_context_id'],
            $validated,
            [],
            $user,
            (string) $request->session()->getId(),
        );

        return response()->json($result);
    }

    /** @deprecated Use catalog() */
    public function showExtras(Request $request): JsonResponse
    {
        return $this->catalog($request);
    }

    private function resolveConnection(int $id): SupplierConnection
    {
        $connection = SupplierConnection::query()->findOrFail($id);
        if ($connection->provider !== SupplierProvider::OneApi) {
            abort(422, 'Invalid supplier connection.');
        }

        return $connection;
    }
}
