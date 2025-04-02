<?php

namespace App\Controller;


use App\Entity\Character;
use App\Entity\League;
use App\Entity\Tournament;
use App\Entity\TournamentCharacter;
use App\Form\CharacterType;

use App\Form\LeagueType;
use App\Form\TournamentCharactersType;
use App\Form\TournamentType;
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
    #[Route('/create/league', name: 'create_league', methods: ['GET', 'POST'])]
    public function createLeague(Request $request, EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $league = new League();
        $form = $this->createForm(LeagueType::class, $league);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($league);
            $em->flush();
            return $this->redirectToRoute('choose_tournament');
        }
        return $this->render('editor/newLeague.html.twig', [
            'form' => $form,
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

    #[Route('/tournament/create', name: 'create_custom_tournament', methods: ['GET', 'POST'])]
    public function createTournament(Request $request, EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $tournament = new Tournament();
        $form = $this->createForm(TournamentType::class, $tournament);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $tournament->setCreatedAt(new \DateTimeImmutable());
            $tournament->setIsActive(true);
            $em->persist($tournament);
            $em->flush();
            return $this->redirectToRoute('setting_tournament', [ 'id' => $tournament->getId()]);
        }
        return $this->render('editor/newCustomTournament.html.twig', [
            'form' => $form,
        ]);
    }
    #[Route('/tournament-character/{id}/create', name: 'create_character_tournament', methods: ['GET', 'POST'])]
    public function createCharacterForTournament(int $id, Request $request, EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $tournament = $em->getRepository(Tournament::class)->findOneBy(['id' => $id]);
        $character = new TournamentCharacter();
        $form = $this->createForm(TournamentCharactersType::class, $character);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $character->setTournament($tournament);
            $em->persist($character);
            $em->flush();
            return $this->redirectToRoute('setting_tournament', [ 'id' => $tournament->getId()]);
        }
        return $this->render('editor/addParticipant.html.twig', [
            'form' => $form,
        ]);
    }
}
