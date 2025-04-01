<?php

// TournamentService.php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Tournament;
use App\Entity\TournamentCharacter;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\DocBlock\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class TournamentService
{
    public function runFastTournament(int $leagueId, array $stats, EntityManagerInterface $em)
    {
        $characters = $em->getRepository(Character::class)->findBy(['league' => $leagueId]);
        $count = count($characters);

        // Проверка: количество участников должно быть степенью двойки
        if ($count  % 2 !== 0) {
            throw new \Exception("Количество участников должно быть четным");
        }

        shuffle($characters);

        // Структуры для уровней
        $levels = [
            0 => $characters, // Все игроки начинают на уровне 0
        ];
        $logs = [];
        $round = 1;
        $places = [];
        while ($this->hasActivePlayers($levels)) {
            $this->processRound($levels, $stats, $round, $logs, $places);
            $round++;
        }
        return [
            'levels' => $levels,
            'places'=>$places,
            'logs'=>$logs
        ];
    }


    public function runTournament(array $stats, array $tournamentData)
    {
        // Структуры для уровней
        $levels = $tournamentData['levels'];
        $logs = $tournamentData['logs'];
        $round = $tournamentData['round'];
        $places = $tournamentData['places'];

        $this->processRound($levels, $stats, $round, $logs, $places);

        foreach ($levels as  $level) {
            shuffle($level);
        }
        return [
            'levels' => $levels,
            'places'=> $places,
            'logs'=> $logs
        ];
    }

    //Основная логика проведения раунда
    private function processRound(array &$levels, array $stats, int $round, array|null &$logs, array &$places)
    {
        $levelsUpdate = [];
        foreach ($levels as $key => $level) {

            if (count($level) < 2) {
                $this->insertWithShift($places, $key, $level[0]);
                continue;
            }

            $winners = [];
            $losers = [];

            if (count($level) % 2 == 0) {
                for ($i = 0; $i < count($level); $i += 2 ) {

                    $result = $this->multipleCompare($level[$i], $level[$i+1], $stats, $round);

                    $logs[] = $result['log'];
                    $winners[] = $result['winner'];
                    $losers[] = $result['loser'];

                }
            } else {

                $result = $this->multipleOddCompare($stats, $key, $level, $round);

                foreach ($result['winners'] as $winner) {
                    $winners[] = $winner;
                }
                foreach ($result['losers'] as $loser) {
                    $losers[] = $loser;
                }

                $logs[] = $result['logs'];

            }

            if (!isset($levelsUpdate[$key + 1])) {
                $levelsUpdate[$key + 1] = [];
            }
            $levelsUpdate[$key + 1] = array_merge($levelsUpdate[$key + 1], $winners);

            if (!isset($levelsUpdate[$key - 1])) {
                $levelsUpdate[$key - 1] = [];
            }
            $levelsUpdate[$key - 1] = array_merge($levelsUpdate[$key - 1], $losers);


        }
        krsort($places);

        $levels = $levelsUpdate;

    }

    //Определение мест
    public function insertWithShift(array &$places, int $key, $player)
    {
        if (isset($places[$key])) {
            $updatePlace = [];
            foreach ($places as $place => $character) {
                if ($place < 0) {
                    $updatePlace[$place - 1] = $character;
                } else {
                    $updatePlace[$place + 1] = $character;
                }
            }
            $places = $updatePlace;
        }
        $places[$key] = $player;
        return $places;
    }

    //Счетчик итерации, пока есть игроки в сетке
    private function hasActivePlayers(array $levels): bool
    {
        foreach ($levels as $players) {
            if (!empty($players)) {
                return true; // Если хотя бы один уровень не пуст, продолжаем турнир
            }
        }
        return false; // Все уровни пусты — турнир окончен
    }


    public function multipleCompare(Character $hero1, Character $hero2, array $stats)
    {
        $data = [];
        $hero1wins = 0;
        $hero2wins = 0;
        $hero1Total = 0;
        $hero2Total = 0;


        foreach ($stats as $stat) {
            $stat1 = $hero1->{"get" . ucfirst($stat)}();
            $stat2 = $hero2->{"get" . ucfirst($stat)}();

            $hero1Total += $stat1;
            $hero2Total += $stat2;

            if ($stat1 > $stat2) {
                $hero1wins++;
            } elseif ($stat1 < $stat2) {
                $hero2wins++;
            }
        }

        if ($hero1wins > $hero2wins) {
            $data['winner'] = $hero1;
            $data['loser'] = $hero2;
        } elseif ($hero1wins < $hero2wins) {
            $data['winner'] = $hero2;
            $data['loser'] = $hero1;
        } else {
            if ($hero1Total > $hero2Total) {
                $data['winner'] = $hero1;
                $data['loser'] = $hero2;
            } elseif ($hero1Total < $hero2Total) {
                $data['winner'] = $hero2;
                $data['loser'] = $hero1;
            } else {
                // Полная ничья — выбираем случайного победителя
                if (rand(0, 1) === 0) {
                    $data['winner'] = $hero1;
                    $data['loser'] = $hero2;
                } else {
                    $data['winner'] = $hero2;
                    $data['loser'] = $hero1;
                }
            }
        }


        $data['log'] = "{$hero1->getName()} vs {$hero2->getName()} → winner: {$data['winner']->getName()}";
        return $data;
    }


    private function multipleOddCompare(array $stats, int $key, array $level)
    {
        $data = [];

        if ($key < 0) {
            // Отрицательный уровень: 3 человека → 2 победителя, 1 проигравший
            while (count($level) >= 3) {
                $heroes = array_splice($level, 0, 3);

                usort($heroes, function ($a, $b) use ($stats) {
                    $aWins = 0;
                    $bWins = 0;
                    $aTotal = 0;
                    $bTotal = 0;

                    foreach ($stats as $stat) {
                        $aValue = $a->{"get" . ucfirst($stat)}();
                        $bValue = $b->{"get" . ucfirst($stat)}();

                        $aTotal += $aValue;
                        $bTotal += $bValue;

                        if ($aValue > $bValue) {
                            $aWins++;
                        } elseif ($bValue > $aValue) {
                            $bWins++;
                        }
                    }

                    // Определяем победителя по количеству выигранных статов
                    if ($aWins !== $bWins) {
                        return $bWins <=> $aWins;
                    }

                    // Если ничья - сравниваем сумму статов
                    if ($aTotal !== $bTotal) {
                        return $bTotal <=> $aTotal;
                    }

                    // Если опять ничья - выбираем случайно
                    return random_int(0, 1) * 2 - 1;
                });

                $data['winners'][] = $heroes[0]; // Лучший
                $data['winners'][] = $heroes[1]; // Второй
                $data['losers'][] = $heroes[2];  // Худший

                $data['logs'] = "{$heroes[0]->getName()}, {$heroes[1]->getName()} vs {$heroes[2]->getName()} winners: {$heroes[0]->getName()}, {$heroes[1]->getName()}";
            }
        } else {
            // Положительный уровень: 3 человека → 1 победитель, 2 проигравших
            while (count($level) >= 3) {
                $heroes = array_splice($level, 0, 3);

                usort($heroes, function ($a, $b) use ($stats) {
                    $aWins = 0;
                    $bWins = 0;
                    $aTotal = 0;
                    $bTotal = 0;

                    foreach ($stats as $stat) {
                        $aValue = $a->{"get" . ucfirst($stat)}();
                        $bValue = $b->{"get" . ucfirst($stat)}();

                        $aTotal += $aValue;
                        $bTotal += $bValue;

                        if ($aValue > $bValue) {
                            $aWins++;
                        } elseif ($bValue > $aValue) {
                            $bWins++;
                        }
                    }

                    if ($aWins !== $bWins) {
                        return $bWins <=> $aWins;
                    }

                    if ($aTotal !== $bTotal) {
                        return $bTotal <=> $aTotal;
                    }

                    return random_int(0, 1) * 2 - 1;
                });

                $data['winners'][] = $heroes[0]; // Лучший
                $data['losers'][] = $heroes[1];  // Второй
                $data['losers'][] = $heroes[2];  // Худший

                $data['logs'] = "{$heroes[0]->getName()} vs {$heroes[1]->getName()}, {$heroes[2]->getName()} winner: {$heroes[0]->getName()}";
            }
        }

        return $data;
    }

    public function chooseOpponents(array &$levels): array
    {
        $fighters = [];
        $levelKey = null;
        //FIXME: найти способ избавиться от foreach и указывать в какой именно ключ надо зайти
        foreach ($levels as $key => &$level) {

            if (count($level) < 2) {
                continue;
            }
            if (count($level) % 2 == 0) {
                $levelKey = $key;
                for ($i = 0; $i < count($level); $i += 2 ) {
                    $randomKeys = array_rand($level, 2);

                    // Забираем элементы по этим случайным ключам
                    $fighters[] = $level[$randomKeys[0]];  // Первый случайный элемент
                    $fighters[] = $level[$randomKeys[1]];  // Второй случайный элемент

                    // Убираем выбранные элементы из массива $level
                    unset($level[$randomKeys[0]]);
                    unset($level[$randomKeys[1]]);

                    // Индексирование массива после удаления элементов
                    $level = array_values($level);

                    break;
                }
                break;
            } else {
                $levelKey = $key;
                $fighters = array_splice($level, 0, 3);
                break;
            }

        }
        return [
            'fighters' => $fighters,
            'key' => $levelKey

        ];
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

    public function runClassicTournament(array $fighters, Tournament $tournament, array $levels, int $key)
    {

        $winners = [];
        $losers = [];
        if (count($levels[$key]) % 2 == 0) {
            $hero1 = array_shift($fighters);
            $hero2 = array_shift($fighters);
            $result = $this->multipleCompare($hero1, $hero2, $tournament->getStats());

            $winners[] = $result['winner'];
            $losers[] = $result['loser'];

        } else {
            $result = $this->multipleOddCompare($tournament->getStats(), $key, $fighters);

            foreach ($result['winners'] as $winner) {
                $winners[] = $winner;
            }
            foreach ($result['losers'] as $loser) {
                $losers[] = $loser;
            }
        }

        return [

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
