<?php

namespace App\Controller;

use App\Entity\Character;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LeagueController extends AbstractController
{
    #[Route('/league/{id}', name: 'app_league')]
    public function index(int $id, EntityManagerInterface $em): Response
    {
        $characters = $em->getRepository(Character::class)->findBy(['league' => $id]);;
        return $this->render('tournament/begin.html.twig', [
            'controller_name' => 'AnotherTournamentsController',
            'characters' => $characters,
            'id' => $id
        ]);
    }
}
