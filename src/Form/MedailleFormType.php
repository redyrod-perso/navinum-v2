<?php

namespace App\Form;

use App\Entity\Exposition;
use App\Entity\Interactif;
use App\Entity\Medaille;
use App\Entity\MedailleType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Médaille attribuable par les règles de gamification (référencée par son libellé). */
class MedailleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'help' => 'Nom utilisé dans les règles de gamification des interactifs (doit être unique).',
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('image', TextType::class, ['label' => 'Image (URL)', 'required' => false])
            ->add('medailleType', EntityType::class, [
                'class' => MedailleType::class, 'choice_label' => 'libelle', 'label' => 'Type de médaille',
                'required' => false, 'placeholder' => '— aucun —',
            ])
            ->add('interactif', EntityType::class, [
                'class' => Interactif::class, 'choice_label' => 'libelle', 'label' => 'Interactif',
                'required' => false, 'placeholder' => '— toutes —',
            ])
            ->add('exposition', EntityType::class, [
                'class' => Exposition::class, 'choice_label' => 'libelle', 'label' => 'Exposition',
                'required' => false, 'placeholder' => '— toutes —',
            ])
            ->add('conditionObtention', TextareaType::class, [
                'label' => "Condition d'obtention (texte affiché)", 'required' => false, 'attr' => ['rows' => 2],
                'help' => 'Description pour les visiteurs ; la condition réelle est dans les règles de l\'interactif.',
            ])
            ->add('isUnique', CheckboxType::class, [
                'label' => 'Unique (obtenue une seule fois par visiteur)', 'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Medaille::class]);
    }

    public function getBlockPrefix(): string
    {
        return 'medaille';
    }
}
