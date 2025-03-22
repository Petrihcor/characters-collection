<?php

// TournamentService.php

namespace App\Service;

use App\Entity\Character;
use Doctrine\ORM\EntityManagerInterface;

class TournamentService
{
    public function runTournament(int $leagueId, string|array $stat, EntityManagerInterface $em)
    {
        $characters = $em->getRepository(Character::class)->findBy(['league' => $leagueId]);
        $count = count($characters);

        // Проверка: количество участников должно быть степенью двойки
        if ($count < 2 || ($count & ($count - 1)) !== 0) {
            throw new \Exception("Количество участников должно быть степенью двойки (4, 8, 16, 32...)");
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
        //while ($round < 5) {
            $this->processRound($levels, $stat, $round, $logs, $places);
            $round++;
        }

        return [
            'levels' => $levels,
            'places'=>$places,
            'logs'=>$logs
        ];
    }

    //Основная логика проведения раунда
    private function processRound(array &$levels, string|array $stat, int $round, array &$logs, array &$places)
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
                    if (gettype($stat) == 'string') {
                        $result = $this->simpleCompare($level[$i], $level[$i+1], $stat, $round);
                    } else {
                        $result = $this->multipleCompare($level[$i], $level[$i+1], $stat, $round);
                    }
                    $logs[] = $result['log'];
                    $winners[] = $result['winner'];
                    $losers[] = $result['loser'];

                }
            } else {
                if (gettype($stat) == 'string') {
                    $result = $this->simpleOddCompare($stat, $key, $level, $round);
                } else {
                    $result = $this->multipleOddCompare($stat, $key, $level, $round);
                }
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
        ksort($places);

        $levels = $levelsUpdate;

    }

    //Определение мест
    private function insertWithShift(array &$places, int $key, $player)
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


    private function multipleCompare(Character $hero1, Character $hero2, array $stats, int $round)
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

        $data['log'] = "round $round {$hero1->getName()} vs {$hero2->getName()} → winner: {$data['winner']->getName()}";
        return $data;
    }

    //сравнение одного стата по количеству очков
    private function simpleCompare(Character $hero1, Character $hero2, string $stat, int $round)
    {
        $data = [];
        $stat1 = $hero1->{"get" . ucfirst($stat)}();
        $stat2 = $hero2->{"get" . ucfirst($stat)}();

        if ($stat1 > $stat2) {
            $data['winner'] = $hero1;
            $data['loser'] = $hero2;

        } elseif ($stat1 < $stat2) {
            $data['winner'] = $hero2;
            $data['loser'] = $hero1;
        } else {
            if (rand(0, 1) === 0) {
                $data['winner'] = $hero1;
                $data['loser'] = $hero2;
            } else {
                $data['winner'] = $hero2;
                $data['loser'] = $hero1;

            }
        }
        $data['log'] = "round $round {$hero1->getName()} vs {$hero2->getName()} winner: {$data['winner']->getName()}";
        
        return $data;
    }

    //сравнение одного стата среди трех участников по количеству очков
    private function simpleOddCompare(string $stat, int $key, array $level, int $round)
    {

        if ($key < 0) {
            // Отрицательный уровень: 3 человека → 2 победителя, 1 проигравший
            while (count($level) >= 3) {
                $heroes = array_splice($level, 0, 3);
                usort($heroes, fn($a, $b) => $b->{"get" . ucfirst($stat)}() <=> $a->{"get" . ucfirst($stat)}());

                $data['winners'][] = $heroes[0]; // Лучший
                $data['winners'][] = $heroes[1]; // Второй
                $data['losers'][] = $heroes[2]; // Худший

                $data['logs'] = "round $round {$heroes[0]->getName()}, {$heroes[1]->getName()} vs {$heroes[2]->getName()} winners: {$heroes[0]->getName()}, {$heroes[1]->getName()}";

            }
        } else {

            // Положительный уровень: 3 человека → 1 победитель, 2 проигравших
            while (count($level) >= 3) {
                $heroes = array_splice($level, 0, 3);
                usort($heroes, fn($a, $b) => $b->{"get" . ucfirst($stat)}() <=> $a->{"get" . ucfirst($stat)}());

                $data['winners'][] = $heroes[0]; // Лучший
                $data['losers'][] = $heroes[1]; // Второй
                $data['losers'][] = $heroes[2]; // Худший

                $data['logs'] = "round $round {$heroes[0]->getName()} vs {$heroes[1]->getName()}, {$heroes[2]->getName()} winner: {$heroes[0]->getName()}";

            }
        }


        return $data;
    }

    private function multipleOddCompare(array $stats, int $key, array $level, int $round)
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

                $data['logs'] = "round $round {$heroes[0]->getName()}, {$heroes[1]->getName()} vs {$heroes[2]->getName()} winners: {$heroes[0]->getName()}, {$heroes[1]->getName()}";
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

                $data['logs'] = "round $round {$heroes[0]->getName()} vs {$heroes[1]->getName()}, {$heroes[2]->getName()} winner: {$heroes[0]->getName()}";
            }
        }

        return $data;
    }

}
