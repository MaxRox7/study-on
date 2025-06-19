<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\CourseType;
use App\Service\CourseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CourseController extends AbstractController
{
    private CourseService $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
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

    #[Route('/courses/create', name: 'course_create')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function create_course(Request $request): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = [
                'courseType' => $form->get('courseType')->getData(),
                'coursePrice' => $form->get('coursePrice')->getData(),
            ];

            $result = $this->courseService->handleCourseCreation($course, $formData);

            if ($result['success']) {
                $this->addFlash('success', 'Курс успешно создан');
                return $this->redirectToRoute('course_show', ['idCourse' => $result['course']->getIdCourse()]);
            } else {
                foreach ($result['errors'] as $field => $message) {
                    $this->addFlash('error', $message);
                }
            }
        }

        return $this->render('course/create.html.twig', [
            'form' => $form->createView(),
        ]);
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

        $form = $this->createForm(CourseType::class, $course);
        
        // Устанавливаем значения из биллинга для unmapped полей
        $billingFormData = $this->courseService->prepareCourseEditForm($course);
        if (!empty($billingFormData['courseType'])) {
            $form->get('courseType')->setData($billingFormData['courseType']);
        }
        if (!empty($billingFormData['coursePrice'])) {
            $form->get('coursePrice')->setData($billingFormData['coursePrice']);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = [
                'courseType' => $form->get('courseType')->getData(),
                'coursePrice' => $form->get('coursePrice')->getData(),
            ];

            $result = $this->courseService->handleCourseUpdate($course, $formData);

            if ($result['success']) {
                $this->addFlash('success', 'Изменения успешно сохранены');
                return $this->redirectToRoute('course_show', ['idCourse' => $idCourse]);
            } else {
                foreach ($result['errors'] as $field => $message) {
                    $this->addFlash('error', $message);
                }
            }
        }

        return $this->render('course/edit.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
        ]);
    }

    #[Route('/courses/{idCourse}', name: 'course_show', requirements: ['idCourse' => '\d+'])]
    public function show(int $idCourse, Request $request): Response
    {
        $course = $this->courseService->getCourse($idCourse);

        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        $lessonFormData = $this->courseService->createLessonForm($course);
        $lesson = $lessonFormData['lesson'];
        $form = $lessonFormData['form'];

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->courseService->createLesson($lesson);
            $this->addFlash('success', 'Урок успешно добавлен!');
            return $this->redirectToRoute('course_show', ['idCourse' => $course->getIdCourse()]);
        }

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
            'lessons' => $course->getLessons(),
        ]);
    }
}
