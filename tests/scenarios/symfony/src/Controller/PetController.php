<?php

namespace App\Controller;

use App\Dto\CreatePetRequest;
use App\Dto\PetPage;
use App\Dto\PetResponse;
use App\Dto\Problem;
use App\Dto\UpdatePetRequest;
use App\Dto\ValidationProblem;
use App\Repository\PetRepository;
use Bidirekt\PhpSdk\BidirektProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/pets', name: 'pet_')]
final class PetController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly PetRepository $pets)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[BidirektProvider(method: 'GET', path: '/pets', responses: [Response::HTTP_OK => PetPage::class])]
    public function list(#[MapQueryParameter] int $page = 1): JsonResponse
    {
        return $this->json(new PetPage($this->pets->page($page, self::PAGE_SIZE), $page, $this->pets->count()));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[BidirektProvider(
        method: 'GET',
        path: '/pets/*',
        responses: [Response::HTTP_OK => PetResponse::class, Response::HTTP_NOT_FOUND => Problem::class],
    )]
    public function show(int $id): JsonResponse
    {
        $pet = $this->pets->find($id);
        if ($pet === null) {
            return $this->notFound($id);
        }

        return $this->json($pet);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[BidirektProvider(
        method: 'POST',
        path: '/pets',
        request: CreatePetRequest::class,
        responses: [
            JsonResponse::HTTP_CREATED => PetResponse::class,
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY => ValidationProblem::class,
        ],
    )]
    public function create(#[MapRequestPayload] CreatePetRequest $request): JsonResponse
    {
        $pet = $this->pets->add($request);

        return $this->json($pet, Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('pet_show', ['id' => $pet->id]),
        ]);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    #[BidirektProvider(
        method: 'PATCH',
        path: '/pets/*',
        request: UpdatePetRequest::class,
        responses: [
            Response::HTTP_OK => PetResponse::class,
            Response::HTTP_NOT_FOUND => Problem::class,
            Response::HTTP_UNPROCESSABLE_ENTITY => ValidationProblem::class,
        ],
    )]
    #[BidirektProvider(
        method: 'PUT',
        path: '/pets/*',
        request: UpdatePetRequest::class,
        responses: [
            Response::HTTP_OK => PetResponse::class,
            Response::HTTP_NOT_FOUND => Problem::class,
            Response::HTTP_UNPROCESSABLE_ENTITY => ValidationProblem::class,
        ],
    )]
    public function update(int $id, #[MapRequestPayload] UpdatePetRequest $request): JsonResponse
    {
        $pet = $this->pets->find($id);
        if ($pet === null) {
            return $this->notFound($id);
        }

        return $this->json($this->pets->update($pet, $request));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[BidirektProvider(
        method: 'DELETE',
        path: '/pets/*',
        responses: [Response::HTTP_NO_CONTENT => null, Response::HTTP_NOT_FOUND => Problem::class],
    )]
    public function delete(int $id): Response
    {
        if (!$this->pets->remove($id)) {
            return $this->notFound($id);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function notFound(int $id): JsonResponse
    {
        $problem = new Problem(
            'https://tools.ietf.org/html/rfc2616#section-10',
            'An error occurred',
            Response::HTTP_NOT_FOUND,
            sprintf('Pet %d not found', $id),
        );

        return $this->json($problem, Response::HTTP_NOT_FOUND);
    }
}
