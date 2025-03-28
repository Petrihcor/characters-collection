<?php

namespace App\Controller;


use App\Entity\Character;
use App\Form\CharacterType;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EditorController extends AbstractController
{
    #[Route('/editor', name: 'app_editor')]
    public function index(): Response
    {
        return $this->render('editor/index.html.twig', [
            'controller_name' => 'EditorController',
        ]);
    }

    #[Route('/create/character', name: 'create_character', methods: 'GET')]
    public function createCharacter()
    {
        $character = new Character();

        $form = $this->createForm(CharacterType::class, $character);
        return $this->render('editor/newCharacter.html.twig', [
            'form' => $form,
        ]);
    }
    #[Route('/create/character', name: 'save_character', methods: 'POST')]
    public function saveCharacter(Request $request, EntityManagerInterface $em, ParameterBagInterface $params)
    {

        $character = new Character();

        $form = $this->createForm(CharacterType::class, $character);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $image = $form['file']->getData();

            $character->setCreatedAt(new \DateTimeImmutable());

            $character = $form->getData();

            $directory = $params->get('upload_directory');
            $image->move($directory, $image->getClientOriginalName());
            $character->setImage($image->getClientOriginalName());
            $em->persist($character);
            $em->flush();
            return $this->redirectToRoute('create_character');
        }
        return $this->render('editor/newCharacter.html.twig', [
            'form' => $form,
        ]);
    }
}
