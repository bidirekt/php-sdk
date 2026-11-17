<?php

namespace App\Http\Requests;

use App\Enums\PetStatus;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[BidirektSchema(name: 'StatusChange', fields: [
    new BidirektField('status', type: PetStatus::class),
])]
class UpdatePetStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PetStatus::class)],
        ];
    }
}
