<?php

namespace App\Entity;

use App\Repository\CharacterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity(repositoryClass: CharacterRepository::class)]
class Character
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['character_group'])]
    private ?int $id = null;

    #[Groups(['character_group'])]
    #[ORM\Column]
    private ?int $intelligence = null;

    #[Groups(['character_group'])]
    #[ORM\Column]
    private ?int $strength = null;

    #[Groups(['character_group'])]
    #[ORM\Column]
    private ?int $agility = null;

    #[Groups(['character_group'])]
    #[ORM\Column]
    private ?int $specialPowers = null;

    #[Groups(['character_group'])]
    #[ORM\Column]
    private ?int $fightingSkills = null;

    #[Groups(['character_group'])]
    #[ORM\Column(length: 255)]
    private ?string $name = null;


    #[ORM\ManyToOne(inversedBy: 'characters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?League $league = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Groups(['character_group'])]
    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
