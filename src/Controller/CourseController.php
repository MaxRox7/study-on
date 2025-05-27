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
use App\Service\BillingClient;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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

        // Исправлены имена полей согласно сущности
        $form = $this->createFormBuilder($lesson)
            ->add('titleLesson') // camelCase для соответствия сущности
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

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
            'lessons' => $course->getLessons(),
        ]);
    }

    #[Route('/courses', name: 'course_show_all')]
    public function show_all(EntityManagerInterface $entityManager, BillingClient $billingClient, TokenStorageInterface $tokenStorage): Response
    {
        $courses = $entityManager->getRepository(Course::class)->findAll();
        $user = $this->getUser();
        $token = $user ? $user->getApiToken() : null;
        $balance = null;
        if ($user && $token) {
            try {
                $userData = $billingClient->getCurrentUser($token);
                $balance = $userData['balance'] ?? null;
            } catch (\Throwable $e) {
                $balance = null;
            }
        }
        try {
            $billingCourses = $billingClient->getCourses();
            $userCourses = $token ? $billingClient->getTransactions($token, ['type' => 'payment']) : [];
        } catch (\Throwable $e) {
            dd($e); // или логгирование
            return $this->render('course/billing_unavailable.html.twig');
        }
        $billingByCode = [];
        foreach ($billingCourses as $bCourse) {
            $billingByCode[$bCourse['code']] = $bCourse;
        }
        $userPaid = [];
        foreach ($userCourses as $tr) {
            if (!empty($tr['course_code'])) {
                $userPaid[$tr['course_code']] = [
                    'expires_at' => $tr['expires_at'] ?? null
                ];
            }
        }
        $viewCourses = [];
        foreach ($courses as $course) {
            $code = $course->getSymbolCode();
            $billing = $billingByCode[$code] ?? null;
            $isBought = false;
            $isRented = false;
            $paidUntil = null;
            if ($user && $token && $billing && $billing['type'] !== 'free') {
                try {
                    $transactions = $billingClient->getTransactions($token, ['type' => 'payment', 'course_code' => $code]);
                    foreach ($transactions as $tr) {
                        if ($tr['type'] === 'payment') {
                            if (empty($tr['expires_at'])) {
                                $isBought = true;
                                break;
                            }
                            if (isset($tr['expires_at']) && $tr['expires_at']) {
                                if ($tr['expires_at'] > (new \DateTimeImmutable())->format(DATE_ATOM)) {
                                    $isRented = true;
                                }
                                $paidUntil = $tr['expires_at'];
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
            $canPay = null;
            if ($billing && $balance !== null && $billing['type'] != 'free' && isset($billing['price'])) {
                $canPay = $balance >= $billing['price'];
            }
            $viewCourses[] = [
                'entity' => $course,
                'billing' => $billing,
                'canPay' => $canPay,
                'isBought' => $isBought,
                'isRented' => $isRented,
                'paidUntil' => $paidUntil,
            ];
        }
        return $this->render('course/show_all.html.twig', [
            'viewCourses' => $viewCourses,
            'balance' => $balance,
        ]);
    }

    #[Route('/course/create', name: 'course_create')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
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
    #[IsGranted('ROLE_SUPER_ADMIN')]
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
    #[IsGranted('ROLE_SUPER_ADMIN')]
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
