<?php

namespace App\Form;

use App\Entity\Exposition;
use App\Entity\Interactif;
use App\Entity\Parcours;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ParcoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du parcours'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le libellé est obligatoire']),
                    new Length(['max' => 255, 'maxMessage' => 'Le libellé ne peut dépasser 255 caractères'])
                ]
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Position dans la liste (optionnel)',
                    'min' => 0
                ],
                'constraints' => [
                    new PositiveOrZero(['message' => 'L\'ordre doit être un nombre positif'])
                ],
                'help' => 'Laissez vide pour ajouter à la fin'
            ])
            ->add('expositions', EntityType::class, [
                'class' => Exposition::class,
                'choice_label' => 'libelle',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Expositions',
                'required' => false,
                'attr' => [
                    'class' => 'form-control select2',
                    'data-placeholder' => 'Sélectionner des expositions'
                ],
                'by_reference' => false,
                'help' => 'Sélectionnez les expositions où ce parcours sera proposé'
            ])
            ->add('interactifs', EntityType::class, [
                'class' => Interactif::class,
                'choice_label' => 'libelle',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Interactifs',
                'required' => false,
                'attr' => [
                    'class' => 'form-control select2',
                    'data-placeholder' => 'Sélectionner des interactifs'
                ],
                'by_reference' => false,
                'help' => 'Choisissez les contenus interactifs inclus dans ce parcours'
            ])
            ->add('is_tosync', CheckboxType::class, [
                'label' => 'À synchroniser avec Navinum V1',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Cochez pour inclure dans la synchronisation avec l\'ancien système'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
            'validation_groups' => ['sylius']
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'app_parcours';
    }
}