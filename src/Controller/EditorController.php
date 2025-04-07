<?php

namespace App\Controller;


use App\Entity\Character;
use App\Entity\League;
use App\Entity\Tournament;
use App\Entity\TournamentCharacter;
use App\Entity\Universe;
use App\Form\CharacterType;

use App\Form\LeagueType;
use App\Form\TournamentCharactersType;
use App\Form\TournamentType;
use App\Form\UniverseType;
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
        return $this->render('editor/begin.html.twig', [
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
            return $this->redirectToRoute('app_home');
        }
        return $this->render('league/newLeague.html.twig', [
            'form' => $form,
        ]);

    }

    #[Route('/create/universe', name: 'create_universe', methods: ['GET', 'POST'])]
    public function createUniverse(Request $request, EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $universe = new Universe();
        $form = $this->createForm(UniverseType::class, $universe);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($universe);
            $em->flush();
            return $this->redirectToRoute('app_home');
        }
        return $this->render('league/newUniverse.html.twig', [
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

            return $this->redirectToRoute('app_league', ['id' => $character->getLeague()->getId()]);
        }

        return $this->render('character/newCharacter.html.twig', [
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
            return $this->redirectToRoute('app_league', ['id' => $character->getLeague()->getId()]);
        }

        return $this->render('character/editCharacter.html.twig', [
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
        return $this->render('customTournament/newCustomTournament.html.twig', [
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
        return $this->render('customTournament/addParticipant.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/tournament/setting/{id}', name: 'setting_tournament', methods: ['GET', 'POST'])]
    public function settingTournament(int $id, Request $request, EntityManagerInterface $em)
    {
        $tournament = $em->getRepository(Tournament::class)->findOneBy(['id' => $id]);
        if($tournament->getLevels()) {
            return $this->render('customTournament/error.html.twig');
        } else {
            $characters = [];
            foreach ($tournament->getTournamentCharacters() as $tournamentCharacter) {
                $characters[] = $tournamentCharacter->getCharacter();
            }

            //делаем форму для добавления участников
            $participant = new TournamentCharacter();
            $formParticipant = $this->createForm(TournamentCharactersType::class, $participant);
            $formParticipant->handleRequest($request);


            if ($formParticipant->isSubmitted() && $formParticipant->isValid()) {
                $selectedCharacters = $formParticipant->get('character')->getData();

                foreach ($selectedCharacters as $character) {
                    $participant = new TournamentCharacter();
                    $participant->setTournament($tournament);
                    $participant->setCharacter($character);

                    $em->persist($participant);
                }
                $em->flush();

                return $this->render('customTournament/settingCustomTournament.html.twig', [
                    'tournament' => $tournament,
                    'characters' => $characters,
                    'formParticipant' => $formParticipant,
                    'id' => $id
                ]);
            }

            return $this->render('customTournament/settingCustomTournament.html.twig', [
                'tournament' => $tournament,
                'characters' => $characters,
                'formParticipant' => $formParticipant,
                'id' => $id
            ]);

        }

    }

    #[Route('/tournament-character/delete', name: 'delete_participant', methods: ['POST'])]
    public function deleteCharacter(EntityManagerInterface $em, Request $request)
    {

        $characterId = $request->request->get('characterId');
        $tournamentId = $request->request->get('tournamentId');
        $character = $em->getRepository(TournamentCharacter::class)->findOneBy([
            'character' => $characterId,
            'tournament' => $tournamentId
        ]);

        if (!$character) {
            throw $this->createNotFoundException('Участник турнира не найден.');
        }

        $tournamentId = $character->getTournament()->getId(); // Чтобы потом вернуться обратно

        $em->remove($character);
        $em->flush();

        return $this->redirectToRoute('setting_tournament', ['id' => $tournamentId]);

    }

    #[Route('/league/edit/{id}', name: 'edit_league', methods: ['POST'])]
    public function editLeague(int $id, Request $request, EntityManagerInterface $em)
    {
        $name = $request->request->get('name');
        $league = $em->getRepository(League::class)->findOneBy(['id' => $id]);
        $league->setName($name);
        $em->persist($league);
        $em->flush();
        return $this->redirectToRoute('app_home');
    }
}
