<?php

namespace App\Controller;

use App\Entity\Character;
use App\Entity\League;
use App\Entity\Tournament;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }

    #[Route('/tournaments/begin', name: 'choose_tournament')]
    public function chooseTournament(EntityManagerInterface $em): Response
    {
        $leagues = $em->getRepository(League::class)->findAll();
        return $this->render('tournament/chooseTournament.html.twig', [
            'controller_name' => 'HomeController',
            'leagues' => $leagues
        ]);
    }
    #[Route('/characters', name: 'app_characters')]
    public function showCharacters(EntityManagerInterface $em)
    {
        $characters = $em->getRepository(Character::class)->findAll();
        return $this->render('character/allCharacters.html.twig', [
            'characters' => $characters
        ]);
    }

    #[Route('/tournaments', name: 'app_tournaments')]
    public function showTournaments(EntityManagerInterface $em)
    {
        $tournaments = $em->getRepository(Tournament::class)->findBy(['isActive' => true]);
        return $this->render('tournament/activeTournaments.html.twig', [
            'tournaments' => $tournaments
        ]);
    }

    #[Route('/tournaments/finished', name: 'finished_tournaments')]
    public function showFinishedTournaments(EntityManagerInterface $em)
    {
        $tournaments = $em->getRepository(Tournament::class)->findBy(['isActive' => false]);
        return $this->render('tournament/activeTournaments.html.twig', [
            'tournaments' => $tournaments
        ]);
    }


}
