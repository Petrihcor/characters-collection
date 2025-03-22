<?php

// TournamentService.php

namespace App\Service;

use App\Entity\Character;
use Doctrine\ORM\EntityManagerInterface;

class TournamentService
{
    public function runTournament(int $leagueId, string $stat, EntityManagerInterface $em)
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
        //while ($round < 8) {
            $this->processRound($levels, $stat, $round, $logs, $places);
            $round++;
        }

        return [
            'levels' => $levels,
            'places'=>$places,
            'logs'=>$logs
        ];
    }

    private function processRound(array &$levels, string $stat, int $round, array &$logs, array &$places)
    {
        $levelsUpdate = [];
        foreach ($levels as $key => $level) {
            //FIXME: если значение $key повторяется, идет перезапись, которая стирает предыдущего игрока.
            if (count($level) < 2) {
                $this->insertWithShift($places, $key, $level[0]);
                continue;
            }

            //TODO: вынести сравнение в отдельный метод
            $winners = [];
            $losers = [];
            if (count($level) % 2 == 0) {
                for ($i = 0; $i < count($level); $i += 2 ) {
                    $hero1 = $level[$i];
                    $hero2 = $level[$i+1];

                    $stat1 = $hero1->{"get" . ucfirst($stat)}();
                    $stat2 = $hero2->{"get" . ucfirst($stat)}();

                    if ($stat1 > $stat2) {
                        $winner = $hero1;
                        $loser = $hero2;
                        $logs[] = "round $round {$hero1->getName()} vs {$hero2->getName()} winner: {$hero1->getName()}";
                    } else {
                        $winner = $hero2;
                        $loser = $hero1;
                        $logs[] = "round $round {$hero1->getName()} vs {$hero2->getName()} winner: {$hero2->getName()}";
                    }

                    $winners[] = $winner;
                    $losers[] = $loser;

                }
            } else {
                // Если нечетное количество участников
                if ($key < 0) {
                    // Отрицательный уровень: 3 человека → 2 победителя, 1 проигравший
                    while (count($level) >= 3) {
                        $heroes = array_splice($level, 0, 3);
                        usort($heroes, fn($a, $b) => $b->{"get" . ucfirst($stat)}() <=> $a->{"get" . ucfirst($stat)}());

                        $winners[] = $heroes[0]; // Лучший
                        $winners[] = $heroes[1]; // Второй
                        $losers[] = $heroes[2]; // Худший

                        $logs[] = "round $round {$heroes[0]->getName()}, {$heroes[1]->getName()} vs {$heroes[2]->getName()} winners: {$heroes[0]->getName()}, {$heroes[1]->getName()}";
                    }
                } else {
                    // Положительный уровень: 3 человека → 1 победитель, 2 проигравших
                    while (count($level) >= 3) {
                        $heroes = array_splice($level, 0, 3);
                        usort($heroes, fn($a, $b) => $b->{"get" . ucfirst($stat)}() <=> $a->{"get" . ucfirst($stat)}());

                        $winners[] = $heroes[0]; // Лучший
                        $losers[] = $heroes[1]; // Второй
                        $losers[] = $heroes[2]; // Худший

                        $logs[] = "round $round {$heroes[0]->getName()} vs {$heroes[1]->getName()}, {$heroes[2]->getName()} winner: {$heroes[0]->getName()}";
                    }
                }
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

    private function hasActivePlayers(array $levels): bool
    {
        foreach ($levels as $players) {
            if (!empty($players)) {
                return true; // Если хотя бы один уровень не пуст, продолжаем турнир
            }
        }
        return false; // Все уровни пусты — турнир окончен
    }



}
