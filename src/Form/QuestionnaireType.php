<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Questionnaire;
use Symfony\Component\Form\AbstractType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class QuestionnaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('summary', CKEditorType::class, array(
                'label' => false,
                'config' => array(
                    'uiColor' => '#ffffff',
                )
            ))
            ->add('slug')
            ->add('leader', EntityType::class, array(
                'required' => true,
                'placeholder' => 'Animateurs',
                'class' => User::class,
                'choice_label' => 'email',
                'expanded'  => false,
                ))
            ->add('isOpen',CheckboxType::class, [
                'label_attr' => ['class' => 'switch-custom'],
            ])
            //->add('logoName')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Questionnaire::class,
        ]);
    }
}
