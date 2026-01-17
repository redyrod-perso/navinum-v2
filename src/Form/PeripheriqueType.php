<?php

namespace App\Form;

use App\Entity\Flotte;
use App\Entity\Interactif;
use App\Entity\Peripherique;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PeripheriqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresse_mac', TextType::class, [
                'label' => 'Adresse MAC',
                'required' => true,
            ])
            ->add('adresse_ip', TextType::class, [
                'label' => 'Adresse IP',
                'required' => false,
            ])
            ->add('serial_number', TextType::class, [
                'label' => 'Numéro de série',
                'required' => false,
            ])
            ->add('flotte', EntityType::class, [
                'class' => Flotte::class,
                'choice_label' => 'libelle',
                'label' => 'Flotte',
                'required' => false,
            ])
            ->add('interactif', EntityType::class, [
                'class' => Interactif::class,
                'choice_label' => 'libelle',
                'label' => 'Interactif',
                'required' => false,
            ])
            ->add('isTosync', CheckboxType::class, [
                'label' => 'A synchroniser',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Peripherique::class,
        ]);
    }
}

