<?php

namespace App\Model;

use App\Entity\League;
use App\Entity\Universe;
use Doctrine\Common\Collections\Collection;

class CharacterFilter
{
    public ?string $search = null;

    /** @var League[] */
    public array $leagues = [];

    /** @var Universe[] */
    public array $universes = [];
}