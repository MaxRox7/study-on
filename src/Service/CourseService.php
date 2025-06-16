<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CourseService
{
    private EntityManagerInterface $entityManager;
    private BillingClient $billingClient;
    private TokenStorageInterface $tokenStorage;
    private ValidatorInterface $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        BillingClient $billingClient,
        TokenStorageInterface $tokenStorage,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->billingClient = $billingClient;
        $this->tokenStorage = $tokenStorage;
        $this->validator = $validator;
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
} 