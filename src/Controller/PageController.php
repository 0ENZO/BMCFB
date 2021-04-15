<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PageController extends AbstractController
{
    /**
     * @Route("/magic_link_sent/{email}", name="magic_link_sent")
     */
    public function magic_link_sent(string $email): Response
    {
        return $this->render('pages/magic_link_sent.html.twig',[
            'email' => $email,
        ]);
    }

}
