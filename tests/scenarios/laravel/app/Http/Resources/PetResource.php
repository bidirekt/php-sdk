<?php

namespace App\Http\Resources;

use App\Enums\PetStatus;
use App\Models\Category;
use App\Models\Pet;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin Pet
 */
#[BidirektSchema(name: 'Pet', fields: [
    new BidirektField('id', type: 'integer'),
    new BidirektField('name', type: 'string'),
    new BidirektField('status', type: PetStatus::class),
    new BidirektField('weight', type: 'number', description: 'In kilograms'),
    new BidirektField('ownerId', type: 'integer'),
    new BidirektField('category', type: Category::class, optional: true, nullable: true),
    new BidirektField('tags', type: 'array', items: 'string'),
    new BidirektField('createdAt', type: 'string', description: 'ISO 8601 in UTC'),
])]
class PetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'weight' => $this->weight,
            'ownerId' => $this->owner_id,
            'category' => $this->whenLoaded('category'),
            'tags' => $this->tags ?? [],
            'createdAt' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
