<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Service\CourseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CourseController extends AbstractController
{
    private CourseService $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    #[Route('/courses/{idCourse}', name: 'course_show')]
    public function show(int $idCourse, Request $request): Response
    {
        $course = $this->courseService->getCourse($idCourse);

        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        $lesson = new Lesson();
        $lesson->setCourse($course);

        // Исправлены имена полей согласно сущности
        $form = $this->createFormBuilder($lesson)
            ->add('titleLesson') // camelCase для соответствия сущности
            ->add('content')
            ->add('orderNumber')
            ->add('save', SubmitType::class, ['label' => 'Добавить урок'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->courseService->createLesson($lesson);
            return $this->redirectToRoute('course_show', ['idCourse' => $idCourse]);
        }

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
            'lessons' => $course->getLessons(),
        ]);
    }

    #[Route('/courses', name: 'course_show_all')]
    public function show_all(): Response
    {
        $result = $this->courseService->getCoursesWithBillingInfo($this->getUser());
        
        if ($result['error']) {
            return $this->render('course/billing_unavailable.html.twig');
        }
        
        return $this->render('course/show_all.html.twig', [
            'viewCourses' => $result['viewCourses'],
            'balance' => $result['balance'],
        ]);
    }

    #[Route('/course/create', name: 'course_create')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function create_course(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $symbolCode = $request->request->get('symbolCode');
            $titleCourse = $request->request->get('title_course');
            $description = $request->request->get('description');

            $result = $this->courseService->createCourse($symbolCode, $titleCourse, $description);

            if (!$result['success']) {
                // Добавляем ошибки во флеш-сообщения
                foreach ($result['errors'] as $field => $message) {
                    $this->addFlash('error_'.$field, $message);
                }

                return $this->render('course/create.html.twig', [
                    'symbolCode' => $symbolCode,
                    'title_course' => $titleCourse,
                    'description' => $description,
                ]);
            }

            return $this->redirectToRoute('course_show', ['idCourse' => $result['course']->getIdCourse()]);
        }

        return $this->render('course/create.html.twig');
    }

    #[Route('/courses/{idCourse}/delete', name: 'course_delete')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete_course(int $idCourse): Response
    {
        $success = $this->courseService->deleteCourse($idCourse);
        
        if (!$success) {
            throw $this->createNotFoundException('Курс не найден');
        }

        return $this->redirectToRoute('course_show_all');
    }

    #[Route('/courses/{idCourse}/edit', name: 'course_edit')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(int $idCourse, Request $request): Response
    {
        $course = $this->courseService->getCourse($idCourse);

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
            $this->courseService->updateCourse($course);
            $this->addFlash('success', 'Изменения успешно сохранены');

            return $this->redirectToRoute('course_show', ['idCourse' => $idCourse]);
        }

        return $this->render('course/edit.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
        ]);
    }
}
