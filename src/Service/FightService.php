<?php

namespace App\Service;

use App\Entity\Character;
use Psr\Log\LoggerInterface;

class FightService
{
    private LoggerInterface $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    public function multipleCompare(Character $hero1, Character $hero2, array $stats)
    {
        $hero1wins = 0;
        $hero2wins = 0;
        $hero1Total = 0;
        $hero2Total = 0;


        foreach ($stats as $stat) {
            $stat1 = $hero1->{"get" . ucfirst($stat)}();
            $stat2 = $hero2->{"get" . ucfirst($stat)}();

            $hero1Total += $stat1;
            $hero2Total += $stat2;

            $hero1wins += $stat1 > $stat2 ? 1 : 0;
            $hero2wins += $stat1 < $stat2 ? 1 : 0;
        }

        if ($hero1wins !== $hero2wins) {
            [$winner, $loser] = $hero1wins > $hero2wins ? [$hero1, $hero2] : [$hero2, $hero1];
        } elseif ($hero1Total !== $hero2Total) {
            [$winner, $loser] = $hero1Total > $hero2Total ? [$hero1, $hero2] : [$hero2, $hero1];
        } else {
            [$winner, $loser] = rand(0, 1) === 0 ? [$hero1, $hero2] : [$hero2, $hero1];
        }

        return [
            'winner' => $winner,
            'loser' => $loser,
            'probability' => null,
        ];
    }

    public function logisticCompare(Character $hero1, Character $hero2, array $stats, $d)
    {
        $hero1wins = 0;
        $hero2wins = 0;
        $hero1Total = 0;
        $hero2Total = 0;
        $hero1TotalProb = 0;
        $hero2TotalProb = 0;

        foreach ($stats as $stat) {
            $getter = 'get' . ucfirst($stat);
            $stat1 = $hero1->$getter();
            $stat2 = $hero2->$getter();

            $hero1Total += $stat1;
            $hero2Total += $stat2;

            $prob1 = 1 / (1 + exp(-($stat1 - $stat2) / $d));
            $prob2 = 1 - $prob1;

            $hero1TotalProb += $prob1;
            $hero2TotalProb += $prob2;

            $roll = mt_rand() / mt_getrandmax();
            $hero1wins += ($roll < $prob1) ? 1 : 0;
            $hero2wins += ($roll >= $prob1) ? 1 : 0;
        }

        if ($hero1wins !== $hero2wins) {
            [$winner, $loser] = $hero1wins > $hero2wins ? [$hero1, $hero2] : [$hero2, $hero1];
        } else {
            $prob1 = 1 / (1 + exp(-($hero1Total - $hero2Total) / $d));
            $roll = mt_rand() / mt_getrandmax();
            [$winner, $loser] = ($roll < $prob1) ? [$hero1, $hero2] : [$hero2, $hero1];
        }

        $totalProb = $hero1TotalProb + $hero2TotalProb;
        $probability = ($winner === $hero1)
            ? $hero1TotalProb / $totalProb
            : $hero2TotalProb / $totalProb;

        return [
            'winner' => $winner,
            'loser' => $loser,
            'probability' => $probability,
        ];
    }

    public function multipleOddCompare(array $stats, int $key, array $fighters)
    {
        $data = [];

        usort($fighters, function ($a, $b) use ($stats) {
            $aWins = 0;
            $bWins = 0;
            $aTotal = 0;
            $bTotal = 0;

            foreach ($stats as $stat) {
                $aValue = $a->{"get" . ucfirst($stat)}();
                $bValue = $b->{"get" . ucfirst($stat)}();

                $aTotal += $aValue;
                $bTotal += $bValue;

                $aWins += $aValue > $bValue ? 1 : 0;
                $bWins += $aValue < $bValue ? 1 : 0;
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

        if ($key < 0) {
            // Отрицательный уровень: 3 человека → 2 победителя, 1 проигравший
            $data['winners'][] = $fighters[0]; // Лучший
            $data['winners'][] = $fighters[1]; // Второй
            $data['losers'][] = $fighters[2];  // Худший
        } else {
            // Положительный уровень: 3 человека → 1 победитель, 2 проигравших
            $data['winners'][] = $fighters[0]; // Лучший
            $data['losers'][] = $fighters[1];  // Второй
            $data['losers'][] = $fighters[2];  // Худший
        }
        $data['probability'] = null;
        return $data;
    }


    public function logisticOddCompare(array $stats, int $key, array $level, int $d, LoggerInterface $logger): array
    {
        $data = [];

        shuffle($level);
        $heroes = array_splice($level, 0, 3);

        $scores = [];

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
                    $logger->info("Сравнение стата '$stat' для героев: " . $hero1->getName() . " и " . $hero2->getName());
                    $logger->info("Значение статов: {$hero1->getName()} = $s1, {$hero2->getName()} = $s2");

                    $prob1 = 1 / (1 + exp(-($s1 - $s2) / $d));
                    $roll = mt_rand() / mt_getrandmax();

                    // Логируем вероятность победы и случайный бросок
                    $logger->info("Вероятность победы {$hero1->getName()}: $prob1, случайное число для {$hero1->getName()}: $roll");

                    if ($roll < $prob1) {
                        $hero1StatWins++;
                        $logger->info("{$hero1->getName()} победил в страте '$stat'");
                    } else {
                        $hero2StatWins++;
                        $logger->info("{$hero1->getName()} победил в страте '$stat'");
                    }
                }

                // Победа в одной встрече — за большинство выигранных статов
                if ($hero1StatWins > $hero2StatWins) {
                    $scores[spl_object_hash($hero1)]++;
                    $logger->info("{$hero1->getName()} победил в сравнении. Очки Hero1: " . $scores[spl_object_hash($hero1)]);
                } elseif ($hero2StatWins > $hero1StatWins) {
                    $scores[spl_object_hash($hero2)]++;
                    $logger->info("{$hero2->getName()} победил в сравнении. Очки Hero2: " . $scores[spl_object_hash($hero2)]);
                } else {
                    // Если ничья по статам — выбираем случайно
                    if (rand(0, 1) === 0) {
                        $scores[spl_object_hash($hero1)]++;
                        $logger->info("Ничья, случайно победил {$hero1->getName()}. Очки {$hero1->getName()}: " . $scores[spl_object_hash($hero1)]);
                    } else {
                        $scores[spl_object_hash($hero2)]++;
                        $logger->info("Ничья, случайно победил {$hero2->getName()}. Очки {$hero2->getName()}: " . $scores[spl_object_hash($hero2)]);
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

}