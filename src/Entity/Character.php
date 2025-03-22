<?php

namespace App\Entity;

use App\Repository\CharacterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterRepository::class)]
class Character
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $intelligence = null;

    #[ORM\Column]
    private ?int $strength = null;

    #[ORM\Column]
    private ?int $agility = null;

    #[ORM\Column]
    private ?int $specialPowers = null;

    #[ORM\Column]
    private ?int $fightingSkills = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'characters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?League $league = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIntelligence(): ?int
    {
        return $this->intelligence;
    }

    public function setIntelligence(int $intelligence): static
    {
        $this->intelligence = $intelligence;

        return $this;
    }

    public function getStrength(): ?int
    {
        return $this->strength;
    }

    public function setStrength(int $strength): static
    {
        $this->strength = $strength;

        return $this;
    }

    public function getAgility(): ?int
    {
        return $this->agility;
    }

    public function setAgility(int $agility): static
    {
        $this->agility = $agility;

        return $this;
    }

    public function getSpecialPowers(): ?int
    {
        return $this->specialPowers;
    }

    public function setSpecialPowers(int $specialPowers): static
    {
        $this->specialPowers = $specialPowers;

        return $this;
    }

    public function getFightingSkills(): ?int
    {
        return $this->fightingSkills;
    }

    public function setFightingSkills(int $fightingSkills): static
    {
        $this->fightingSkills = $fightingSkills;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLeague(): ?League
    {
        return $this->league;
    }

    public function setLeague(?League $league): static
    {
        $this->league = $league;

        return $this;
    }
}
