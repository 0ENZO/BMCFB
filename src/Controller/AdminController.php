<?php

namespace App\Controller;

use App\Entity\Questionnaire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin", name="admin")
     */
    public function index(): Response
    {

        $questionnaires = $this->getDoctrine()->getRepository(Questionnaire::class)->findAll();
        
        return $this->render('admin/index.html.twig', [
            'questionnaires' => $questionnaires
        ]);
    }
}
