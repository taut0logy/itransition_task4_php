<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HelloController extends AbstractController
{
    #[Route('/hello/{name}', name: 'app_hello', methods: ['GET'])]
    public function index(Request $request, string $name = 'World'): Response
    {
        $greeting = $request->query->get('greeting', 'Hello');

        return $this->render('hello.html.twig', [
            'name' => $name,
            'greeting' => $greeting,
        ]);
    }
}
