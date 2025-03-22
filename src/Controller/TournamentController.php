<?php

namespace App\Controller;

use App\Entity\Character;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TournamentController extends AbstractController
{
    private $tournamentService;
    private $entityManager;

    public function __construct(TournamentService $tournamentService, EntityManagerInterface $entityManager)
    {
        $this->tournamentService = $tournamentService;
        $this->entityManager = $entityManager;
    }

    #[Route('/tournament/{id}', name: 'app_tournament')]
    public function index(int $id, EntityManagerInterface $em): Response
    {
        $characters = $em->getRepository(Character::class)->findBy(['league' => $id]);;
        return $this->render('tournament/index.html.twig', [
            'controller_name' => 'TournamentController',
            'characters' => $characters,
            'id' => $id
        ]);
    }

    #[Route('/tournament/{id}/stat/{stat}', name: 'tournament_start')]
    public function start(int $id, string $stat, EntityManagerInterface $em): Response
    {
        $characters = $em->getRepository(Character::class)->findBy(['league' => $id]);
        return $this->render('tournament/selectTournament.html.twig', [
            'controller_name' => 'TournamentController',
            'characters' => $characters,
            'stat' => $stat,
            'id' => $id
        ]);
    }

    #[Route('/tournament/{id}/fast-bracket/{stat}', name: 'tournament')]
    public function bracket(int $id, string|array $stat): Response
    {

        $data = $this->tournamentService->runTournament($id, $stat, $this->entityManager);


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

        return $this->render('tournament/oneStatSimple.html.twig', [
            'places' => $places,
            'logs' => $logs,
            'levels' => $levels,
            'stat' => $stat,
        ]);
    }

    #[Route('/tournament/{id}/fast-bracket', name: 'fastTournament')]
    public function fastTournament(int $id, Request $request): Response
    {
        $stats = $request->request->all('stats');

        $data = $this->tournamentService->runTournament($id, $stats, $this->entityManager);


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
