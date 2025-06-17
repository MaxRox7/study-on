<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titleCourse', TextType::class, [
                'label' => 'Название курса',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите название курса'
                ]
            ])
            ->add('symbolCode', TextType::class, [
                'label' => 'Символьный код',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите символьный код курса'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 8,
                    'placeholder' => 'Добавьте описание курса'
                ]
            ])
            ->add('courseType', ChoiceType::class, [
                'label' => 'Тип курса',
                'mapped' => false,
                'choices' => [
                    'Бесплатный' => 'free',
                    'Аренда' => 'rent',
                    'Покупка' => 'buy',
                ],
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'course_type'
                ]
            ])
            ->add('coursePrice', NumberType::class, [
                'label' => 'Стоимость курса',
                'mapped' => false,
                'required' => false, // Будет обрабатываться в контроллере
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите стоимость курса',
                    'step' => '0.01',
                    'min' => '0'
                ],
                'help' => 'Обязательно для курсов типа "Аренда" и "Покупка"'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
} 