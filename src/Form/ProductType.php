<?php

namespace App\Form;

use App\Entity\Produit;
use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('imageFile', VichFileType::class, [
                'required' => true,
                'allow_delete' => true,
                'download_uri' => true,
            ])
            ->add('prix')
            ->add('quantite')
            // ->add('etat')
            ->add('categorie',EntityType::class
               , [
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'label' => 'Categorie Produit',
                'placeholder' => 'Choose a categorie',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
                
                ])
            // ->add('stores')
            ->add('save', SubmitType::class, [
                'label' => 'Create new +',
                'attr' => [
                    'class' => 'btn btn-info'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
