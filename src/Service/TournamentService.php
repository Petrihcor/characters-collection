<?php

// TournamentService.php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Tournament;
use App\Entity\TournamentCharacter;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\DocBlock\Serializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class TournamentService
{
    private FightService $fightService;
    private LoggerInterface $logger;

    public function __construct(FightService $fightService, LoggerInterface $logger)
    {
        $this->fightService = $fightService;
        $this->logger = $logger;
    }


    public function deserializeLevels(array $jsonBracket, SerializerInterface $serializer)
    {
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
        return $bracket;
    }

    public function runClassicTournament(array $fighters, Tournament $tournament, array $levels, int $key, LoggerInterface $logger)
    {

        $winners = [];
        $losers = [];
        if (count($levels[$key]) % 2 == 0) {
            $hero1 = array_shift($fighters);
            $hero2 = array_shift($fighters);

            if ($tournament->getType() == "classic") {
                $result = $this->fightService->multipleCompare($hero1, $hero2, $tournament->getStats());
            } elseif ($tournament->getType() == "logistic") {
                $result = $this->fightService->logisticCompare($hero1, $hero2, $tournament->getStats(), 125, $logger);
            }

            $winners[] = $result['winner'];
            $losers[] = $result['loser'];

        } else {

            if ($tournament->getType() == "classic") {
                $result = $this->fightService->multipleOddCompare($tournament->getStats(), $key, $fighters);
            } elseif ($tournament->getType() == "logistic") {
                $result = $this->fightService->logisticOddCompare($tournament->getStats(), $key, $fighters, 125, $logger);
            }


            foreach ($result['winners'] as $winner) {
                $winners[] = $winner;
            }
            foreach ($result['losers'] as $loser) {
                $losers[] = $loser;
            }
        }

        return [
            'probability' => $result['probability'],
            'winners' => $winners,
            'losers' => $losers
        ];
    }

    public function setPlace(array $places, int $key)
    {
        // Создаем массив от 1 до N (чтобы не зависеть от изменения индексов)
        $allPlaces = range(1, count($places));

        // Ищем свободные места (где нет значений)
        $emptyPlaces = array_diff($allPlaces, $places);

        if (!empty($emptyPlaces)) {
            return $key > 0 ? min($emptyPlaces) : max($emptyPlaces);
        }

        // Если все места заняты, назначаем следующее по порядку
        return max($allPlaces) + 1;
    }

    public function chooseOpponents(array &$levels): array
    {
        $fighters = [];


        //FIXME: найти способ избавиться от foreach и указывать в какой именно ключ надо зайти
        foreach ($levels as $key => &$level) {
            $levelKey = $key;

            shuffle($level);
            if (count($level) < 2) {
                continue;
            } elseif (count($level) % 2 == 0) {
                $fighters[] = array_shift($level);
                $fighters[] = array_shift($level);
                break;
            } else {
                $fighters = array_splice($level, 0, 3);
                break;
            }

        }


        return [
            'fighters' => $fighters,
            'key' => $levelKey
        ];
    }
    public function changeBracket(array $winners, array $losers, array $bracket, EntityManagerInterface $em, int $tournamentId)
    {
        foreach ($bracket as $key => &$level) {
            if (count($level) < 2) {
                $placesData = $em->getRepository(TournamentCharacter::class)->findBy(["tournament" => $tournamentId]);
                $places = [];
                foreach ($placesData as $place) {
                    $places[] = $place->getPlace();
                }

                $character = $em->getRepository(TournamentCharacter::class)->findOneBy([
                    "tournament" => $tournamentId,
                    "character" => $level[0]
                ]);
                $place = $this->setPlace($places, $key);

                $character->setPlace($place);
                $em->persist($character);
                unset($bracket[$key]);
                continue;
            }

            if (!isset($bracket[$key + 1])) {
                $bracket[$key + 1] = [];
            }
            $bracket[$key + 1] = array_merge($bracket[$key + 1], $winners);

            if (!isset($bracket[$key - 1])) {
                $bracket[$key - 1] = [];
            }
            $bracket[$key - 1] = array_merge($bracket[$key - 1], $losers);


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
                unset($bracket[$key]);
            }
            break;
        }
        return $bracket;
    }

}
