<?php

namespace App\Controller;

use App\DTO\OnboardingData;
use App\Form\AddressInfoType;
use App\Form\PaymentInfoType;
use App\Form\UserInfoType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/onboarding')]
class OnboardingController extends AbstractController
{
    private const SESSION_KEY = 'onboarding_data';

    #[Route('/start', name: 'app_onboarding_start', methods: ['GET', 'POST'])]
    public function step1UserInfo(
        Request               $request,
        SessionInterface      $session,
        UrlGeneratorInterface $urlGenerator
    ): Response
    {
        $onboardingData = $session->get(self::SESSION_KEY, new OnboardingData());
        $form = $this->createForm(UserInfoType::class, $onboardingData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->set(self::SESSION_KEY, $onboardingData);

            if ($form->get("subscriptionType")->getData() === UserInfoType::SUBSCRIPTION_TYPE_FREE) {
                return $this->redirect($urlGenerator->generate('app_onboarding_confirmation'));
            }

            return $this->redirect($urlGenerator->generate('app_onboarding_address'));
        }

        return $this->render('onboarding/user_info.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/address', name: 'app_onboarding_address', methods: ['GET', 'POST'])]
    public function step2AddressInfo(
        Request               $request,
        SessionInterface      $session,
        UrlGeneratorInterface $urlGenerator
    ): Response
    {
        $onboardingData = $session->get(self::SESSION_KEY);
        if (!$onboardingData instanceof OnboardingData) {
            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        $form = $this->createForm(AddressInfoType::class, $onboardingData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->set(self::SESSION_KEY, $onboardingData);

            $nextStepRoute = $onboardingData->needsPaymentStep()
                ? 'app_onboarding_payment'
                : 'app_onboarding_confirmation';
            return $this->redirect($urlGenerator->generate($nextStepRoute));
        }

        return $this->render('onboarding/address_info.html.twig', [
            'form' => $form->createView(),
            'back_url' => $urlGenerator->generate('app_onboarding_start')
        ]);
    }

    #[Route('/payment', name: 'app_onboarding_payment', methods: ['GET', 'POST'])]
    public function step3PaymentInfo(
        Request               $request,
        SessionInterface      $session,
        UrlGeneratorInterface $urlGenerator
    ): Response
    {
        $onboardingData = $session->get(self::SESSION_KEY);
        if (!$onboardingData instanceof OnboardingData) {
            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        if (!$onboardingData->needsPaymentStep()) {
            return $this->redirect($urlGenerator->generate('app_onboarding_confirmation'));
        }

        $form = $this->createForm(PaymentInfoType::class, $onboardingData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->set(self::SESSION_KEY, $onboardingData);
            return $this->redirect($urlGenerator->generate('app_onboarding_confirmation'));
        }

        return $this->render('onboarding/payment_info.html.twig', [
            'form' => $form->createView(),
            'back_url' => $urlGenerator->generate('app_onboarding_address')
        ]);
    }

    #[Route('/confirmation', name: 'app_onboarding_confirmation', methods: ['GET'])] // Nur GET erlaubt
    public function step4Confirmation(
        SessionInterface      $session,
        ValidatorInterface    $validator,
        UrlGeneratorInterface $urlGenerator
    ): Response
    {
        $onboardingData = $session->get(self::SESSION_KEY);
        if (!$onboardingData instanceof OnboardingData) {
            $this->addFlash('warning', 'onboarding-Prozess nicht gefunden. Bitte starte erneut.');
            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        $groups = new GroupSequence(['step1']);
        if ($onboardingData->needsPaymentStep()) {
            $groups->groups[] = 'step2';
            $groups->groups[] = 'step3';
        }
        $groups->groups[] = 'Default';

        $violations = $validator->validate($onboardingData, null, $groups);

        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('danger', $violation->getPropertyPath() . ': ' . $violation->getMessage());
            }
            $this->addFlash('danger', 'Fehler in den eingegebenen Daten gefunden. Bitte überprüfe deine Eingaben.');
            // Session nicht löschen, damit der User korrigieren kann
            return $this->redirect($urlGenerator->generate('app_onboarding_start')); // Einfachheitshalber zurück zu Schritt 1
        }

        // 3. Daten verarbeiten (z.B. User erstellen, Abo anlegen - HIER SIMULIERT)
        try {
            // $user = $this->userService->createFromOnboarding($onboardingData);
            // if ($onboardingData->needsPaymentStep()) {
            //     $this->subscriptionService->createSubscription($user, $onboardingData);
            // }
            // Simulation: Alles erfolgreich
            $userId = uniqid(); // Simulierte User ID
            $subscriptionStatus = $onboardingData->needsPaymentStep() ? 'active (Premium)' : 'active (Free)';

        } catch (\Exception $e) {
            // Fehler bei der Verarbeitung
            $this->addFlash('danger', 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut oder kontaktiere den Support.');
            // Logge den Fehler $e->getMessage() ...
            // Session nicht löschen, damit der User es erneut versuchen kann (oder zur Fehlerseite leiten)
            return $this->redirect($urlGenerator->generate('app_onboarding_payment')); // Letzter Schritt vor Bestätigung
        }


        // 4. onboarding-Daten aus der Session entfernen
        $session->remove(self::SESSION_KEY);

        // 5. Erfolgsseite rendern
        return $this->render('onboarding/confirmation.html.twig', [
            'onboardingData' => $onboardingData, // Für die Anzeige der Zusammenfassung
            'simulatedUserId' => $userId,
            'simulatedSubscriptionStatus' => $subscriptionStatus
            // Hier könnten weitere Daten übergeben werden, z.B. Link zum Dashboard
        ]);
    }
}