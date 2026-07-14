<?php

namespace App\Http\Requests\Frontend;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventoryAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GroupTicketingPassengersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seat_count' => ['required', 'integer', 'min:1', 'max:20'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.title' => ['required', 'string', 'in:Mr,Mrs,Ms,Miss,Dr,Mstr'],
            'passengers.*.first_name' => ['required', 'string', 'max:80'],
            'passengers.*.last_name' => ['required', 'string', 'max:80'],
            'passengers.*.gender' => ['required', 'string', 'in:male,female,other'],
            'passengers.*.date_of_birth' => ['required', 'date', 'before:today'],
            'passengers.*.nationality' => ['required', 'string', 'max:80'],
            'passengers.*.document_type' => ['required', 'string', 'in:passport,national_id'],
            'passengers.*.passport_number' => ['required', 'string', 'max:40'],
            'passengers.*.passport_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'passengers.*.passport_expiry' => ['required', 'date', 'after:today'],
            'passengers.*.passenger_type' => ['nullable', 'string', 'in:adult,child,infant'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $seatCount = (int) $this->input('seat_count', 0);
            $passengers = $this->input('passengers', []);
            if (is_array($passengers) && count($passengers) !== $seatCount) {
                $validator->errors()->add('passengers', 'Passenger details must match the number of seats selected.');
            }

            /** @var GroupInventory|null $inventory */
            $inventory = $this->route('inventory');
            if ($inventory instanceof GroupInventory && $seatCount > $inventory->availableSeats()) {
                $available = $inventory->availableSeats();
                $validator->errors()->add(
                    'seat_count',
                    GroupInventoryAvailabilityService::insufficientSeatsMessage($available),
                );
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function passengerRows(): array
    {
        $rows = $this->input('passengers', []);

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function contactDetails(): array
    {
        return [
            'contact_name' => $this->input('contact_name'),
            'contact_email' => $this->input('contact_email'),
            'contact_phone' => $this->input('contact_phone'),
        ];
    }
}
