<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\CourseType;
use App\Form\LessonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CourseService
{
    private EntityManagerInterface $entityManager;
    private BillingClient $billingClient;
    private TokenStorageInterface $tokenStorage;
    private ValidatorInterface $validator;
    private FormFactoryInterface $formFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        BillingClient $billingClient,
        TokenStorageInterface $tokenStorage,
        ValidatorInterface $validator,
        FormFactoryInterface $formFactory
    ) {
        $this->entityManager = $entityManager;
        $this->billingClient = $billingClient;
        $this->tokenStorage = $tokenStorage;
        $this->validator = $validator;
        $this->formFactory = $formFactory;
    }

    public function getCourse(int $idCourse): ?Course
    {
        return $this->entityManager->getRepository(Course::class)->find($idCourse);
    }

    public function getAllCourses(): array
    {
        return $this->entityManager->getRepository(Course::class)->findAll();
    }

    public function createCourse(string $symbolCode, string $titleCourse, ?string $description): array
    {
        $course = new Course();
        $course->setSymbolCode($symbolCode);
        $course->setTitleCourse($titleCourse);
        $course->setDescription($description);

        $errors = $this->validator->validate($course);
        $errorMessages = [];

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $propertyPath = $error->getPropertyPath();
                // Преобразуем camelCase в snake_case
                $fieldName = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $propertyPath));
                $errorMessages[$fieldName] = $error->getMessage();
            }
            return [
                'success' => false,
                'errors' => $errorMessages,
                'course' => null
            ];
        }

        $this->entityManager->persist($course);
        $this->entityManager->flush();

        return [
            'success' => true,
            'errors' => [],
            'course' => $course
        ];
    }

    public function createCourseWithBilling(string $symbolCode, string $titleCourse, ?string $description, string $courseType, ?float $coursePrice): array
    {
        // Проверяем валидность данных курса для StudyOn
        $course = new Course();
        $course->setSymbolCode($symbolCode);
        $course->setTitleCourse($titleCourse);
        $course->setDescription($description);

        $errors = $this->validator->validate($course);
        $errorMessages = [];

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $propertyPath = $error->getPropertyPath();
                $fieldName = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $propertyPath));
                $errorMessages[$fieldName] = $error->getMessage();
            }
            return [
                'success' => false,
                'errors' => $errorMessages,
                'course' => null
            ];
        }

        // Получаем токен пользователя
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return [
                'success' => false,
                'errors' => ['auth' => 'Требуется аутентификация'],
                'course' => null
            ];
        }

        $user = $token->getUser();
        $apiToken = $user->getApiToken();

        if (!$apiToken) {
            return [
                'success' => false,
                'errors' => ['auth' => 'Отсутствует API токен'],
                'course' => null
            ];
        }

        // Подготавливаем данные для биллинга
        $billingData = [
            'code' => $symbolCode,
            'title' => $titleCourse,
            'type' => $courseType,
        ];

        if ($courseType !== 'free' && $coursePrice !== null) {
            $billingData['price'] = $coursePrice;
        }

        try {
            // Создаем курс в биллинге
            $billingResponse = $this->billingClient->createCourse($apiToken, $billingData);
            
            if (!isset($billingResponse['success']) || !$billingResponse['success']) {
                return [
                    'success' => false,
                    'errors' => ['billing' => 'Ошибка создания курса в биллинге'],
                    'course' => null
                ];
            }

            // Если в биллинге все успешно, создаем курс в StudyOn
            $this->entityManager->persist($course);
            $this->entityManager->flush();

            return [
                'success' => true,
                'errors' => [],
                'course' => $course
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errors' => ['billing' => 'Ошибка связи с биллингом: ' . $e->getMessage()],
                'course' => null
            ];
        }
    }

    public function updateCourseWithBillingFixed(string $originalSymbolCode, Course $course, string $newSymbolCode, string $courseType, ?float $coursePrice): array
    {
        
        // Получаем токен пользователя
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return [
                'success' => false,
                'errors' => ['auth' => 'Требуется аутентификация']
            ];
        }

        $user = $token->getUser();
        $apiToken = $user->getApiToken();

        if (!$apiToken) {
            return [
                'success' => false,
                'errors' => ['auth' => 'Отсутствует API токен']
            ];
        }

        // Подготавливаем данные для биллинга
        $billingData = [
            'code' => $newSymbolCode,
            'title' => $course->getTitleCourse(),
            'type' => $courseType,
        ];

        if ($courseType !== 'free' && $coursePrice !== null) {
            $billingData['price'] = $coursePrice;
        }

        try {
            // Логируем для отладки
            error_log("Updating course: original='{$originalSymbolCode}', new='{$newSymbolCode}'");
            
            // Если код изменился, проверим, что новый код не занят
            if ($newSymbolCode !== $originalSymbolCode) {
                $existingCourse = $this->getCourseFromBilling($newSymbolCode);
                if ($existingCourse) {
                    return [
                        'success' => false,
                        'errors' => ['billing' => 'Курс с кодом "' . $newSymbolCode . '" уже существует в биллинге']
                    ];
                }
            }

            // Используем оригинальный код в URL для обновления
            $billingResponse = $this->billingClient->updateCourse($apiToken, $originalSymbolCode, $billingData);
            
            if (!isset($billingResponse['success']) || !$billingResponse['success']) {
                return [
                    'success' => false,
                    'errors' => ['billing' => 'Ошибка обновления курса в биллинге']
                ];
            }

            // Если в биллинге все успешно, сохраняем изменения в StudyOn
            $this->entityManager->flush();

            return [
                'success' => true,
                'errors' => []
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errors' => ['billing' => 'Ошибка связи с биллингом: ' . $e->getMessage()]
            ];
        }
    }

    public function getCourseFromBilling(string $courseCode): ?array
    {
        try {
            $courses = $this->billingClient->getCourses();
            foreach ($courses as $course) {
                if ($course['code'] === $courseCode) {
                    return $course;
                }
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function createLesson(Lesson $lesson): void
    {
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();
    }

    public function updateCourse(Course $course): void
    {
        $this->entityManager->flush();
    }

    public function deleteCourse(int $idCourse): bool
    {
        $course = $this->getCourse($idCourse);
        
        if (!$course) {
            return false;
        }

        $this->entityManager->remove($course);
        $this->entityManager->flush();
        
        return true;
    }

    public function getCoursesWithBillingInfo($user = null): array
    {
        $courses = $this->getAllCourses();
        $token = $user ? $user->getApiToken() : null;
        $balance = null;
        
        if ($user && $token) {
            try {
                $userData = $this->billingClient->getCurrentUser($token);
                $balance = $userData['balance'] ?? null;
            } catch (\Throwable $e) {
                $balance = null;
            }
        }
        
        try {
            $billingCourses = $this->billingClient->getCourses();
            $userCourses = $token ? $this->billingClient->getTransactions($token, ['type' => 'payment']) : [];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'viewCourses' => [],
                'balance' => null
            ];
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
                    $transactions = $this->billingClient->getTransactions($token, ['type' => 'payment', 'course_code' => $code]);
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
                    // Обработка ошибки или логирование
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
        
        return [
            'error' => false,
            'viewCourses' => $viewCourses,
            'balance' => $balance
        ];
    }

    public function handleCourseCreation(Course $course, array $formData): array
    {
        $courseType = $formData['courseType'];
        $coursePrice = $formData['coursePrice'];

        // Дополнительная валидация для платных курсов
        if (in_array($courseType, ['rent', 'buy']) && (empty($coursePrice) || $coursePrice <= 0)) {
            return [
                'success' => false,
                'errors' => ['coursePrice' => 'Для платных курсов необходимо указать стоимость больше 0'],
                'course' => null
            ];
        }

        return $this->createCourseWithBilling(
            $course->getSymbolCode(),
            $course->getTitleCourse(),
            $course->getDescription(),
            $courseType,
            $coursePrice
        );
    }

    public function prepareCourseEditForm(Course $course): array
    {
        // Получаем текущие данные курса из биллинга
        $billingCourse = $this->getCourseFromBilling($course->getSymbolCode());
        
        $formData = [];
        if ($billingCourse) {
            $formData['courseType'] = $billingCourse['type'];
            if (isset($billingCourse['price'])) {
                $formData['coursePrice'] = (float) $billingCourse['price'];
            }
        }

        return $formData;
    }

    public function handleCourseUpdate(Course $course, array $formData): array
    {
        $courseType = $formData['courseType'];
        $coursePrice = $formData['coursePrice'];
        $originalSymbolCode = $formData['originalSymbolCode'];
        
        // Новый код из обновленного объекта
        $newSymbolCode = $course->getSymbolCode();

        $result = $this->updateCourseWithBillingFixed(
            $originalSymbolCode,
            $course,
            $newSymbolCode,
            $courseType,
            $coursePrice
        );

        if ($result['success']) {
            $this->updateCourse($course);
        }

        return $result;
    }

    public function createLessonForm(Course $course): array
    {
        $lesson = new Lesson();
        $lesson->setCourse($course);

        $form = $this->formFactory->create(LessonType::class, $lesson, [
            'submit_label' => 'Добавить урок'
        ]);

        return [
            'lesson' => $lesson,
            'form' => $form
        ];
    }
} 