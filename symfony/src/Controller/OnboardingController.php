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
        // Redirect to start if onboarding data is missing
        if (!$onboardingData instanceof OnboardingData) {
            $this->addFlash('warning', 'Onboarding process not found. Please start again.');
            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        $form = $this->createForm(AddressInfoType::class, $onboardingData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->set(self::SESSION_KEY, $onboardingData);

            // Determine the next step based on whether payment is needed
            $nextStepRoute = $onboardingData->needsPaymentStep()
                ? 'app_onboarding_payment'
                : 'app_onboarding_confirmation';
            return $this->redirect($urlGenerator->generate($nextStepRoute));
        }

        return $this->render('onboarding/address_info.html.twig', [
            'form' => $form->createView(),
            'back_url' => $urlGenerator->generate('app_onboarding_start') // URL for the back button
        ]);
    }

    #[Route('/update-address-form', name: 'app_onboarding_update_address_form', methods: ['POST'])]
    public function updateAddressForm(Request $request): Response
    {
        // Important: Use a *new*, empty DTO here to only use the
        // submitted data for the PRE_SUBMIT logic.
        $onboardingData = new OnboardingData();

        // Create the form, but without validation groups for this update
        $form = $this->createForm(AddressInfoType::class, $onboardingData, [
            'validation_groups' => false,
        ]);

        // Only submit the data, do not handle the full request object.
        // This triggers the PRE_SUBMIT listeners in AddressInfoType.
        $form->submit($request->request->all($form->getName()), false); // false -> clearMissing = false

        // Render only the part of the form with the dependent fields.
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
        // Redirect to start if onboarding data is missing
        if (!$onboardingData instanceof OnboardingData) {
            $this->addFlash('warning', 'Onboarding process not found. Please start again.');
            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        // If payment step is not needed, redirect to confirmation
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
        // Redirect to start if onboarding data is missing
        if (!$onboardingData instanceof OnboardingData) {
            $this->addFlash('warning', 'Onboarding process not found. Please start again.');
            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        // Define validation groups based on the onboarding path
        $groups = new GroupSequence(['step1']);
        if ($onboardingData->needsPaymentStep()) {
            $groups->groups[] = 'step2'; // Address validation
            $groups->groups[] = 'step3'; // Payment validation
        }
        $groups->groups[] = 'Default'; // Default constraints

        // Validate the complete data object
        $violations = $validator->validate($onboardingData, null, $groups);

        // If validation fails, redirect back with errors
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('danger', $violation->getPropertyPath() . ': ' . $violation->getMessage());
            }
            $this->addFlash('danger', 'Errors found in the submitted data. Please check your input.');
            // Do not remove session data, allow user to correct
            // Redirect back to the first step for simplicity
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
            // Error during processing
            $this->addFlash('danger', 'An unexpected error occurred. Please try again later or contact support.');
            // Log the error: $e->getMessage() ...
            // Do not remove session data, allow user to retry (or redirect to an error page)
            // Redirect to the last step before confirmation
            return $this->redirect($urlGenerator->generate('app_onboarding_payment'));
        }

        // 4. Remove onboarding data from the session
        $session->remove(self::SESSION_KEY);

        // 5. Render the success/confirmation page
        return $this->render('onboarding/confirmation.html.twig', [
            'onboardingData' => $onboardingData, // For displaying the summary
            'simulatedUserId' => $userId,
            'simulatedSubscriptionStatus' => $subscriptionStatus
            // Other data could be passed here, e.g., link to the dashboard
        ]);
    }
}