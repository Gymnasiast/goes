<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class Home extends AbstractController
{
    #[Route('/')]
    public function get(): Response
    {
        return $this->render('home.html.twig', [
            'title' => 'Gymnasiast’s Object Editor Site',
        ]);
    }
}
