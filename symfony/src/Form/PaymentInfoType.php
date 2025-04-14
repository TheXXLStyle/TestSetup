<?php

namespace App\Form;

use App\DTO\OnboardingData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('creditCardNumber', TextType::class, [
                'label' => 'Kreditkartennummer',
                'required' => true,
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9\\s]{13,19}',
                    'autocomplete' => 'cc-number',
                    'maxlength' => 19,
                    'placeholder' => 'XXXX XXXX XXXX XXXX',
                ],
            ])
            ->add('expirationDate', TextType::class, [
                'label' => 'Ablaufdatum (MM/YY)',
                'required' => true,
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '(0[1-9]|1[0-2])\\s*\\/\\s*([0-9]{2})',
                    'autocomplete' => 'cc-exp',
                    'maxlength' => 5, // MM/YY
                    'placeholder' => 'MM/YY',
                ],
            ])
            ->add('cvv', TextType::class, [
                'label' => 'CVV',
                'required' => true,
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]{3}',
                    'autocomplete' => 'cc-csc',
                    'maxlength' => 3,
                    'placeholder' => 'XXX',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OnboardingData::class,
            'validation_groups' => ['step3'],
        ]);
    }
}