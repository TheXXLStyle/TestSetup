<?php

namespace App\Controller;

use App\DTO\OnboardingData;
use App\Form\AddressInfoType;
use App\Form\PaymentInfoType;
use App\Form\UserInfoType;
use App\Service\SubscriptionService;
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

    #[Route('/confirmation', name: 'app_onboarding_confirmation', methods: ['GET', 'POST'])]
    public function step4Confirmation(
        Request               $request,
        SessionInterface      $session,
        ValidatorInterface    $validator,
        UrlGeneratorInterface $urlGenerator,
        SubscriptionService   $subscriptionService
    ): Response
    {
        $onboardingData = $session->get(self::SESSION_KEY);

        if (!$onboardingData instanceof OnboardingData) {
            $this->addFlash('warning', 'Onboarding process not found. Please start again.');
            return $this->redirect($urlGenerator->generate('app_onboarding_start'));
        }

        if ($request->isMethod('POST')) {
            $violations = $validator->validate($onboardingData);

            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('danger', $violation->getPropertyPath() . ': ' . $violation->getMessage());
                }
                return $this->redirectToRoute('app_onboarding_confirmation');
            }

            try {
                $user = $subscriptionService->saveUserFromOnboarding($onboardingData);
                $this->addFlash('success', 'Your data has been saved successfully!');
                $session->remove(self::SESSION_KEY);

                return $this->redirectToRoute('app_homepage');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'An error occurred while saving your data. Please try again.');
            }
        }

        return $this->render('onboarding/confirmation.html.twig', [
            'onboardingData' => $onboardingData,
        ]);
    }
}