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
        $data['probability'] = null;
        return $data;
    }

    public function logisticCompare(Character $hero1, Character $hero2, array $stats, $d, LoggerInterface $logger)
    {
        $data = [];
        $hero1wins = 0;
        $hero2wins = 0;
        $hero1Total = 0;
        $hero2Total = 0;
        $hero1TotalWinsProbability = 0;
        $hero2TotalWinsProbability = 0;

        // Проходим по всем статистикам и вычисляем победы по каждому стату
        foreach ($stats as $stat) {
            $stat1 = $hero1->{"get" . ucfirst($stat)}();
            $stat2 = $hero2->{"get" . ucfirst($stat)}();

            $hero1Total += $stat1;
            $hero2Total += $stat2;

            // Вычисляем шансы победы через логистическую функцию для каждого стата
            $probability1 = 1 / (1 + exp(-($stat1 - $stat2) / $d));// вероятность победы hero1
            $probability2 = 1 - $probability1;  // вероятность победы hero2
            //$logger->info("Вероятность {$hero1->getName()}:", ['probability1' => $probability1]);
            //$logger->info("Вероятность {$hero2->getName()}:", ['probability2' => $probability2]);
            // Суммируем вероятности для каждого героя
            $hero1TotalWinsProbability += $probability1;
            $hero2TotalWinsProbability += $probability2;

            // Генерация случайного числа для определения победителя по данному стату
            $roll = mt_rand() / mt_getrandmax();  // Генерация случайного числа от 0 до 1
            //$logger->info("Сгенерированное число: $roll");
            if ($roll < $probability1) {
                $hero1wins++;
                //$logger->info("Победитель раунда {$hero1->getName()}");
            } else {
                $hero2wins++;
                //$logger->info("Победитель раунда {$hero2->getName()}");
            }

        }
        //$logger->info("Счет $hero1wins $hero2wins");
        // Сравниваем победы по статам
        if ($hero1wins > $hero2wins) {
            $data['winner'] = $hero1;
            $data['loser'] = $hero2;
        } elseif ($hero1wins < $hero2wins) {
            $data['winner'] = $hero2;
            $data['loser'] = $hero1;
        } else {
            // Если победы по статам равны, то сравниваем их суммарные значения с помощью логистической функции
            $totalDifference = $hero1Total - $hero2Total; // Разница между суммарными статами
            $probability1 = 1 / (1 + exp(-$totalDifference / $d));  // Общая вероятность победы hero1
            $probability2 = 1 - $probability1;  // Общая вероятность победы hero2
            //$logger->info("Вероятность {$hero1->getName()}:", ['probability1' => $probability1]);
            //$logger->info("Вероятность {$hero2->getName()}:", ['probability2' => $probability2]);
            // Применяем случайный выбор на основе вычисленных вероятностей
            $roll = mt_rand() / mt_getrandmax();  // Генерация случайного числа от 0 до 1
            //$logger->info("Сгенерированное число: $roll");
            if ($roll < $probability1) {
                $data['winner'] = $hero1;
                $data['loser'] = $hero2;
                $logger->info("Победитель {$hero1->getName()}");
            } else {
                $data['winner'] = $hero2;
                $data['loser'] = $hero1;
                //$logger->info("Победитель {$hero2->getName()}");
            }
        }

        // Вычисление итоговой вероятности победы для победителя
        $totalProbability = $hero1TotalWinsProbability + $hero2TotalWinsProbability;
        //$logger->info("Считаю общую вероятность $hero1TotalWinsProbability + $hero2TotalWinsProbability = $totalProbability");
        if ($data['winner'] === $hero1) {
            $data['probability'] = $hero1TotalWinsProbability / $totalProbability;
        } else {
            $data['probability'] = $hero2TotalWinsProbability / $totalProbability;
        }
        //$logger->info("Вероятность победы была {$data['probability']}");
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


            }
        }
        $data['probability'] = null;
        return $data;
    }


    private function logisticOddCompare(array $stats, int $key, array $level, int $d, LoggerInterface $logger): array
    {
        $data = [];

        shuffle($level);
        $heroes = array_splice($level, 0, 3);

        $scores = [];
        $probabilities = [];

        // Инициализируем счётчики
        foreach ($heroes as $hero) {
            $scores[spl_object_hash($hero)] = 0;
        }

        // Попарное сравнение (3 пары)
        for ($i = 0; $i < 3; $i++) {
            for ($j = $i + 1; $j < 3; $j++) {
                $hero1 = $heroes[$i];
                $hero2 = $heroes[$j];
                $hero1StatWins = 0;
                $hero2StatWins = 0;

                foreach ($stats as $stat) {
                    $s1 = $hero1->{"get" . ucfirst($stat)}();
                    $s2 = $hero2->{"get" . ucfirst($stat)}();

                    // Логируем информацию о сравнении стата
                    $logger->info("Сравнение стата '$stat' для героев: " . spl_object_hash($hero1) . " и " . spl_object_hash($hero2));
                    $logger->info("Значение статов: Hero1 = $s1, Hero2 = $s2");

                    $prob1 = 1 / (1 + exp(-($s1 - $s2) / $d));
                    $roll = mt_rand() / mt_getrandmax();

                    // Логируем вероятность победы и случайный бросок
                    $logger->info("Вероятность победы Hero1: $prob1, случайное число для Hero1: $roll");

                    if ($roll < $prob1) {
                        $hero1StatWins++;
                        $logger->info("Hero1 победил в страте '$stat'");
                    } else {
                        $hero2StatWins++;
                        $logger->info("Hero2 победил в страте '$stat'");
                    }
                }

                // Победа в одной встрече — за большинство выигранных статов
                if ($hero1StatWins > $hero2StatWins) {
                    $scores[spl_object_hash($hero1)]++;
                    $logger->info("Hero1 победил в сравнении. Очки Hero1: " . $scores[spl_object_hash($hero1)]);
                } elseif ($hero2StatWins > $hero1StatWins) {
                    $scores[spl_object_hash($hero2)]++;
                    $logger->info("Hero2 победил в сравнении. Очки Hero2: " . $scores[spl_object_hash($hero2)]);
                } else {
                    // Если ничья по статам — выбираем случайно
                    if (rand(0, 1) === 0) {
                        $scores[spl_object_hash($hero1)]++;
                        $logger->info("Ничья, случайно победил Hero1. Очки Hero1: " . $scores[spl_object_hash($hero1)]);
                    } else {
                        $scores[spl_object_hash($hero2)]++;
                        $logger->info("Ничья, случайно победил Hero2. Очки Hero2: " . $scores[spl_object_hash($hero2)]);
                    }
                }
            }
        }

        // Определим победителя и проигравших
        uasort($scores, function ($a, $b) {
            return $b <=> $a;
        });

        // Логируем результаты
        $logger->info("Результаты после всех сравнений: ");
        foreach ($scores as $hash => $score) {
            $logger->info("Hero " . $hash . " очки: $score");
        }

        // Проверка на равенство очков
        $maxScore = max($scores);
        $minScore = min($scores);

        $tiedHeroesMax = [];
        $tiedHeroesMin = [];

        // Разделяем героев на тех, кто имеет максимальные и минимальные очки
        foreach ($scores as $hash => $score) {
            if ($score === $maxScore) {
                foreach ($heroes as $hero) {
                    if (spl_object_hash($hero) === $hash) {
                        $tiedHeroesMax[] = $hero;
                        break;
                    }
                }
            }
            if ($score === $minScore) {
                foreach ($heroes as $hero) {
                    if (spl_object_hash($hero) === $hash) {
                        $tiedHeroesMin[] = $hero;
                        break;
                    }
                }
            }
        }

        //если у всех побед одинаково
        if (count($tiedHeroesMax) == 3) {
            //перезаписываем счетчик побед
            $scores = [];
            foreach ($tiedHeroesMax as $hero) {
                $scores[spl_object_hash($hero)] = 0;
            }

            // Для каждого героя вычислить среднее значение всех статов
            $heroAvgStats = [];
            foreach ($tiedHeroesMax as $hero) {
                $totalStats = 0;
                $statCount = 0;

                foreach ($stats as $stat) {
                    $s = $hero->{"get" . ucfirst($stat)}();
                    $totalStats += $s;
                    $statCount++;
                }

                $avgStat = $totalStats / $statCount;
                $heroAvgStats[spl_object_hash($hero)] = $avgStat;
            }

            // Попарное сравнение через логистическую функцию
            foreach ($tiedHeroesMax as $i => $hero1) {
                for ($j = $i + 1; $j < count($tiedHeroesMax); $j++) {
                    $hero2 = $tiedHeroesMax[$j];

                    // Получаем средний стат для каждого героя
                    $avgStat1 = $heroAvgStats[spl_object_hash($hero1)];
                    $avgStat2 = $heroAvgStats[spl_object_hash($hero2)];

                    // Рассчитываем вероятность победы hero1 над hero2 с помощью логистической функции
                    $prob1 = 1 / (1 + exp(-($avgStat1 - $avgStat2) / $d));
                    $roll = mt_rand() / mt_getrandmax();

                    // Логируем вероятность победы и случайный бросок
                    $logger->info("Сравнение средних статов для героев: " . spl_object_hash($hero1) . " и " . spl_object_hash($hero2));
                    $logger->info("Средние статы: Hero1 = $avgStat1, Hero2 = $avgStat2");
                    $logger->info("Вероятность победы Hero1: $prob1, случайное число для Hero1: $roll");

                    // Побеждает тот, у кого вероятность больше
                    if ($roll < $prob1) {
                        $scores[spl_object_hash($hero1)]++;
                        $logger->info("Hero1 победил по средним статам. Очки Hero1: " . $scores[spl_object_hash($hero1)]);
                    } else {
                        $scores[spl_object_hash($hero2)]++;
                        $logger->info("Hero2 победил по средним статам. Очки Hero2: " . $scores[spl_object_hash($hero2)]);
                    }
                }
            }

            // Логируем финальный результат
            $logger->info("Результаты после попарного сравнения героев с одинаковыми победами:");
            foreach ($scores as $hash => $score) {
                $logger->info("Hero " . $hash . " очки: $score");
            }

        } elseif (count($tiedHeroesMax) == 2) {
            $heroAvgStats = [];
            foreach ($tiedHeroesMax as $hero) {
                $totalStats = 0;
                $statCount = 0;

                foreach ($stats as $stat) {
                    $s = $hero->{"get" . ucfirst($stat)}();
                    $totalStats += $s;
                    $statCount++;
                }

                $avgStat = $totalStats / $statCount;
                $heroAvgStats[spl_object_hash($hero)] = $avgStat;
            }

            // Получаем двух героев
            $hero1 = $tiedHeroesMax[0];
            $hero2 = $tiedHeroesMax[1];

            // Получаем средний стат для каждого героя
            $avgStat1 = $heroAvgStats[spl_object_hash($hero1)];
            $avgStat2 = $heroAvgStats[spl_object_hash($hero2)];

            // Рассчитываем вероятность победы hero1 над hero2 с помощью логистической функции
            $prob1 = 1 / (1 + exp(-($avgStat1 - $avgStat2) / $d));
            $roll = mt_rand() / mt_getrandmax();

            // Логируем вероятность победы и случайный бросок
            $logger->info("Сравнение средних статов для героев с равными победами: " . spl_object_hash($hero1) . " и " . spl_object_hash($hero2));
            $logger->info("Средние статы: Hero1 = $avgStat1, Hero2 = $avgStat2");
            $logger->info("Вероятность победы Hero1: $prob1, случайное число для Hero1: $roll");

            // Побеждает тот, у кого вероятность больше
            if ($roll < $prob1) {
                // Добавляем победу hero1
                $scores[spl_object_hash($hero1)]++;
                $logger->info("Hero1 победил по средним статам. Очки Hero1: " . $scores[spl_object_hash($hero1)]);
            } else {
                // Добавляем победу hero2
                $scores[spl_object_hash($hero2)]++;
                $logger->info("Hero2 победил по средним статам. Очки Hero2: " . $scores[spl_object_hash($hero2)]);
            }

            // Логируем финальный результат
            $logger->info("Результаты после попарного сравнения героев с равными победами:");
            foreach ($scores as $hash => $score) {
                $logger->info("Hero " . $hash . " очки: $score");
            }

        } elseif (count($tiedHeroesMin) == 2) {
            // Для каждого героя вычисляем среднее значение всех статов
            $heroAvgStats = [];
            foreach ($tiedHeroesMin as $hero) {
                $totalStats = 0;
                $statCount = 0;

                foreach ($stats as $stat) {
                    $s = $hero->{"get" . ucfirst($stat)}();
                    $totalStats += $s;
                    $statCount++;
                }

                $avgStat = $totalStats / $statCount;
                $heroAvgStats[spl_object_hash($hero)] = $avgStat;
            }

            // Получаем двух героев
            $hero1 = $tiedHeroesMin[0];
            $hero2 = $tiedHeroesMin[1];

            // Получаем средний стат для каждого героя
            $avgStat1 = $heroAvgStats[spl_object_hash($hero1)];
            $avgStat2 = $heroAvgStats[spl_object_hash($hero2)];

            // Рассчитываем вероятность победы hero1 над hero2 с помощью логистической функции
            $prob1 = 1 / (1 + exp(-($avgStat1 - $avgStat2) / $d));
            $roll = mt_rand() / mt_getrandmax();

            // Логируем вероятность победы и случайный бросок
            $logger->info("Сравнение средних статов для героев с минимальными победами: " . spl_object_hash($hero1) . " и " . spl_object_hash($hero2));
            $logger->info("Средние статы: Hero1 = $avgStat1, Hero2 = $avgStat2");
            $logger->info("Вероятность победы Hero1: $prob1, случайное число для Hero1: $roll");

            // Побеждает тот, у кого вероятность больше
            if ($roll < $prob1) {
                // Минусуем очко у hero2 (проигравшего)
                $scores[spl_object_hash($hero2)]--;
                $logger->info("Hero1 победил по средним статам. Очки Hero1: " . $scores[spl_object_hash($hero1)]);
            } else {
                // Минусуем очко у hero1 (проигравшего)
                $scores[spl_object_hash($hero1)]--;
                $logger->info("Hero2 победил по средним статам. Очки Hero2: " . $scores[spl_object_hash($hero2)]);
            }

            // Логируем финальный результат
            $logger->info("Результаты после попарного сравнения героев с минимальными победами:");
            foreach ($scores as $hash => $score) {
                $logger->info("Hero " . $hash . " очки: $score");
            }
        }


        // Определяем порядок героев
        $orderedHeroes = [];
        foreach (array_keys($scores) as $hash) {
            foreach ($heroes as $hero) {
                if (spl_object_hash($hero) === $hash) {
                    $orderedHeroes[] = $hero;
                    break;
                }
            }
        }

        // Логируем победителей и проигравших
        $logger->info("Конечные результаты: ");
        if ($key < 0) {
            // 2 победителя, 1 проигравший
            $data['winners'][] = $orderedHeroes[0];
            $data['winners'][] = $orderedHeroes[1];
            $data['losers'][] = $orderedHeroes[2];
        } else {
            // 1 победитель, 2 проигравших
            $data['winners'][] = $orderedHeroes[0];
            $data['losers'][] = $orderedHeroes[1];
            $data['losers'][] = $orderedHeroes[2];
        }

        // Подсчёт финальной вероятности победителя
        $winner = $orderedHeroes[0];
        $probSum = 0;
        $statCount = 0;

        foreach ($stats as $stat) {
            foreach ([$orderedHeroes[1], $orderedHeroes[2]] as $opponent) {
                $s1 = $winner->{"get" . ucfirst($stat)}();
                $s2 = $opponent->{"get" . ucfirst($stat)}();
                $prob = 1 / (1 + exp(-($s1 - $s2) / $d));
                $probSum += $prob;
                $statCount++;
            }
        }

        $data['probability'] = $probSum / $statCount;

        // Логируем финальную вероятность победы
        $logger->info("Финальная вероятность победы: " . $data['probability']);

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

    public function runClassicTournament(array $fighters, Tournament $tournament, array $levels, int $key, LoggerInterface $logger)
    {

        $winners = [];
        $losers = [];
        if (count($levels[$key]) % 2 == 0) {
            $hero1 = array_shift($fighters);
            $hero2 = array_shift($fighters);

            if ($tournament->getType() == "classic") {
                $result = $this->multipleCompare($hero1, $hero2, $tournament->getStats());
            } elseif ($tournament->getType() == "logistic") {
                $result = $this->logisticCompare($hero1, $hero2, $tournament->getStats(), 100, $logger);
            }

            $winners[] = $result['winner'];
            $losers[] = $result['loser'];

        } else {
            if ($tournament->getType() == "classic") {
                $result = $this->multipleOddCompare($tournament->getStats(), $key, $fighters);
            } elseif ($tournament->getType() == "logistic") {
                $result = $this->logisticOddCompare($tournament->getStats(), $key, $fighters, 100, $logger);
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
