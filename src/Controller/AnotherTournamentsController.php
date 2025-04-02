<?php

namespace App\Controller;

use App\Entity\Character;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AnotherTournamentsController extends AbstractController
{
    private $tournamentService;
    private $entityManager;

    public function __construct(TournamentService $tournamentService, EntityManagerInterface $entityManager)
    {
        $this->tournamentService = $tournamentService;
        $this->entityManager = $entityManager;
    }

    #[Route('/fast-tournament/{id}', name: 'app_tournament')]
    public function index(int $id, EntityManagerInterface $em): Response
    {
        $characters = $em->getRepository(Character::class)->findBy(['league' => $id]);;
        return $this->render('tournament/index.html.twig', [
            'controller_name' => 'AnotherTournamentsController',
            'characters' => $characters,
            'id' => $id
        ]);
    }


    #[Route('/tournament/{id}/fast-bracket', name: 'fastTournament')]
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

    #[Route('/tournament/{id}/bracket', name: 'tournament')]
    public function tournament(int $id, Request $request, EntityManagerInterface $em, SessionInterface $session): Response
    {
        if (!$session->get('stats')) {
            $stats = $request->request->all('stats');
            $session->set('stats', $stats);
        }
        $characters = $em->getRepository(Character::class)->findBy(['league' => $id]);

        if (!$session->get('tournament_'.$id)) {
            $tournamentData = [
                'levels' => [0 => $characters], // Уровень 0 — все участники
                'round' => 1,
                'logs' => [],
                'places' => [],
            ];
            $session->set('tournament_'.$id, $tournamentData);
        } else {
            $tournamentData = $session->get('tournament_'.$id);
        }
        return $this->render('tournament/roundsTournament.html.twig', [
            'places' => $tournamentData['places'],
            'logs' => $tournamentData['logs'],
            'levels' => $tournamentData['levels'],
            'round' => $tournamentData['round'],
            'stats' => $session->get('stats'),
            'id' => $id
        ]);
    }



    #[Route('/tournament/{id}/bracket/next', name: 'tournament_next_round')]
    public function nextRound(int $id, Request $request, SessionInterface $session, EntityManagerInterface $em)
    {

        $stats = $request->request->all('stats');
        $tournamentData = $session->get('tournament_'.$id);
        $tournamentData['logs'] = null;
        $result = $this->tournamentService->runTournament($stats, $tournamentData);

        $tournamentData['levels'] = $result['levels'];
        $tournamentData['places'] = $result['places'];
        $tournamentData['logs'] = $result['logs'];
        $tournamentData['round']++;

        // Сохраняем обновленные данные в сессию
        $session->set('tournament_'.$id, $tournamentData);

        return $this->redirectToRoute('tournament', ['id' => $id]);
    }

    #[Route('/tournament/{id}/reset', name: 'tournament_reset')]
    public function resetTournament(int $id, SessionInterface $session): Response
    {
        // Удаляем данные турнира из сессии
        $session->remove('tournament_' . $id);

        // Перенаправляем на страницу начала турнира (или куда нужно)
        return $this->redirectToRoute('classic_tournament', ['id' => $id]);
    }
    #[Route('/tournament/{id}/stop', name: 'tournament_stop')]
    public function stopTournament(int $id, SessionInterface $session): Response
    {
        // Удаляем данные турнира из сессии
        $session->clear();

        // Перенаправляем на страницу начала турнира (или куда нужно)
        return $this->redirectToRoute('app_tournament', ['id' => $id]);
    }

}
