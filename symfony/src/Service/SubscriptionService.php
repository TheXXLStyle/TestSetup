<?php

namespace App\Service;

use App\DTO\OnboardingData;
use App\Entity\User;
use App\Entity\Address;
use App\Entity\PaymentMethod;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function saveUserFromOnboarding(OnboardingData $data): User
    {
        $user = new User();

        $user->setName($data->name);
        $user->setEmail($data->email);
        $user->setPhoneNumber($data->phoneNumber);
        $user->setSubscriptionType($data->subscriptionType);

        $address = new Address();
        $address->setAddressLine1($data->addressLine1);
        $address->setAddressLine2($data->addressLine2);
        $address->setCity($data->city);
        $address->setPostalCode($data->postalCode);
        $address->setState($data->state);
        $address->setCountry($data->country);
        $address->setUser($user);
        $user->addAddress($address);

        if ($data->subscriptionType === 'premium' && $data->creditCardNumber) {
            $paymentMethod = new PaymentMethod();
            $paymentMethod->setProviderToken('dummy-token');
            $paymentMethod->setLast4(substr($data->creditCardNumber, -4));
            $paymentMethod->setCardType('unknown');
            $paymentMethod->setExpirationDate($data->expirationDate);
            $paymentMethod->setUser($user);
            $user->addPaymentMethod($paymentMethod);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}