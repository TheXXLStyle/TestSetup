<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OnboardingController extends AbstractController
{
    #[Route('/onboarding/subscribe', name: 'app_onboarding_subscribe')]
    public function index(): Response
    {
        return $this->render('onboarding/subscribe.html.twig', [
            'controller_name' => 'OnboardingController',
        ]);
    }
}
