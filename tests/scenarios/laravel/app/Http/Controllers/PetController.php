<?php

namespace App\Http\Controllers;

use App\Enums\PetStatus;
use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetStatusRequest;
use App\Http\Resources\PetResource;
use App\Models\Pet;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PetController extends Controller
{
    #[BidirektProvider(method: 'GET', path: '/api/pets', responses: [200 => 'PetCollection'])]
    #[BidirektSchema(name: 'PetCollection', fields: [
        new BidirektField('data', type: 'array', items: PetResource::class),
    ])]
    public function index(): AnonymousResourceCollection
    {
        return PetResource::collection(Pet::with('category')->orderBy('id')->get());
    }

    #[BidirektProvider(
        method: 'POST',
        path: '/api/pets',
        request: StorePetRequest::class,
        responses: [
            Response::HTTP_CREATED => 'PetEnvelope',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'ValidationError',
        ],
    )]
    #[BidirektSchema(name: 'ValidationError', fields: [
        new BidirektField('message', type: 'string'),
        new BidirektField('errors', type: 'map', values: ['type' => 'array', 'items' => 'string'], description: 'The messages of each invalid field, by field name'),
    ])]
    public function store(StorePetRequest $request): PetResource
    {
        $pet = Pet::create([
            'name' => $request->validated('name'),
            'weight' => $request->validated('weight'),
            'owner_id' => $request->validated('ownerId'),
            'category_id' => $request->validated('categoryId'),
            'tags' => $request->validated('tags', []),
        ]);

        return new PetResource($pet->load('category'));
    }

    #[BidirektProvider(method: 'GET', path: '/api/pets/*', responses: [200 => 'PetEnvelope', 404 => 'ErrorMessage'])]
    #[BidirektSchema(name: 'PetEnvelope', fields: [
        new BidirektField('data', type: PetResource::class),
    ])]
    #[BidirektSchema(name: 'ErrorMessage', fields: [
        new BidirektField('message', type: 'string'),
    ])]
    public function show(Pet $pet): PetResource
    {
        return new PetResource($pet->load('category'));
    }

    #[BidirektProvider('PUT', '/api/pets/*/status', [200 => 'PetEnvelope', 404 => 'ErrorMessage', 422 => 'ValidationError'], UpdatePetStatusRequest::class)]
    public function updateStatus(UpdatePetStatusRequest $request, Pet $pet): PetResource
    {
        $pet->update(['status' => $request->enum('status', PetStatus::class)]);

        return new PetResource($pet->load('category'));
    }

    #[BidirektProvider(method: 'DELETE', path: '/api/pets/*', responses: [Response::HTTP_NO_CONTENT => null, Response::HTTP_NOT_FOUND => 'ErrorMessage'])]
    public function destroy(Pet $pet): Response
    {
        $pet->delete();

        return response()->noContent();
    }
}
