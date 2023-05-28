<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

class UserController extends AbstractController
{
    #[Route('/users', name: 'get_users_list', methods: ['GET'])]
    public function getUsersList(EntityManagerInterface $entityManager): JsonResponse
    {
        $users = $entityManager->getRepository(User::class)->findAll();
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'age' => $user->getAge()
            ];
        }
        return new JsonResponse($data, 200);
    }

    #[Route('/users', name: 'create_user', methods: ['POST'])]
    public function createUser(EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['nom']) || !isset($data['age'])) {
        return new JsonResponse('Missing data', 400);
        }

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['name' => $data['nom']]);
        if ($existingUser) {
        return new JsonResponse('User already exists', 400);
        }

        if ($data['age'] <= 21) {
        return new JsonResponse('Wrong age', 400);
        }

        $user = new User();
        $user->setName($data['nom']);
        $user->setAge($data['age']);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse('User created', 201);
    }

    #[Route('/user/{identifiant}', name: 'get_user_by_id', methods: ['GET'])]
    public function getUserById(EntityManagerInterface $entityManager, $identifiant): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($identifiant);
        if (!$user) {
            return new JsonResponse('User not found', 404);
        }

        $data = [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'age' => $user->getAge()
        ];

        return new JsonResponse($data, 200);
    }

    
    #[Route('/user/{identifiant}', name: 'update_user', methods: ['PATCH'])]
    public function updateUser(EntityManagerInterface $entityManager, $identifiant, Request $request): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($identifiant);
        if (!$user) {
            return new JsonResponse('User not found', 404);
        }
    
        $data = json_decode($request->getContent(), true);
    
        if (isset($data['nom'])) {
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['name' => $data['nom']]);
            if ($existingUser && $existingUser !== $user) {
                return new JsonResponse('User already exists', 400);
            }
            $user->setName($data['nom']);
        }
    
        if (isset($data['age'])) {
            if ($data['age'] <= 21) {
                return new JsonResponse('Wrong age', 400);
            }
            $user->setAge($data['age']);
        }
    
        // Vérifier les clés supplémentaires dans les données
        $allowedKeys = ['nom', 'age'];
        $extraKeys = array_diff(array_keys($data), $allowedKeys);
        if (!empty($extraKeys)) {
            return new JsonResponse('Extra keys not allowed: ' . implode(', ', $extraKeys), 400);
        }
    
        $entityManager->flush();
    
        $updatedData = [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'age' => $user->getAge()
        ];
    
        return new JsonResponse($updatedData, 200);
    }

    #[Route('/user/{identifiant}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(EntityManagerInterface $entityManager, $identifiant): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($identifiant);
        if (!$user) {
            return new JsonResponse('User not found', 404);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(null, 204);
    }
}