<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PaymentMethodRepository;

/**
 * Represents a payment method linked to a User, typically using a
 * token from a Payment Service Provider (PSP).
 */
#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
class PaymentMethod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Token or reference ID from the Payment Service Provider (e.g., Stripe, Braintree).
     * This is the recommended way to securely reference payment methods.
     */
    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true)] // Token should be unique, but possibly optional if other methods exist
    private ?string $providerToken = null;

    /**
     * Stores only the last 4 digits unencrypted for display purposes.
     * This information is typically provided by the PSP.
     */
    #[ORM\Column(type: 'string', length: 4, nullable: true)]
    private ?string $last4 = null;

    /**
     * Stores the card type (Visa, Mastercard etc.) unencrypted.
     * This information is typically provided by the PSP.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $cardType = null;

    /**
     * Optional: Expiration date (MM/YY), if provided by the PSP and needed.
     * Often not necessary when using the token.
     */
    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    private ?string $expirationDate = null;

    /**
     * The User this payment method belongs to.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'paymentMethods')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProviderToken(): ?string
    {
        return $this->providerToken;
    }

    public function setProviderToken(?string $providerToken): self
    {
        $this->providerToken = $providerToken;
        return $this;
    }

    public function getLast4(): ?string
    {
        return $this->last4;
    }

    /**
     * These setters are typically populated with data
     * from the PSP when creating/updating the method.
     */
    public function setLast4(?string $last4): self
    {
        $this->last4 = $last4;
        return $this;
    }

    public function getCardType(): ?string
    {
        return $this->cardType;
    }

    /**
     * Sets the card type.
     */
    public function setCardType(?string $cardType): self
    {
        $this->cardType = $cardType;
        return $this;
    }

    public function getExpirationDate(): ?string
    {
        return $this->expirationDate;
    }

    /**
     * Sets the expiration date (MM/YY).
     */
    public function setExpirationDate(?string $expirationDate): self
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }
}