<?php

namespace App\Form;

use App\Entity\Csp;
use App\Entity\Langue;
use App\Entity\Visiteur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VisiteurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
            ])
            ->add('pseudo_son', TextType::class, [
                'label' => 'Pseudo',
                'required' => false,
            ])
            ->add('password_son', TextType::class, [
                'label' => 'Password son',
                'required' => false,
            ])
            ->add('date_naissance', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('code_postal', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
            ])
            ->add('type', TextType::class, [
                'label' => 'Type',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
            ])
            ->add('genre', TextType::class, [
                'label' => 'Genre',
                'required' => false,
            ])
            ->add('has_newsletter', CheckboxType::class, [
                'label' => 'Newsletter',
                'required' => false,
            ])
            ->add('csp', EntityType::class, [
                'class' => Csp::class,
                'choice_label' => 'libelle',
                'label' => 'CSP',
                'required' => false,
            ])
            ->add('langue', EntityType::class, [
                'class' => Langue::class,
                'choice_label' => 'libelle',
                'label' => 'Langue',
                'required' => false,
            ])
            ->add('has_photo', CheckboxType::class, [
                'label' => 'Photo',
                'required' => false,
            ])
            ->add('url_avatar', TextType::class, [
                'label' => 'URL Avatar',
                'required' => false,
            ])
            ->add('num_mobile', TextType::class, [
                'label' => 'Numéro mobile',
                'required' => false,
            ])
            ->add('facebook_id', TextType::class, [
                'label' => 'Facebook ID',
                'required' => false,
            ])
            ->add('google_id', TextType::class, [
                'label' => 'Google ID',
                'required' => false,
            ])
            ->add('twitter_id', TextType::class, [
                'label' => 'Twitter ID',
                'required' => false,
            ])
            ->add('is_active', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ])
            ->add('is_tosync', CheckboxType::class, [
                'label' => 'A synchroniser',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Visiteur::class,
        ]);
    }
}

