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

    #[Route('/create/character', name: 'create_character', methods: ['GET', 'POST'])]
    public function createCharacter(Request $request, EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $character = new Character();
        $form = $this->createForm(CharacterType::class, $character, ['is_edit' => false]); // Создание
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form['file']->getData();

            if ($image) {
                $directory = $params->get('upload_directory');
                $image->move($directory, $image->getClientOriginalName());
                $character->setImage($image->getClientOriginalName());
            }

            $character->setCreatedAt(new \DateTimeImmutable());
            $em->persist($character);
            $em->flush();

            return $this->redirectToRoute('app_characters');
        }

        return $this->render('editor/newCharacter.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/edit/character/{id}', name: 'edit_character', methods: ['GET', 'POST'])]
    public function editCharacter(Request $request, EntityManagerInterface $em, ParameterBagInterface $params, Character $character)
    {
        $oldImage = $character->getImage();

        $form = $this->createForm(CharacterType::class, $character, ['is_edit' => true]); // Редактирование
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form['file']->getData();

            if ($image) {
                $directory = $params->get('upload_directory');
                $image->move($directory, $image->getClientOriginalName());
                $character->setImage($image->getClientOriginalName());
            } else {
                $character->setImage($oldImage);
            }

            $em->flush();
            return $this->redirectToRoute('app_characters');
        }

        return $this->render('editor/editCharacter.html.twig', [
            'form' => $form,
            'character' => $character,
        ]);
    }

}
