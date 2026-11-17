<?php

namespace App\Models;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
#[Hidden(['created_at', 'updated_at'])]
#[BidirektSchema(fields: [
    new BidirektField('id', type: 'integer'),
    new BidirektField('name', type: 'string'),
    new BidirektField('slug', type: 'string', description: 'The name in the category URL'),
])]
class Category extends Model
{
    /**
     * @return HasMany<Pet, $this>
     */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }
}
