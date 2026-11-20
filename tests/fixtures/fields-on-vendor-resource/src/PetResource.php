<?php

namespace App\Http\Resources;

use Acme\Http\JsonResource;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(fields: [
    new BidirektField('id', type: 'integer'),
    new BidirektField('name', type: 'string'),
    new BidirektField('tag', type: 'string', nullable: true),
    new BidirektField('photoUrls', type: 'array', items: 'string', optional: true),
    new BidirektField('status', type: 'string', description: 'available pending or sold'),
])]
final class PetResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'tag' => $this->resource->tag,
            'photoUrls' => $this->when($request->has('photos'), $this->resource->photoUrls),
            'status' => $this->resource->status,
        ];
    }
}
