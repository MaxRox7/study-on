<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CourseController extends AbstractController
{
    #[Route('/courses/{idCourse}', name: 'course_show')]
    public function show(int $idCourse, EntityManagerInterface $entityManager, Request $request): Response
    {
        $course = $entityManager->getRepository(Course::class)->find($idCourse);

        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        $lesson = new Lesson();
        $lesson->setCourse($course);

        $form = $this->createFormBuilder($lesson)
            ->add('title_lesson') // Исправлено на snake_case для соответствия сущности
            ->add('content')
            ->add('orderNumber')
            ->add('save', SubmitType::class, ['label' => 'Добавить урок'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lesson);
            $entityManager->flush();

            return $this->redirectToRoute('course_show', ['idCourse' => $idCourse]);
        }

        $lessons = $course->getLessons();

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
            'lessons' => $lessons,
        ]);
    }

    #[Route('/courses', name: 'course_show_all')]
    public function show_all(EntityManagerInterface $entityManager): Response
    {
        $course = $entityManager->getRepository(Course::class)->findAll();

        if (!$course) {
            throw $this->createNotFoundException('Курсы не найден');
        }

        return $this->render('course/show_all.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/course/create', name: 'course_create')]
    public function create_course(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($request->isMethod('POST')) {
            $symbolCode = $request->request->get('symbolCode');
            $titleCourse = $request->request->get('title_course');
            $description = $request->request->get('description');

            $course = new Course();
            $course->setSymbolCode($symbolCode);
            $course->setTitleCourse($titleCourse);
            $course->setDescription($description);

            $errors = $validator->validate($course);

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $propertyPath = $error->getPropertyPath();
                    // Преобразуем camelCase в snake_case
                    $fieldName = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $propertyPath));
                    $this->addFlash('error_'.$fieldName, $error->getMessage());
                }

                return $this->render('course/create.html.twig', [
                    'symbolCode' => $symbolCode,
                    'title_course' => $titleCourse,
                    'description' => $description,
                ]);
            }

            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('course_show', ['idCourse' => $course->getIdCourse()]);
        }

        return $this->render('course/create.html.twig');
    }

    #[Route('/courses/{idCourse}/delete', name: 'course_delete')]
    public function delete_course(int $idCourse, EntityManagerInterface $entityManager): Response
    {
        // Ищем курс по его ID
        $course = $entityManager->getRepository(Course::class)->find($idCourse);

        // Если курс не найден, выбрасываем исключение
        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        // Удаляем курс (и все связанные уроки из-за каскадного удаления)
        $entityManager->remove($course);
        $entityManager->flush();

        // Перенаправляем на страницу со списком всех курсов
        return $this->redirectToRoute('course_show_all');
    }

    #[Route('/courses/{idCourse}/edit', name: 'course_edit')]
    public function edit(int $idCourse, EntityManagerInterface $entityManager, Request $request, EntityManagerInterface $em): Response
    {
        $course = $em->getRepository(Course::class)->find($idCourse);

        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        $form = $this->createFormBuilder($course)
            ->add('titleCourse', TextType::class, [
                'label' => 'Название курса',
                'constraints' => [
                    new NotBlank(['message' => 'Поле обязательно для заполнения']),
                    new Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Название должно быть не менее {{ limit }} символов',
                        'maxMessage' => 'Название должно быть не длиннее {{ limit }} символов',
                    ]),
                ],
            ])
            ->add('symbolCode', TextType::class, [
                'label' => 'Код курса',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
                'required' => false,
                'attr' => ['rows' => 8],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Изменения успешно сохранены');

            return $this->redirectToRoute('course_show', ['idCourse' => $idCourse]);
        }

        return $this->render('course/edit.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
        ]);
    }
}
