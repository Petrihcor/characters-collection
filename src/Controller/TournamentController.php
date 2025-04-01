<?php

namespace App\Controller;

use App\Entity\Character;
use App\Entity\League;
use App\Entity\Tournament;
use App\Entity\TournamentCharacter;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

final class TournamentController extends AbstractController
{
    private $tournamentService;
    private $entityManager;
    private $logger;

    public function __construct(TournamentService $tournamentService, EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->tournamentService = $tournamentService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/new/tournament/{id}', name: 'app_new_tournament')]
    public function index(int $id, Request $request, SerializerInterface $serializer): Response
    {
        /** @var Tournament $tournament */
        $tournament = $this->entityManager->getRepository(Tournament::class)->findOneBy(['id' => $id]);

        $bracket = $this->tournamentService->deserializeLevels($tournament->getLevels(), $serializer);
        $places = $this->entityManager->getRepository(TournamentCharacter::class)->findBy(["tournament" => $id]);

        return $this->render('new_tournament/index.html.twig', [
            'tournament' => $tournament,
            'bracket' => $bracket,
            'id' => $id,
            'places' => $places
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

        foreach ($characters as $character) {
            $tournamentCharacter = new TournamentCharacter();
            $tournamentCharacter->setTournament($tournament);
            $tournamentCharacter->setCharacter($character);

            $this->entityManager->persist($tournamentCharacter);
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('app_new_tournament', [ 'id' => $tournament->getId()]);
    }

    #[Route('/new/tournament/{id}/fight', name: 'choose_participans')]
    public function startFight(int $id, Request $request, SerializerInterface $serializer)
    {

        $tournament = $this->entityManager->getRepository(Tournament::class)->findOneBy(['id' => $id]);

        $jsonBracket = $tournament->getLevels();

        $bracket = [];
        foreach ($jsonBracket as $key => $levelData) {
            foreach ($levelData as $k => $character) {
                $levelData[$k] = $serializer->deserialize(
                    $character,          // JSON строка
                    Character::class,     // Класс, в который нужно десериализовать
                    'json',               // Формат
                    [
                        'groups' => ['character_group'],  // Указание группы для десериализации, если это необходимо
                    ]
                );
            }
            $bracket[$key] = $levelData;
        }
        $stats = $tournament->getStats();

        $result = $this->tournamentService->chooseOpponents($bracket);

        // Извлекаем бойцов и ключ уровня из результата
        $fighters = $result['fighters'];
        $levelKey = $result['key'];

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
            'fighters' => $fightersData,
            'level' => $levelKey
        ]);
    }

    #[Route('/new/tournament/{id}/result', name: 'fight')]
    public function fight(int $id, Request $request, SerializerInterface $serializer) {



        $tournament = $this->entityManager->getRepository(Tournament::class)->findOneBy(['id' => $id]);
        $levels = $this->tournamentService->deserializeLevels($tournament->getLevels(), $serializer);

        $key = $request->request->get('level');
        $this->logger->info('Key value:', ['key' => $key]);
        //TODO: небольшой костыль в виде проверки $key, может быть пофикшу
        if ($key == "null") {
            //TODO: вынести в сервис
            foreach ($levels as $key => &$level) {
                $placesData = $this->entityManager->getRepository(TournamentCharacter::class)->findBy(["tournament" => $id]);
                $places = [];
                foreach ($placesData as $place) {
                    $places[] = $place->getPlace();
                }

                $character = $this->entityManager->getRepository(TournamentCharacter::class)->findOneBy([
                    "tournament" => $id,
                    "character" => $level[0]
                ]);
                $place = $this->tournamentService->setPlace($places, $key);

                $character->setPlace($place);
                $this->entityManager->persist($character);
                unset($levels[$key]);
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
            $tournament->setLevels($levels);
            $this->entityManager->persist($tournament);
            $this->entityManager->flush();

            $response = $this->json([
                'status' => 'success',
                'newBracket' => $tournament->getLevels()
            ]);
            // Log the response to check what is being returned
            error_log($response->getContent());
            return $response;
        } else {
            $fighterIds = $request->request->all('fighters');
            $fighters = $this->entityManager->getRepository(Character::class)->findBy(['id' => $fighterIds]);


            $result = $this->tournamentService->runClassicTournament($fighters, $tournament, $levels, $key);
            $newLevels = $this->tournamentService->changeBracket($result['winners'], $result['losers'], $levels, $this->entityManager, $id);

            foreach ($newLevels as &$level) {
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

            $tournament->setLevels($newLevels);
            $this->entityManager->persist($tournament);
            $this->entityManager->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Бой проведен!',
                'winner' => [
                    'id' => $result['winners'][0]->getId(),
                    'name' => $result['winners'][0]->getName(),
                    'image' => $result['winners'][0]->getImage(),
                ],
                'loser' => [
                    'id' => $result['losers'][0]->getId(),
                    'name' => $result['losers'][0]->getName(),
                    'image' => $result['losers'][0]->getImage(),
                ],
                'newBracket' => $tournament->getLevels() // Можно сразу обновлять турнирную сетку
            ]);
        }
    }

    #[Route('/new/tournament/{id}/stop', name: 'stop_tournament')]
    public function stopTournament(int $id)
    {
        $tournament = $this->entityManager->getRepository(Tournament::class)->findOneBy(['id' => $id]);
        $tournament->setIsActive(false);
        $this->entityManager->persist($tournament);
        $this->entityManager->flush();

        return $this->redirectToRoute('finished_tournaments');
    }
}
