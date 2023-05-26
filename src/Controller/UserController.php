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

    #[Route('/users', name: 'user_post', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse('Wrong method', 405);
        }

        $data = json_decode($request->getContent(), true);

        $form = $this->createFormBuilder()
            ->add('nom', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 1, 'max' => 255])
                ]
            ])
            ->add('age', NumberType::class, [
                'constraints' => [
                    new Assert\NotBlank()
                ]
            ])
            ->getForm();

        $form->submit($data);

        if (!$form->isValid()) {
            return new JsonResponse('Invalid form', 400);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => $data['nom']]);

        if ($user !== null) {
            return new JsonResponse('Name already exists', 400);
        }

        if ($data['age'] <= 21) {
            return new JsonResponse('Wrong age', 400);
        }

        $joueur = new User();
        $joueur->setName($data['nom']);
        $joueur->setAge($data['age']);
        $entityManager->persist($joueur);
        $entityManager->flush();

        return $this->json(
            $joueur,
            201,
            ['Content-Type' => 'application/json;charset=UTF-8']
        );
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

    #[Route('/user/{identifiant}', name: 'udpate_user', methods:['PATCH'])]
    public function updateUser(EntityManagerInterface $entityManager, $identifiant, Request $request): JsonResponse
    {
        $joueur = $entityManager->getRepository(User::class)->findBy(['id'=>$identifiant]);


        if(count($joueur) == 1){

            if($request->getMethod() == 'PATCH'){
                $data = json_decode($request->getContent(), true);
                $form = $this->createFormBuilder()
                    ->add('nom', TextType::class, array(
                        'required'=>false
                    ))
                    ->add('age', NumberType::class, [
                        'required' => false
                    ])
                    ->getForm();

                $form->submit($data);
                if($form->isValid()) {

                    foreach($data as $key=>$value){
                        switch($key){
                            case 'nom':
                                $user = $entityManager->getRepository(User::class)->findBy(['name'=>$data['nom']]);
                                if(count($user) === 0){
                                    $joueur[0]->setName($data['nom']);
                                    $entityManager->flush();
                                }else{
                                    return new JsonResponse('Name already exists', 400);
                                }
                                break;
                            case 'age':
                                if($data['age'] > 21){
                                    $joueur[0]->setAge($data['age']);
                                    $entityManager->flush();
                                }else{
                                    return new JsonResponse('Wrong age', 400);
                                }
                                break;
                        }
                    }
                }else{
                    return new JsonResponse('Invalid form', 400);
                }
            }else{
                $data = json_decode($request->getContent(), true);
                return new JsonResponse('Wrong method', 405);
            }

            return new JsonResponse(array('name'=>$joueur[0]->getName(), "age"=>$joueur[0]->getAge(), 'id'=>$joueur[0]->getId()), 200);
        }else{
            return new JsonResponse('Wrong id', 404);
        }    
    }

    #[Route('/user/{id}', name: 'delete_user_by_identifiant', methods:['DELETE'])]
    public function deleteUser($id, EntityManagerInterface $entityManager): JsonResponse | null
    {
        $joueur = $entityManager->getRepository(User::class)->findBy(['id'=>$id]);
        if(count($joueur) == 1){
            try{
                $entityManager->remove($joueur[0]);
                $entityManager->flush();

                $existeEncore = $entityManager->getRepository(User::class)->findBy(['id'=>$id]);
    
                if(!empty($existeEncore)){
                    throw new \Exception("Le user n'a pas éte délété");
                    return null;
                }else{
                    return new JsonResponse('', 204);
                }
            }catch(\Exception $e){
                return new JsonResponse($e->getMessage(), 500);
            }
        }else{
            return new JsonResponse('Wrong id', 404);
        }    
    }
}