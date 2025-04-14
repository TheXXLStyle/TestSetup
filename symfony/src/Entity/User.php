<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $subscriptionType = null;

    /**
     * @var Collection<int, Address>
     */
    #[ORM\OneToMany(targetEntity: Address::class, mappedBy: 'customer', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $addresses;

    /**
     * @var Collection<int, PaymentMethod>
     */
    #[ORM\OneToMany(targetEntity: PaymentMethod::class, mappedBy: 'customer', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $paymentMethods;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->paymentMethods = new ArrayCollection();
        $this->subscriptionType = 'free';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getSubscriptionType(): ?string
    {
        return $this->subscriptionType;
    }

    public function setSubscriptionType(string $subscriptionType): self
    {
        $this->subscriptionType = $subscriptionType;
        return $this;
    }

    /**
     * @return Collection<int, Address>
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(Address $address): self
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses[] = $address;
            $address->setUser($this);
        }
        return $this;
    }

    public function removeAddress(Address $address): self
    {
        if ($this->addresses->removeElement($address)) {
            if ($address->getUser() === $this) {
                $address->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    public function getPaymentMethods(): Collection
    {
        return $this->paymentMethods;
    }

    public function addPaymentMethod(PaymentMethod $paymentMethod): self
    {
        if (!$this->paymentMethods->contains($paymentMethod)) {
            $this->paymentMethods[] = $paymentMethod;
            $paymentMethod->setUser($this); // Wichtig für bidirektionale Beziehung
        }
        return $this;
    }

    public function removePaymentMethod(PaymentMethod $paymentMethod): self
    {
        if ($this->paymentMethods->removeElement($paymentMethod)) {

            if ($paymentMethod->getUser() === $this) {
                $paymentMethod->setUser(null);
            }
        }
        return $this;
    }
}