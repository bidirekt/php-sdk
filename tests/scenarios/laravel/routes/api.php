<?php

use App\Http\Controllers\PetController;
use App\Http\Resources\PetResource;
use App\Models\Category;
use App\Models\Pet;
use Bidirekt\PhpSdk\BidirektProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::apiResource('pets', PetController::class)->only(['index', 'store', 'show', 'destroy']);
Route::put('/pets/{pet}/status', [PetController::class, 'updateStatus']);

Route::get(
    '/categories',
    #[BidirektProvider(method: 'GET', path: '/api/categories', responses: [200 => [Category::class]])]
    fn () => Category::query()->orderBy('name')->get(),
);

Route::get(
    '/categories/{category}',
    #[BidirektProvider(method: 'GET', path: '/api/categories/*', responses: [200 => Category::class, 404 => 'ErrorMessage'])]
    fn (Category $category) => $category,
);

Route::prefix('owners')->group(function () {
    /**
     * Lists the pets of one owner.
     *
     * @bidirekt provides GET /api/owners/*\/pets
     * @bidirekt response 200 PetCollection
     */
    Route::get('/{ownerId}/pets', function (int $ownerId) {
        return PetResource::collection(Pet::with('category')->where('owner_id', $ownerId)->orderBy('id')->get());
    })->whereNumber('ownerId');
});

/**
 * @bidirekt provides GET /api/health
 * @bidirekt response 200 Health
 * @bidirekt response 503 Health
 * @bidirekt schema Health
 * @bidirekt field status type=string description="ok or down when the database does not answer"
 * @bidirekt field database type=boolean
 */
Route::get('/health', function () {
    try {
        DB::select('select 1');
        $database = true;
    } catch (Throwable) {
        $database = false;
    }

    return response()->json(['status' => $database ? 'ok' : 'down', 'database' => $database], $database ? 200 : 503);
});
