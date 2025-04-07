<?php

namespace App\Controller;

use App\Entity\Character;
use App\Service\FastTournamnetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FastTournamentController extends AbstractController
{

    private FastTournamnetService $tournamentService;
    private EntityManagerInterface $entityManager;

    public function __construct(FastTournamnetService $tournamentService, EntityManagerInterface $entityManager)
    {
        $this->tournamentService = $tournamentService;
        $this->entityManager = $entityManager;
    }
    #[Route('/fast-tournament/{id}', name: 'fast_tournament')]
    public function index(int $id, EntityManagerInterface $em): Response
    {
        $characters = $em->getRepository(Character::class)->findBy(['league' => $id]);
        return $this->render('tournament/begin.html.twig', [
            'controller_name' => 'AnotherTournamentsController',
            'characters' => $characters,
            'id' => $id
        ]);
    }
    #[Route('/fast-tournament/{id}/bracket', name: 'fastTournament_bracket')]
    public function fastTournament(int $id, Request $request): Response
    {
        $stats = $request->request->all('stats');

        $data = $this->tournamentService->runFastTournament($id, $stats, $this->entityManager);


        $logs = $data['logs'] ?? [];
        $places = $data['places'] ?? [];
        $levels = $data['levels'] ?? [];

        ksort($places);

        $places = array_reverse($places, true); // Переворачиваем массив

        // Перенумеруем в нормальном порядке (1, 2, 3...)
        $rankedPlaces = [];

        $position = 1;
        foreach ($places as $player) {
            $rankedPlaces[$position] = $player;
            $position++;
        }

        $places = $rankedPlaces;

        return $this->render('tournament/multipleStatsSimple.html.twig', [
            'places' => $places,
            'logs' => $logs,
            'levels' => $levels,
            'stats' => $stats,
        ]);
    }
}
