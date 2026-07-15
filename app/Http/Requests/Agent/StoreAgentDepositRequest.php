<?php

namespace App\Http\Requests\Agent;

use App\Models\AgentDepositRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreAgentDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', AgentDepositRequest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'agent_note' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,webp'],
        ];
    }
}
