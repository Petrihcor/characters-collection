<?php

namespace App\Entity;

use App\Enum\achievementsType;
use App\Repository\AchievementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AchievementRepository::class)]
class Achievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Character $character = null;

    #[ORM\Column(enumType: achievementsType::class)]
    private ?achievementsType $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharacter(): ?Character
    {
        return $this->character;
    }

    public function setCharacter(?Character $character): static
    {
        $this->character = $character;

        return $this;
    }

    public function getType(): ?achievementsType
    {
        return $this->type;
    }

    public function setType(achievementsType $type): static
    {
        $this->type = $type;

        return $this;
    }
}
