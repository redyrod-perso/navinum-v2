<?php

namespace App\Form;

use App\Entity\Contexte;
use App\Entity\Exposition;
use App\Entity\Organisateur;
use App\Entity\Parcours;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExpositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'required' => true,
            ])
            ->add('contexte', EntityType::class, [
                'class' => Contexte::class,
                'choice_label' => 'libelle',
                'label' => 'Contexte',
                'required' => false,
            ])
            ->add('organisateurEditeur', EntityType::class, [
                'class' => Organisateur::class,
                'choice_label' => 'libelle',
                'label' => 'Organisateur éditeur',
                'required' => false,
            ])
            ->add('organisateurDiffuseur', EntityType::class, [
                'class' => Organisateur::class,
                'choice_label' => 'libelle',
                'label' => 'Organisateur diffuseur',
                'required' => false,
            ])
            ->add('synopsis', TextareaType::class, [
                'label' => 'Synopsis',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 8],
            ])
            ->add('logo', TextType::class, [
                'label' => 'Logo',
                'required' => false,
            ])
            ->add('publics', TextType::class, [
                'label' => 'Publics',
                'required' => false,
                'help' => 'séparer les types de public par un point-virgule (enfants;adultes;adolescents)',
            ])
            ->add('langues', TextType::class, [
                'label' => 'Langues',
                'required' => false,
                'help' => 'séparer les langues par un point-virgule (fr;en)',
            ])
            ->add('url_illustration', TextType::class, [
                'label' => 'URL illustration',
                'required' => false,
            ])
            ->add('url_studio', TextType::class, [
                'label' => 'URL studio',
                'required' => false,
            ])
            ->add('start_at', DateType::class, [
                'label' => 'Commence le',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('end_at', DateType::class, [
                'label' => 'Fini le',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('parcours', EntityType::class, [
                'class' => Parcours::class,
                'choice_label' => 'libelle',
                'label' => 'Parcours',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('is_tosync', CheckboxType::class, [
                'required' => false,
                'label' => 'A synchroniser',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Exposition::class,
        ]);
    }
}

