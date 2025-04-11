<?php

namespace App\Controller;

use App\Form\CharacterFilterType;
use App\Model\CharacterFilter;
use App\Repository\CharacterRepository;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{

    #[Route('/characters/search', name: 'app_characters_search')]
    public function ajaxCharacters(
        Request $request,
        CharacterRepository $repo,
        PaginatorInterface $paginator,
        LoggerInterface $logger
    ): JsonResponse {
        $filter = new CharacterFilter();
        $form = $this->createForm(CharacterFilterType::class, $filter, ['method' => 'GET']);
        $form->submit($request->query->all('character_filter'));

        $query = $repo->getFilteredQuery($filter);
        $pagination = $paginator->paginate($query, $request->query->getInt('page', 1), 12);

        $logger->info('Filter debug', [
            'search' => $filter->search,
            'leagues' => array_map(fn($l) => $l->getId(), $filter->leagues),
            'universes' => array_map(fn($u) => $u->getId(), $filter->universes),
        ]);

        $characters = [];
        foreach ($pagination as $character) {
            $characters[] = [
                'id' => $character->getId(),
                'name' => $character->getName(),
                'image' => $character->getImage(),
                'intelligence' => $character->getIntelligence(),
                'strength' => $character->getStrength(),
                'agility' => $character->getAgility(),
                'specialPowers' => $character->getSpecialPowers(),
                'fightingSkills' => $character->getFightingSkills(),
            ];
        }

        return $this->json(['characters' => $characters]);
    }

}
