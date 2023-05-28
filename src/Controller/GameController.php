<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\Validator\Constraints as Assert;
class GameController extends AbstractController
{
    #[Route('/games', name: 'get_list_of_games', methods: ['GET'])]
    public function getGamesList(EntityManagerInterface $entityManager): JsonResponse
    {
        $games = $entityManager->getRepository(Game::class)->findAll();

        return $this->json($games);
    }

    #[Route('/games', name: 'launch_game', methods: ['POST'])]
    public function launchGame(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $currentUserId = $request->headers->get('X-User-Id');

        if ($currentUserId !== null && ctype_digit($currentUserId)) {
            $currentUser = $entityManager->getRepository(User::class)->find($currentUserId);

            if ($currentUser === null) {
                return new JsonResponse('User not found', 401);
            }

            $newGame = new Game();
            $newGame->setState('pending');
            $newGame->setPlayerLeft($currentUser);

            $entityManager->persist($newGame);
            $entityManager->flush();

            return $this->json($newGame, 201);
        } else {
            return new JsonResponse('User not found', 401);
        }
    }

    #[Route('/game/{identifiant}', name: 'get_game_info', methods: ['GET'])]
    public function getGameInfo(EntityManagerInterface $entityManager, $identifiant): JsonResponse
    {
        if (ctype_digit($identifiant)) {
            $game = $entityManager->getRepository(Game::class)->findOneBy(['id' => $identifiant]);

            if ($game !== null) {
                return $this->json($game);
            } else {
                return new JsonResponse('Game not found', 404);
            }
        } else {
            return new JsonResponse('Game not found', 404);
        }
    }

    #[Route('/game/{id}/add/{playerRightId}', name: 'invite_to_play', methods:['PATCH'])]
    public function inviteToPlay(Request $request, EntityManagerInterface $entityManager, $id, $playerRightId): JsonResponse
    {
        $currentUserId = $request->headers->get('X-User-Id');

        if (empty($currentUserId)) {
            return new JsonResponse('User not found', 401);
        }

        if (ctype_digit($id) && ctype_digit($playerRightId) && ctype_digit($currentUserId)) {

            $playerLeft = $entityManager->getRepository(User::class)->find($currentUserId);

            if ($playerLeft === null) {
                return new JsonResponse('User not found', 401);
            }

            $game = $entityManager->getRepository(Game::class)->find($id);

            if ($game === null) {
                return new JsonResponse('Game not found', 404);
            } elseif ($game->getState() === 'ongoing' || $game->getState() === 'finished') {
                return new JsonResponse('Game already started', 409);
            }

            $playerRight = $entityManager->getRepository(User::class)->find($playerRightId);

            if ($playerRight !== null) {

                if ($playerLeft->getId() === $playerRight->getId()) {
                    return new JsonResponse('You can\'t play against yourself', 409);
                }

                $game->setPlayerRight($playerRight);
                $game->setState('ongoing');

                $entityManager->flush();

                return $this->json(
                    $game,
                    headers: ['Content-Type' => 'application/json;charset=UTF-8']
                );
            } else {
                return new JsonResponse('User not found', 404);
            }
        } else {
            if (ctype_digit($currentUserId) === false) {
                return new JsonResponse('User not found', 401);
            }

            return new JsonResponse('Game not found', 404);
        }
    }


    #[Route('/game/{identifiant}', name: 'send_choice', methods:['PATCH'])]
    public function play(Request $request, EntityManagerInterface $entityManager, $identifiant): JsonResponse
    {
        $currentUserId = $request->headers->get('X-User-Id');

        if (ctype_digit($currentUserId) === false) {
            return new JsonResponse('User not found', 401);
        }

        $currentUser = $entityManager->getRepository(User::class)->find($currentUserId);

        if ($currentUser === null) {
            return new JsonResponse('User not found', 401);
        }

        if (ctype_digit($identifiant) === false) {
            return new JsonResponse('Game not found', 404);
        }

        $game = $entityManager->getRepository(Game::class)->find($identifiant);

        if ($game === null) {
            return new JsonResponse('Game not found', 404);
        }

        $userIsPlayerLeft = $game->getPlayerLeft() === $currentUser;
        $userIsPlayerRight = $game->getPlayerRight() === $currentUser;

        if (!$userIsPlayerLeft && !$userIsPlayerRight) {
            return new JsonResponse('You are not a player of this game', 403);
        }

        if ($game->getState() === 'finished' || $game->getState() === 'pending') {
            return new JsonResponse('Game not started', 409);
        }

        $form = $this->createFormBuilder()
            ->add('choice', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank()
                ]
            ])
            ->getForm();

        $choice = json_decode($request->getContent(), true);

        $form->submit($choice);

        if ($form->isValid()) {
            $data = $form->getData();

            $validChoices = ['rock', 'paper', 'scissors'];
            if (!in_array($data['choice'], $validChoices)) {
                return new JsonResponse('Invalid choice', 400);
            }

            if ($userIsPlayerLeft) {
                $game->setPlayLeft($data['choice']);
            } elseif ($userIsPlayerRight) {
                $game->setPlayRight($data['choice']);
            }

            $entityManager->flush();

            if ($game->getPlayLeft() !== null && $game->getPlayRight() !== null) {
                $result = $this->calculateResult($game->getPlayLeft(), $game->getPlayRight());
                $game->setResult($result);
                $game->setState('finished');
                $entityManager->flush();
            }

            return $this->json(
                $game,
                headers: ['Content-Type' => 'application/json;charset=UTF-8']
            );
        }

        return new JsonResponse('Invalid choice', 400);
    }

    private function calculateResult($choice1, $choice2)
    {
        if ($choice1 === $choice2) {
            return 'draw';
        }

        switch ($choice1) {
            case 'rock':
                return ($choice2 === 'paper') ? 'winRight' : 'winLeft';
            case 'paper':
                return ($choice2 === 'scissors') ? 'winRight' : 'winLeft';
            case 'scissors':
                return ($choice2 === 'rock') ? 'winRight' : 'winLeft';
        }

        return 'draw';
    }


    #[Route('/game/{id}', name: 'delete_game', methods:['DELETE'])]
    public function deleteGame(EntityManagerInterface $entityManager, Request $request, $id): JsonResponse
    {
        $currentUserId = $request->headers->get('X-User-Id');

        if (!ctype_digit($currentUserId)) {
            return new JsonResponse('User not found', 401);
        }

        $player = $entityManager->getRepository(User::class)->find($currentUserId);

        if ($player === null) {
            return new JsonResponse('User not found', 401);
        }

        if (!ctype_digit($id)) {
            return new JsonResponse('Game not found', 404);
        }

        $game = $entityManager->getRepository(Game::class)->findOneBy(['id' => $id, 'playerLeft' => $player]);

        if ($game === null) {
            $game = $entityManager->getRepository(Game::class)->findOneBy(['id' => $id, 'playerRight' => $player]);
        }

        if ($game === null) {
            return new JsonResponse('Game not found', 403);
        }

        $entityManager->remove($game);
        $entityManager->flush();

        return new JsonResponse(null, 204);
    }
}
