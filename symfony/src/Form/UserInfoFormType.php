<?php

namespace App\Form;

use App\DTO\OnboardingData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserInfoType extends AbstractType
{
    public const SUBSCRIPTION_TYPE_FREE = 'free';
    public const SUBSCRIPTION_TYPE_PREMIUM = 'premium';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Please enter your name.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Please enter your email address.'),
                    new Email(message: 'Please enter a valid email address.'),
                ],
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Phone Number',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Please enter your phone number.'),
                    new Length(min: 10, minMessage: 'The phone number needs to have at least {{ limit }} digits.'),
                ],
            ])
            ->add('subscriptionType', ChoiceType::class, [
                'label' => 'Subscription Type',
                'choices' => [
                    'Free' => self::SUBSCRIPTION_TYPE_FREE,
                    'Premium' => self::SUBSCRIPTION_TYPE_PREMIUM,
                ],
                'expanded' => false,
                'multiple' => false,
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OnboardingData::class,
            'validation_groups' => ['step1'],
        ]);
    }
}