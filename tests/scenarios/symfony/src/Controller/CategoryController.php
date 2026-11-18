<?php

namespace App\Controller;

use App\Dto\CategoryResponse;
use App\Repository\PetRepository;
use Bidirekt\PhpSdk\BidirektProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController extends AbstractController
{
    #[Route('/categories', name: 'category_list', methods: ['GET'])]
    #[BidirektProvider(method: 'GET', path: '/categories', responses: [Response::HTTP_OK => [CategoryResponse::class]])]
    public function __invoke(PetRepository $pets): JsonResponse
    {
        return $this->json($pets->categories());
    }
}
