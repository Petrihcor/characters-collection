<?php

namespace App\Controller;

use App\Entity\Character;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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

    #[Route('/tournament/{id}/classic', name: 'classic_tournament')]
    public function classicTournament(int $id, Request $request, SessionInterface $session)
    {

        if (!$session->get('stats')) {
            $stats = $request->request->all('stats');
            $session->set('stats', $stats);
        }

        $characters = $this->entityManager->getRepository(Character::class)->findBy(['league' => $id]);
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
        return $this->render('tournament/classic.html.twig', [
            'places' => $tournamentData['places'],
            'logs' => $tournamentData['logs'],
            'levels' => $tournamentData['levels'],
            'round' => $tournamentData['round'],
            'stats' => $session->get('stats'),
            'id' => $id
        ]);

    }

    #[Route('/tournament/{id}/classic/fight', name: 'start_fight', methods: ['POST'])]
    public function startFight(int $id, Request $request, SessionInterface $session)
    {

        $tournamentData = $session->get('tournament_'.$id);
        $stats = $session->get('stats');
        $levels = $tournamentData['levels'];

        $fighters = $this->tournamentService->chooseOpponents($levels);
        $fightersData = array_map(function ($fighter) use ($stats) {
            $fighterData = [
                'id' => $fighter->getId(),
                'name' => $fighter->getName(),
                'image' => $fighter->getImage(),
            ];
            // Добавляем все статы из сессии
            foreach ($stats as $stat) {
                $getter = 'get' . ucfirst($stat);
                if (method_exists($fighter, $getter)) {
                    $fighterData[$stat] = $fighter->$getter();
                } else {
                    $fighterData[$stat] = 'N/A'; // На случай, если метод отсутствует
                }
            }
            return $fighterData;
        }, $fighters);


        return $this->json([
            'fighters' => $fightersData
        ]);
    }

    #[Route('/tournament/{id}/bracket/classic/fight', name: 'tournament_next_fight')]
    public function fight(int $id, Request $request, SessionInterface $session)
    {

        $stats = $session->get('stats');
        $tournamentData = $session->get('tournament_'.$id);
        $tournamentData['logs'] = null;

        $levels = $tournamentData['levels'];
        $places = $tournamentData['places'];
        $fighterIds = $request->request->all('fighters');


        $fighters = $this->entityManager->getRepository(Character::class)->findBy(['id' => $fighterIds]);
        $result = $this->tournamentService->runClassicTournament($fighters, $stats, $tournamentData);


        //
        if (isset($result['winners']) && isset($result['losers'])) {
            foreach ($result['winners'] as $winner) {
                $winners[] = $winner;
            }
            foreach ($result['losers'] as $loser) {
                $losers[] = $loser;
            }
        } else {
            foreach ($levels as $key => &$level) {
                $this->tournamentService->insertWithShift($places, $key, $level[0]);
                unset($levels[$key]);
            }
            krsort($places);


            $tournamentData['logs'] = $result['logs'];
            $tournamentData['levels'] = $levels;
            $tournamentData['places'] = $places;
            $session->set('tournament_'.$id, $tournamentData);

            return $this->redirectToRoute('classic_tournament', ['id' => $id]);
        }

        //TODO: вынести эту часть в сервис
        foreach ($levels as $key => &$level) {
            if (count($level) < 2) {
                $this->tournamentService->insertWithShift($places, $key, $level[0]);
                unset($levels[$key]);
                continue;
            }

            if (!isset($levels[$key + 1])) {
                $levels[$key + 1] = [];
            }
            $levels[$key + 1] = array_merge($levels[$key + 1], $winners);

            if (!isset($levels[$key - 1])) {
                $levels[$key - 1] = [];
            }
            $levels[$key - 1] = array_merge($levels[$key - 1], $losers);


            // Удаляем победителей и проигравших из текущего уровня
            $level = array_values(array_filter($level, function ($player) use ($winners, $losers) {
                foreach ($winners as $winner) {
                    if ($player->getId() === $winner->getId()) {
                        return false;
                    }
                }
                foreach ($losers as $loser) {
                    if ($player->getId() === $loser->getId()) {
                        return false;
                    }
                }
                return true;
            }));

            // Если уровень стал пустым, удаляем его
            if (empty($level)) {
                unset($levels[$key]);
            }
            break;
        }

        krsort($places);


        $tournamentData['logs'] = $result['logs'];
        $tournamentData['levels'] = $levels;
        $tournamentData['places'] = $places;
        $session->set('tournament_'.$id, $tournamentData);

        return $this->redirectToRoute('classic_tournament', ['id' => $id]);
    }
}
