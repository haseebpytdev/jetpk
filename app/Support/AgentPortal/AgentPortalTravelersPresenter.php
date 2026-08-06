<?php

namespace App\Support\AgentPortal;

use App\Models\SavedTraveler;
use App\Support\Geo\CountryList;
use App\Support\Travel\TravelDocumentFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Agent saved-traveler JSON for Next.js dashboard.
 */
class AgentPortalTravelersPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(LengthAwarePaginator $travelers): array
    {
        return [
            'ok' => true,
            'travelers' => collect($travelers->items())
                ->map(fn (SavedTraveler $traveler) => $this->presentTravelerForList($traveler))
                ->values()
                ->all(),
            'pagination' => $this->paginationMeta($travelers),
            'countries' => CountryList::forSelect(),
            'create_url' => '/laravel/agent/travelers',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForm(?SavedTraveler $traveler = null): array
    {
        return [
            'ok' => true,
            'traveler' => $traveler !== null ? $this->presentTravelerForForm($traveler) : $this->emptyTraveler(),
            'countries' => CountryList::forSelect(),
            'submit_url' => $traveler !== null
                ? '/laravel/agent/travelers/'.$traveler->id
                : '/laravel/agent/travelers',
            'method' => $traveler !== null ? 'PATCH' : 'POST',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentStored(SavedTraveler $traveler): array
    {
        return [
            'ok' => true,
            'traveler' => $this->presentTravelerForList($traveler),
            'redirect_url' => '/agent/travelers',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTravelerForList(SavedTraveler $traveler): array
    {
        return [
            'id' => $traveler->id,
            'title' => $traveler->title,
            'first_name' => $traveler->first_name,
            'last_name' => $traveler->last_name,
            'gender' => $traveler->gender,
            'date_of_birth' => $traveler->date_of_birth?->toDateString(),
            'nationality' => $traveler->nationality,
            'document_type' => $traveler->document_type,
            'document_number_masked' => TravelDocumentFormatter::maskDocumentForList($traveler->document_number),
            'document_expiry' => $traveler->document_expiry?->toDateString(),
            'issuing_country' => $traveler->issuing_country,
            'phone' => $traveler->phone,
            'email' => $traveler->email,
            'is_default' => (bool) $traveler->is_default,
            'edit_url' => '/agent/travelers/'.$traveler->id.'/edit',
            'delete_url' => '/laravel/agent/travelers/'.$traveler->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTravelerForForm(SavedTraveler $traveler): array
    {
        return [
            'id' => $traveler->id,
            'title' => $traveler->title,
            'first_name' => $traveler->first_name,
            'last_name' => $traveler->last_name,
            'gender' => $traveler->gender,
            'date_of_birth' => $traveler->date_of_birth?->toDateString(),
            'nationality' => $traveler->nationality,
            'document_type' => $traveler->document_type,
            'document_number' => $traveler->document_number,
            'document_number_masked' => TravelDocumentFormatter::maskDocumentForList($traveler->document_number),
            'document_expiry' => $traveler->document_expiry?->toDateString(),
            'issuing_country' => $traveler->issuing_country,
            'phone' => $traveler->phone,
            'email' => $traveler->email,
            'is_default' => (bool) $traveler->is_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTraveler(): array
    {
        return [
            'id' => null,
            'title' => 'Mr',
            'first_name' => '',
            'last_name' => '',
            'gender' => 'male',
            'date_of_birth' => null,
            'nationality' => 'PK',
            'document_type' => 'passport',
            'document_number' => null,
            'document_number_masked' => null,
            'document_expiry' => null,
            'issuing_country' => 'PK',
            'phone' => null,
            'email' => null,
            'is_default' => false,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
