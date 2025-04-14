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
        CountryRepositoryInterface $countryRepository,
        SubdivisionRepositoryInterface $subdivisionRepository,
        AddressFormatRepositoryInterface $addressFormatRepository,
        TranslatorInterface $translator
    ) {
        $this->countryRepository = $countryRepository;
        $this->subdivisionRepository = $subdivisionRepository;
        $this->addressFormatRepository = $addressFormatRepository;
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Hole die Länderliste direkt vom Repository
        $countries = $this->countryRepository->getList();

        $builder
            ->add('country', ChoiceType::class, [
                'label' => 'Land', // -> Übersetzen mit Translator wäre besser
                'choices' => array_flip($countries), // Labels als Keys, Codes als Values
                'choice_loader' => null, // Deaktiviert den Standard-Loader
                'required' => true,
                'placeholder' => 'Bitte Land auswählen', // -> Übersetzen
                'preferred_choices' => ['DE', 'AT', 'CH'], // Bevorzugte Länder beibehalten
                'attr' => [
                    // Wichtig für JavaScript, um Änderungen zu erkennen
                    'data-action' => 'change->address-form#update', // Beispiel für Stimulus
                ],
            ]);

        // Füge Listener hinzu, um Felder dynamisch zu ändern/hinzuzufügen
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    public function onPreSetData(FormEvent $event): void
    {
        /** @var ?OnboardingData $data */
        $data = $event->getData();
        $form = $event->getForm();

        // Land aus bestehenden Daten oder null
        $countryCode = $data?->country;

        $this->addAddressFields($form, $countryCode);
    }

    public function onPreSubmit(FormEvent $event): void
    {
        /** @var array<string, mixed>|null $data */
        $data = $event->getData();
        $form = $event->getForm();

        // Land aus den übermittelten Rohdaten
        $countryCode = $data['country'] ?? null;

        // Wichtig: Felder müssen *vor* der Validierung/Datenbindung angepasst werden
        $this->addAddressFields($form, $countryCode);
    }

    /**
     * Fügt die Adressfelder dynamisch hinzu, basierend auf dem Ländercode.
     */
    private function addAddressFields(FormInterface $form, ?string $countryCode): void
    {
        // Wenn kein Land ausgewählt ist, zeige Standardfelder oder keine Felder
        if (null === $countryCode) {
            // Füge hier ggf. Platzhalter oder leere Felder hinzu,
            // oder return; wenn Felder erst nach Landauswahl erscheinen sollen.
            // Aktuell: Füge Basis-Textfelder hinzu, damit das Formular initial rendert
            $form
                ->add('addressLine1', TextType::class, ['label' => 'Adresse (Zeile 1)', 'required' => false, 'attr' => ['disabled' => true]])
                ->add('addressLine2', TextType::class, ['label' => 'Adresse (Zeile 2)', 'required' => false, 'attr' => ['disabled' => true]])
                ->add('city', TextType::class, ['label' => 'Stadt', 'required' => false, 'attr' => ['disabled' => true]])
                ->add('postalCode', TextType::class, ['label' => 'PLZ', 'required' => false, 'attr' => ['disabled' => true]])
                ->add('state', TextType::class, ['label' => 'Bundesland/Provinz', 'required' => false, 'attr' => ['disabled' => true]]);
            return;
        }

        $addressFormat = $this->addressFormatRepository->get($countryCode);
        $requiredFields = $addressFormat->getRequiredFields();
        $usedFields = $addressFormat->getUsedFields(); // Alle Felder, die das Format verwendet

        // Generische Funktion zum Hinzufügen eines Textfeldes basierend auf dem Format
        $addTextField = function (string $fieldName, string $addressFieldConstant) use ($form, $usedFields, $requiredFields, $addressFormat) {
            if (!in_array($addressFieldConstant, $usedFields)) {
                if ($form->has($fieldName)) $form->remove($fieldName); // Entfernen, falls nicht genutzt
                return;
            }
            // Hole Label aus dem Format (z.B. "Postleitzahl", "PLZ", "ZIP code")
            // Nutze Translator für bessere Internationalisierung
            $labelKey = strtolower($addressFieldConstant); // z.B. 'postal_code'
            $label = $this->translator->trans($labelKey, [], 'addressing'); // Nutze 'addressing' Domain der Lib

            $form->add($fieldName, TextType::class, [
                'label' => $label,
                'required' => in_array($addressFieldConstant, $requiredFields),
                // Ggf. Pattern für PLZ hinzufügen: 'attr' => ['pattern' => $addressFormat->getPostalCodePattern()]
            ]);
        };

        // Füge Felder hinzu
        $addTextField('addressLine1', AddressField::ADDRESS_LINE1);
        $addTextField('addressLine2', AddressField::ADDRESS_LINE2);
        $addTextField('city', AddressField::LOCALITY); // Stadt = Locality
        $addTextField('postalCode', AddressField::POSTAL_CODE);

        // Spezielle Behandlung für Bundesland/Provinz (Administrative Area)
        if (in_array(AddressField::ADMINISTRATIVE_AREA, $usedFields)) {
            $subdivisions = $this->subdivisionRepository->getAll([$countryCode]);
            $stateLabelKey = strtolower(AddressField::ADMINISTRATIVE_AREA);
            $stateLabel = $this->translator->trans($stateLabelKey, [], 'addressing');
            $stateRequired = in_array(AddressField::ADMINISTRATIVE_AREA, $requiredFields);

            if (!empty($subdivisions[$countryCode])) {
                // Land hat vordefinierte Unterregionen -> ChoiceType
                $choices = [];
                /** @var Subdivision $subdivision */
                foreach ($subdivisions[$countryCode] as $subdivision) {
                    $choices[$subdivision->getName()] = $subdivision->getCode(); // Name als Label, Code als Value
                }
                asort($choices); // Sortiere nach Namen

                $form->add('state', ChoiceType::class, [
                    'label' => $stateLabel,
                    'required' => $stateRequired,
                    'choices' => $choices,
                    'placeholder' => $this->translator->trans('select_state', [], 'addressing'), // -> Übersetzen
                    'attr' => ['data-address-field' => 'state'], // Für JS-Targeting
                ]);
            } else {
                // Land hat keine vordefinierten Unterregionen -> TextType
                $form->add('state', TextType::class, [
                    'label' => $stateLabel,
                    'required' => $stateRequired,
                    'attr' => ['data-address-field' => 'state'], // Für JS-Targeting
                ]);
            }
        } elseif ($form->has('state')) {
            $form->remove('state'); // Entferne das Feld, wenn es im Format nicht vorkommt
        }

        // Hier könnten weitere Felder wie SORTING_CODE, DEPENDENT_LOCALITY etc. hinzugefügt werden
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OnboardingData::class,
            'validation_groups' => ['step2'], // Behalte Validierungsgruppe bei
        ]);
    }
}