<?php

namespace App\Form;

use App\Entity\Organisateur;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Email de connexion'])
            ->add('nom', TextType::class, ['label' => 'Nom affiché', 'required' => false])
            ->add('storedRoles', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Administrateur (tout gérer)' => User::ROLE_ADMIN,
                    'Organisateur (ses expositions et parcours)' => User::ROLE_ORGANISATEUR,
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('organisateur', EntityType::class, [
                'class' => Organisateur::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => '— aucun —',
                'help' => 'Obligatoire pour un organisateur : il ne verra que les expositions de cet organisateur.',
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'required' => $options['require_password'],
                'help' => $options['require_password'] ? null : 'Laisser vide pour ne pas changer.',
                'constraints' => array_filter([
                    $options['require_password'] ? new NotBlank() : null,
                    new Length(min: 8, minMessage: '8 caractères minimum'),
                ]),
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('isActive', CheckboxType::class, ['label' => 'Compte actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'require_password' => true]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
