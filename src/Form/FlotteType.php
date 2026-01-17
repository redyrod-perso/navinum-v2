<?php

namespace App\Form;

use App\Entity\Exposition;
use App\Entity\Flotte;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlotteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'required' => true,
            ])
            ->add('exposition', EntityType::class, [
                'class' => Exposition::class,
                'choice_label' => 'libelle',
                'label' => 'Exposition',
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
            'data_class' => Flotte::class,
        ]);
    }
}

