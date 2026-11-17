<?php

namespace App\Http\Requests;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BidirektSchema(name: 'NewPet', fields: [
    new BidirektField('name', type: 'string'),
    new BidirektField('weight', type: 'number', description: 'In kilograms'),
    new BidirektField('ownerId', type: 'integer'),
    new BidirektField('categoryId', type: 'integer', optional: true, nullable: true),
    new BidirektField('tags', type: 'array', items: 'string', optional: true),
])]
class StorePetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:0'],
            'ownerId' => ['required', 'integer'],
            'categoryId' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => $this->string('name')->toString() |> trim(...)]);
        }
    }
}
