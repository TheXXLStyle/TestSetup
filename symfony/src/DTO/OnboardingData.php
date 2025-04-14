<?php

namespace App\DTO;

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;
use Symfony\Component\Intl\Countries;

/**
 * Data Transfer Object for the multi-step onboarding process.
 * Implements GroupSequenceProviderInterface to control validation order based on selected subscription.
 */
class OnboardingData implements GroupSequenceProviderInterface
{
    #[Assert\NotBlank(message: 'Name cannot be blank.', groups: ['step1'])]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Name must be at least {{ limit }} characters long.', maxMessage: 'Name cannot be longer than {{ limit }} characters.', groups: ['step1'])]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Email cannot be blank.', groups: ['step1'])]
    #[Assert\Email(message: 'The email "{{ value }}" is not a valid email address.', groups: ['step1'])]
    public ?string $email = null;

    #[Assert\Length(min: 5, max: 20, minMessage: 'Phone number must be at least {{ limit }} characters long.', maxMessage: 'Phone number cannot be longer than {{ limit }} characters.', groups: ['step1'])]
    #[Assert\Regex(pattern: '/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/', message: 'Invalid phone number format.', groups: ['step1'])]
    public ?string $phoneNumber = null;

    #[Assert\NotBlank(message: 'Subscription type must be selected.', groups: ['step1'])]
    #[Assert\Choice(choices: ['free', 'premium'], message: 'Invalid subscription type selected.', groups: ['step1'])]
    public ?string $subscriptionType = 'free'; // Default value

    #[Assert\NotBlank(message: 'Address (Line 1) cannot be blank.', groups: ['step2'])]
    #[Assert\Length(min: 5, max: 255, minMessage: 'Address (Line 1) must be at least {{ limit }} characters long.', maxMessage: 'Address (Line 1) cannot be longer than {{ limit }} characters.', groups: ['step2'])]
    public ?string $addressLine1 = null;

    #[Assert\Length(max: 255, maxMessage: 'Address (Line 2) cannot be longer than {{ limit }} characters.', groups: ['step2'])]
    public ?string $addressLine2 = null;

    #[Assert\NotBlank(message: 'City cannot be blank.', groups: ['step2'])]
    #[Assert\Length(min: 2, max: 100, minMessage: 'City must be at least {{ limit }} characters long.', maxMessage: 'City cannot be longer than {{ limit }} characters.', groups: ['step2'])]
    public ?string $city = null;

    #[Assert\NotBlank(message: 'Postal code cannot be blank.', groups: ['step2'])]
    #[Assert\Length(min: 3, max: 20, minMessage: 'Postal code must be at least {{ limit }} characters long.', maxMessage: 'Postal code cannot be longer than {{ limit }} characters.', groups: ['step2'])]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9\s\-]+$/', message: 'Invalid postal code format.', groups: ['step2'])]
    public ?string $postalCode = null;

    #[Assert\NotBlank(message: 'State/Province/Region cannot be blank.', groups: ['step2'])]
    #[Assert\Length(min: 2, max: 100, minMessage: 'State/Province/Region must be at least {{ limit }} characters long.', maxMessage: 'State/Province/Region cannot be longer than {{ limit }} characters.', groups: ['step2'])]
    public ?string $state = null;

    #[Assert\NotBlank(message: 'Country cannot be blank.', groups: ['step2'])]
    #[Assert\Country(message: 'Invalid country code.', groups: ['step2'])]
    public ?string $country = null; // Store country code (e.g., DE, US)

    #[Assert\NotBlank(message: 'Credit card number cannot be blank.', groups: ['step3'])]
    #[Assert\Luhn(message: 'Invalid credit card number.', groups: ['step3'])]
    #[Assert\Length(min: 13, max: 19, minMessage: 'Credit card number too short.', maxMessage: 'Credit card number too long.', groups: ['step3'])]
    public ?string $creditCardNumber = null;

    #[Assert\NotBlank(message: 'Expiration date cannot be blank.', groups: ['step3'])]
    #[Assert\Regex(pattern: '/^(0[1-9]|1[0-2])\s*\/\s*([0-9]{2})$/', message: 'Invalid expiration date format (MM/YY).', groups: ['step3'])]
    // TODO: Add Callback constraint for future date validation if needed
    public ?string $expirationDate = null;

    #[Assert\NotBlank(message: 'CVV cannot be blank.', groups: ['step3'])]
    #[Assert\Length(min: 3, max: 3, exactMessage: 'CVV must have exactly {{ limit }} digits.', groups: ['step3'])]
    #[Assert\Regex(pattern: '/^[0-9]{3}$/', message: 'CVV must consist of digits only.', groups: ['step3'])]
    public ?string $cvv = null;

    public function __construct()
    {
        $this->subscriptionType = 'free';
    }

    /**
     * Determines if the payment step (step 3) is required based on the subscription type.
     */
    public function needsPaymentStep(): bool
    {
        return $this->subscriptionType === 'premium';
    }

    /**
     * Defines the sequence of validation groups to be checked.
     * This method is required by the GroupSequenceProviderInterface.
     * It ensures that only relevant fields are validated in each step
     * and that the payment step is only validated if needed.
     */
    public function getGroupSequence(): array
    {
        $groups = ['step1', 'step2'];
        if ($this->needsPaymentStep()) {
            $groups[] = 'step3';
        }
        $groups[] = 'Default';
        return $groups;
    }

    /**
     * Returns the full, localized name of the selected country.
     * Uses the Symfony Intl component.
     */
    public function getCountryName(): ?string
    {
        if ($this->country === null) {
            return null;
        }
        try {
            return Countries::getName($this->country);
        } catch (MissingResourceException $e) {
            return $this->country;
        }
    }
}