<?php

// src/Form/LessonType.php

namespace App\Form;

use App\Entity\Lesson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LessonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titleLesson', TextType::class, [
                'label' => 'Название урока',
                'required' => true,
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Содержимое урока',
                'required' => true,
            ])
            ->add('orderNumber', IntegerType::class, [
                'label' => 'Порядковый номер',
                'required' => true,
            ]);
            
        if ($options['include_submit']) {
            $builder->add('save', SubmitType::class, [
                'label' => $options['submit_label']
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'include_submit' => true,
            'submit_label' => 'Сохранить',
        ]);
    }
}
