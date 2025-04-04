<?php

namespace App\Controller;

use App\Entity\Character;
use App\Entity\League;
use App\Entity\Tournament;
use App\Form\CharacterFilterType;
use App\Model\CharacterFilter;
use App\Repository\CharacterRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function chooseTournament(EntityManagerInterface $em): Response
    {
        $leagues = $em->getRepository(League::class)->findAll();
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'leagues' => $leagues
        ]);
    }

    #[Route('/characters', name: 'app_characters')]
    public function showCharacters(EntityManagerInterface $em, Request $request, PaginatorInterface $paginator, CharacterRepository $repo)
    {

        $characters = $em->getRepository(Character::class)->findAll();
        $filter = new CharacterFilter();
        $form = $this->createForm(CharacterFilterType::class, $filter);
        $form->handleRequest($request);

        $query = $repo->getFilteredQuery($filter);

        $pagination = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1), /* page number */
            12 /* limit per page */
        );

        return $this->render('character/allCharacters.html.twig', [
            'characters' => $pagination,
            'filterForm' => $form->createView(),
        ]);
    }

    #[Route('/tournaments', name: 'app_tournaments')]
    public function showTournaments(EntityManagerInterface $em)
    {
        $tournaments = $em->getRepository(Tournament::class)->findBy(['isActive' => true]);
        return $this->render('tournament/activeTournaments.html.twig', [
            'tournaments' => $tournaments
        ]);
    }

    #[Route('/tournaments/finished', name: 'finished_tournaments')]
    public function showFinishedTournaments(EntityManagerInterface $em)
    {
        $tournaments = $em->getRepository(Tournament::class)->findBy(['isActive' => false]);
        return $this->render('tournament/activeTournaments.html.twig', [
            'tournaments' => $tournaments
        ]);
    }


}
