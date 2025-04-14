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
            $this->addFlash('warning', 'Onboarding process not found. Please start again.');
            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        $form = $this->createForm(AddressInfoType::class, $onboardingData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->set(self::SESSION_KEY, $onboardingData);

            return $this->redirect($urlGenerator->generate('app_onboarding_payment'));
        }

        return $this->render('onboarding/address_info.html.twig', [
            'form' => $form->createView(),
            'back_url' => $urlGenerator->generate('app_onboarding_start')
        ]);
    }

    #[Route('/update-address-form', name: 'app_onboarding_update_address_form', methods: ['POST'])]
    public function updateAddressForm(Request $request): Response
    {
        $onboardingData = new OnboardingData();

        $form = $this->createForm(AddressInfoType::class, $onboardingData, [
            'validation_groups' => false,
        ]);

        $form->submit($request->request->all($form->getName()), false); // false -> clearMissing = false

        return $this->render('onboarding/_address_fields.html.twig', [
            'form' => $form->createView(),
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
            $this->addFlash('warning', 'Onboarding process not found. Please start again.');

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
            'back_url' => $urlGenerator->generate('app_onboarding_address') // URL for the back button
        ]);
    }

    #[Route('/confirmation', name: 'app_onboarding_confirmation', methods: ['GET'])] // Only GET allowed
    public function step4Confirmation(
        SessionInterface      $session,
        ValidatorInterface    $validator,
        UrlGeneratorInterface $urlGenerator
    ): Response
    {
        $onboardingData = $session->get(self::SESSION_KEY);

        if (!$onboardingData instanceof OnboardingData) {
            $this->addFlash('warning', 'Onboarding process not found. Please start again.');
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
            $this->addFlash('danger', 'Errors found in the submitted data. Please check your input.');

            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        // 3. Process data (e.g., create user, create subscription - SIMULATED HERE)
        try {
            // Example service calls:
            // $user = $this->userService->createFromOnboarding($onboardingData);
            // if ($onboardingData->needsPaymentStep()) {
            //     $this->subscriptionService->createSubscription($user, $onboardingData);
            // }

            // Simulation: Everything successful
            $userId = uniqid(); // Simulated User ID
            $subscriptionStatus = $onboardingData->needsPaymentStep() ? 'active (Premium)' : 'active (Free)';

        } catch (\Exception $e) {
            $this->addFlash('danger', 'An unexpected error occurred. Please try again later or contact support.');

            return $this->redirect($urlGenerator->generate('app_onboarding_payment'));
        }

        $session->remove(self::SESSION_KEY);

        return $this->render('onboarding/confirmation.html.twig', [
            'onboardingData' => $onboardingData, // For displaying the summary
            'simulatedUserId' => $userId,
            'simulatedSubscriptionStatus' => $subscriptionStatus
        ]);
    }
}