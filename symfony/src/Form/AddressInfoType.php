<?php

namespace App\Form;

use App\DTO\OnboardingData;
use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use CommerceGuys\Addressing\Subdivision\Subdivision;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddressInfoType extends AbstractType
{
    private CountryRepositoryInterface $countryRepository;
    private SubdivisionRepositoryInterface $subdivisionRepository;
    private AddressFormatRepositoryInterface $addressFormatRepository;
    private TranslatorInterface $translator;

    public function __construct(
        CountryRepositoryInterface       $countryRepository,
        SubdivisionRepositoryInterface   $subdivisionRepository,
        AddressFormatRepositoryInterface $addressFormatRepository,
        TranslatorInterface              $translator
    )
    {
        $this->countryRepository = $countryRepository;
        $this->subdivisionRepository = $subdivisionRepository;
        $this->addressFormatRepository = $addressFormatRepository;
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $countries = $this->countryRepository->getList();

        $builder
            ->add('country', ChoiceType::class, [
                'label' => 'Land',
                'choices' => array_flip($countries),
                'choice_loader' => null,
                'required' => true,
                'placeholder' => 'Plase select a country',
                'preferred_choices' => ['DE', 'AT', 'CH'],
                'attr' => [
                    'data-action' => 'change->address-form#update',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    public function onPreSetData(FormEvent $event): void
    {
        /** @var ?OnboardingData $data */
        $data = $event->getData();
        $form = $event->getForm();

        $countryCode = $data?->country;

        $this->addAddressFields($form, $countryCode);
    }

    public function onPreSubmit(FormEvent $event): void
    {
        /** @var array<string, mixed>|null $data */
        $data = $event->getData();
        $form = $event->getForm();

        $countryCode = $data['country'] ?? null;

        $this->addAddressFields($form, $countryCode);
    }

    private function addAddressFields(FormInterface $form, ?string $countryCode): void
    {
        if (null === $countryCode) {
            $form
                ->add('addressLine1', TextType::class, ['label' => 'Adress (line 1)', 'required' => false, 'attr' => ['disabled' => true]])
                ->add('addressLine2', TextType::class, ['label' => 'Adress (Line 2)', 'required' => false, 'attr' => ['disabled' => true]])
                ->add('city', TextType::class, ['label' => 'City', 'required' => false, 'attr' => ['disabled' => true]])
                ->add('postalCode', TextType::class, ['label' => 'Postal Code', 'required' => false, 'attr' => ['disabled' => true]])
                ->add('state', TextType::class, ['label' => 'State/Province', 'required' => false, 'attr' => ['disabled' => true]]);
            return;
        }

        $addressFormat = $this->addressFormatRepository->get($countryCode);
        $requiredFields = $addressFormat->getRequiredFields();
        $usedFields = $addressFormat->getUsedFields();


        $addTextField = function (string $fieldName, string $addressFieldConstant) use ($form, $usedFields, $requiredFields, $addressFormat) {
            if (!in_array($addressFieldConstant, $usedFields)) {
                if ($form->has($fieldName)) $form->remove($fieldName);
                return;
            }

            $labelKey = strtolower($addressFieldConstant);
            $label = $this->translator->trans($labelKey, [], 'addressing');

            $form->add($fieldName, TextType::class, [
                'label' => $label,
                'required' => in_array($addressFieldConstant, $requiredFields),
            ]);
        };

        $addTextField('addressLine1', AddressField::ADDRESS_LINE1);
        $addTextField('addressLine2', AddressField::ADDRESS_LINE2);
        $addTextField('city', AddressField::LOCALITY);
        $addTextField('postalCode', AddressField::POSTAL_CODE);

        if (in_array(AddressField::ADMINISTRATIVE_AREA, $usedFields)) {
            $subdivisions = $this->subdivisionRepository->getAll([$countryCode]);
            $stateLabelKey = strtolower(AddressField::ADMINISTRATIVE_AREA);
            $stateLabel = $this->translator->trans($stateLabelKey, [], 'addressing');
            $stateRequired = in_array(AddressField::ADMINISTRATIVE_AREA, $requiredFields);

            if (!empty($subdivisions[$countryCode])) {
                $choices = [];

                /** @var Subdivision $subdivision */
                foreach ($subdivisions[$countryCode] as $subdivision) {
                    $choices[$subdivision->getName()] = $subdivision->getCode();
                }
                asort($choices);

                $form->add('state', ChoiceType::class, [
                    'label' => $stateLabel,
                    'required' => $stateRequired,
                    'choices' => $choices,
                    'placeholder' => $this->translator->trans('select_state', [], 'addressing'),
                    'attr' => ['data-address-field' => 'state'],
                ]);
            } else {
                $form->add('state', TextType::class, [
                    'label' => $stateLabel,
                    'required' => $stateRequired,
                    'attr' => ['data-address-field' => 'state'],
                ]);
            }
        } elseif ($form->has('state')) {
            $form->remove('state');
        }

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OnboardingData::class,
            'validation_groups' => ['step2'],
        ]);
    }
}