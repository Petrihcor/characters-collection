<?php

namespace App\Entity;

use App\Repository\TournamentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentRepository::class)]
class Tournament
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $number_participants = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $levels = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $stats = [];


    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    private ?League $league = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    /**
     * @var Collection<int, TournamentCharacter>
     */
    #[ORM\OneToMany(targetEntity: TournamentCharacter::class, mappedBy: 'tournament')]
    private Collection $TournamentCharacters;



    public function __construct()
    {
        $this->characters = new ArrayCollection();
        $this->TournamentCharacters = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNumberParticipants(): ?int
    {
        return $this->number_participants;
    }

    public function setNumberParticipants(int $number_participants): static
    {
        $this->number_participants = $number_participants;

        return $this;
    }


    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLevels(): ?array
    {
        return $this->levels;
    }

    public function setLevels(?array $levels): static
    {
        $this->levels = $levels;

        return $this;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function setStats(array $stats): static
    {
        $this->stats = $stats;

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

    public function getLeague(): ?League
    {
        return $this->league;
    }

    public function setLeague(?League $league): static
    {
        $this->league = $league;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, TournamentCharacter>
     */
    public function getTournamentCharacters(): Collection
    {
        return $this->TournamentCharacters;
    }

    public function addTournamentCharacter(TournamentCharacter $tournamentCharacter): static
    {
        if (!$this->TournamentCharacters->contains($tournamentCharacter)) {
            $this->TournamentCharacters->add($tournamentCharacter);
            $tournamentCharacter->setTournament($this);
        }

        return $this;
    }

    public function removeTournamentCharacter(TournamentCharacter $tournamentCharacter): static
    {
        if ($this->TournamentCharacters->removeElement($tournamentCharacter)) {
            // set the owning side to null (unless already changed)
            if ($tournamentCharacter->getTournament() === $this) {
                $tournamentCharacter->setTournament(null);
            }
        }

        return $this;
    }


}
