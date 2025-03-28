<?php

namespace App\Controller;

use App\Entity\Character;
use App\Entity\League;
use App\Entity\Tournament;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

final class NewTournamentController extends AbstractController
{
    private $tournamentService;
    private $entityManager;

    public function __construct(TournamentService $tournamentService, EntityManagerInterface $entityManager)
    {
        $this->tournamentService = $tournamentService;
        $this->entityManager = $entityManager;
    }

    #[Route('/new/tournament/{id}', name: 'app_new_tournament')]
    public function index(int $id, Request $request, SerializerInterface $serializer): Response
    {
        /** @var Tournament $tournament */
        $tournament = $this->entityManager->getRepository(Tournament::class)->findOneBy(['id' => $id]);

        $bracket = $this->tournamentService->deserializeLevels($tournament->getLevels(), $serializer);

        return $this->render('new_tournament/index.html.twig', [
            'tournament' => $tournament,
            'bracket' => $bracket,
            'id' => $id
        ]);
    }
    #[Route('/tournament/create-from-league/{id}', name: 'create_tournament')]
    public function createTournament(int $id, Request $request, SerializerInterface $serializer)
    {
        $characters = $this->entityManager->getRepository(Character::class)->findBy(['league' => $id]);
        $stats = $request->request->all('stats');
        $league = $this->entityManager->getRepository(League::class)->findOneBy(['id' => $id]);
        $name = "Обычный турнир в лиге {$league->getName()}";
        $type = $request->request->get('type');

        $tournament = new Tournament();
        $tournament->setName($name);

        $jsonArray = [];
        foreach ($characters as $character) {
            //$tournament->addCharacter($character);
            $jsonArray[] = $serializer->serialize(
                $character,
                'json',
                [
                    'groups' => 'character_group',
                    'json_encode_options' => JSON_UNESCAPED_UNICODE,
                ],

            );

        }
        $levels = [$jsonArray];

        $tournament->setLevels($levels);
        $tournament->setStats($stats);
        $tournament->setNumberParticipants(count($characters));
        $tournament->setCreatedAt(new \DateTimeImmutable());
        $tournament->setType($type);
        $tournament->setLeague($league);
        $tournament->setIsActive(true);

        $this->entityManager->persist($tournament);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_new_tournament', [ 'id' => $tournament->getId()]);
    }

    #[Route('/new/tournament/{id}/fight', name: 'choose_participans')]
    public function startFight(int $id, Request $request, SerializerInterface $serializer)
    {
        $tournament = $this->entityManager->getRepository(Tournament::class)->findOneBy(['id' => $id]);

        $jsonBracket = $tournament->getLevels();

        $bracket = [];
        foreach ($jsonBracket as $key => $level) {
            foreach ($level as $k => $character) {
                $level[$k] = $serializer->deserialize(
                    $character,          // JSON строка
                    Character::class,     // Класс, в который нужно десериализовать
                    'json',               // Формат
                    [
                        'groups' => ['character_group'],  // Указание группы для десериализации, если это необходимо
                    ]
                );
            }
            $bracket[$key] = $level;
        }
        $stats = $tournament->getStats();

        $fighters = $this->tournamentService->chooseOpponents($bracket);

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

    #[Route('/new/tournament/{id}/result', name: 'fight')]
    public function fight(int $id, Request $request, SerializerInterface $serializer) {


        $tournament = $this->entityManager->getRepository(Tournament::class)->findOneBy(['id' => $id]);
        $levels = $this->tournamentService->deserializeLevels($tournament->getLevels(), $serializer);
        $fighterIds = $request->request->all('fighters');


        $fighters = $this->entityManager->getRepository(Character::class)->findBy(['id' => $fighterIds]);


        $result = $this->tournamentService->runClassicTournament2($fighters, $tournament, $levels);

        foreach ($result['winners'] as $winner) {
            $winners[] = $winner;
        }
        foreach ($result['losers'] as $loser) {
            $losers[] = $loser;
        }


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


        foreach ($levels as &$level) {
            foreach ($level as $k => $character) {
                $level[$k] = $serializer->serialize(
                    $character,
                    'json',
                    [
                        'groups' => 'character_group',
                        'json_encode_options' => JSON_UNESCAPED_UNICODE,
                    ],
                );
            }

        }

        // Если нужно вернуть сразу результат, а не редирект

        $tournament->setLevels($levels);
        $this->entityManager->persist($tournament);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_new_tournament', ['id' => $id]);

    }
}
