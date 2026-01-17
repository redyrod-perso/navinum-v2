<?php

namespace App\Form;

use App\Entity\Interactif;
use App\Entity\Parcours;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InteractifType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'required' => true,
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
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
            ])
            ->add('version', TextType::class, [
                'label' => 'Version',
                'required' => false,
            ])
            ->add('editeur', TextType::class, [
                'label' => 'Editeur',
                'required' => false,
            ])
            ->add('publics', TextType::class, [
                'label' => 'Publics',
                'required' => false,
                'help' => 'séparer les types de public par un point-virgule (enfants;adultes;adolescents)',
            ])
            ->add('markets', TextType::class, [
                'label' => 'Markets',
                'required' => false,
                'help' => 'séparer les markets par un point-virgule (ios;android;windows)',
            ])
            ->add('url_market_ios', TextType::class, [
                'label' => 'URL iOS AppStore',
                'required' => false,
            ])
            ->add('url_market_android', TextType::class, [
                'label' => 'URL Android Google Store',
                'required' => false,
            ])
            ->add('url_market_windows', TextType::class, [
                'label' => 'URL Windows Phone',
                'required' => false,
            ])
            ->add('langues', TextType::class, [
                'label' => 'Langues',
                'required' => false,
                'help' => 'séparer les langues par un point-virgule (fr;en)',
            ])
            ->add('image1', TextType::class, [
                'label' => 'Image 1',
                'required' => false,
            ])
            ->add('image2', TextType::class, [
                'label' => 'Image 2',
                'required' => false,
            ])
            ->add('image3', TextType::class, [
                'label' => 'Image 3',
                'required' => false,
            ])
            ->add('date_diff', DateType::class, [
                'label' => 'Date de mise en production',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('score', TextareaType::class, [
                'label' => 'Score',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('explications_resultats', TextareaType::class, [
                'label' => 'Explications résultats',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('source_type', ChoiceType::class, [
                'label' => 'Source',
                'required' => false,
                'choices' => [
                    'HTML5' => 'html5',
                    'Native' => 'native',
                    'Web' => 'web',
                ],
                'placeholder' => 'Choisir un type',
            ])
            ->add('url_fichier_interactif', TextType::class, [
                'label' => 'URL fichier interactif',
                'required' => false,
            ])
            ->add('url_pierre_de_rosette', TextType::class, [
                'label' => 'URL pierre de rosette',
                'required' => false,
            ])
            ->add('url_illustration', TextType::class, [
                'label' => 'URL illustration',
                'required' => false,
            ])
            ->add('url_scheme', TextType::class, [
                'label' => 'URL Scheme',
                'required' => false,
            ])
            ->add('is_visiteur_needed', CheckboxType::class, [
                'label' => 'Données visiteur nécessaire',
                'required' => false,
            ])
            ->add('is_parcours_needed', CheckboxType::class, [
                'label' => 'Données parcours nécessaire',
                'required' => false,
            ])
            ->add('is_logvisite_needed', CheckboxType::class, [
                'label' => 'Données log_visite simple nécessaire',
                'required' => false,
            ])
            ->add('is_logvisite_verbose_needed', CheckboxType::class, [
                'label' => 'Données log_visite complet nécessaire',
                'required' => false,
            ])
            ->add('url_interactif_type', IntegerType::class, [
                'label' => 'Log_visite : choix des interactifs',
                'required' => false,
            ])
            ->add('url_interactif_choice', TextType::class, [
                'label' => ' ',
                'required' => false,
            ])
            ->add('url_visiteur_type', IntegerType::class, [
                'label' => 'Log_visite : choix des profils visiteur',
                'required' => false,
            ])
            ->add('url_start_at', IntegerType::class, [
                'label' => 'Log_visite : date de début de log',
                'required' => false,
            ])
            ->add('url_start_at_type', TextType::class, [
                'label' => ' ',
                'required' => false,
            ])
            ->add('url_end_at', IntegerType::class, [
                'label' => 'Log_visite : date de fin de log',
                'required' => false,
            ])
            ->add('url_end_at_type', TextType::class, [
                'label' => ' ',
                'required' => false,
            ])
            ->add('refresh_deploiement', CheckboxType::class, [
                'label' => 'Déploiement à générer',
                'required' => false,
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre',
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
                'label' => 'A synchroniser',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Interactif::class,
        ]);
    }
}

