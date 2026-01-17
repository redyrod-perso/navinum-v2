<?php

namespace App\Form;

use App\Entity\Rfid;
use App\Entity\RfidGroupe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RfidType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('valeur1', TextType::class, [
                'label' => 'UID',
                'required' => false,
            ])
            ->add('valeur2', TextType::class, [
                'label' => 'Valeur 2',
                'required' => false,
            ])
            ->add('valeur3', TextType::class, [
                'label' => 'Valeur 3',
                'required' => false,
            ])
            ->add('type', TextType::class, [
                'label' => 'Type',
                'required' => false,
                'data' => 'visiteur',
            ])
            ->add('groupe', EntityType::class, [
                'class' => RfidGroupe::class,
                'choice_label' => 'nom',
                'label' => 'Nom Groupe',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Est actif',
                'required' => false,
            ])
            ->add('isResettable', CheckboxType::class, [
                'label' => 'A resetter',
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
            'data_class' => Rfid::class,
        ]);
    }
}

