<?php

namespace App\Service;

use App\Entity\Lesson;
use App\Entity\Course;
use App\Form\LessonType;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\BillingClient;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LessonService
{
    private EntityManagerInterface $entityManager;
    private BillingClient $billingClient;
    private ValidatorInterface $validator;
    private FormFactoryInterface $formFactory;

    public function __construct(
        EntityManagerInterface $entityManager, 
        BillingClient $billingClient, 
        ValidatorInterface $validator,
        FormFactoryInterface $formFactory
    ) {
        $this->entityManager = $entityManager;
        $this->billingClient = $billingClient;
        $this->validator = $validator;
        $this->formFactory = $formFactory;
    }

    public function getLesson(int $idLesson): ?Lesson
    {
        return $this->entityManager->getRepository(Lesson::class)->find($idLesson);
    }

    public function canUserAccessLesson(Lesson $lesson, $user): void
    {
        $course = $lesson->getCourse();
        // Супер-админ всегда имеет доступ
        if ($user && method_exists($user, 'getRoles') && in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return;
        }
        if (method_exists($course, 'getType') && $course->getType() === 3) {
            return;
        }
        if ($user) {
            try {
                $transactions = $this->billingClient->getTransactions($user->getApiToken(), ['type' => 'payment', 'course_code' => $course->getSymbolCode()]);
                $hasAccess = false;
                foreach ($transactions as $tr) {
                    if ($tr['type'] === 'payment' && (!isset($tr['expires_at']) || (isset($tr['expires_at']) && $tr['expires_at'] > (new \DateTimeImmutable())->format(DATE_ATOM)))) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (!$hasAccess) {
                    throw new AccessDeniedException('Курс не оплачен или аренда истекла');
                }
            } catch (\Throwable $e) {
                throw new AccessDeniedException('Ошибка проверки доступа к курсу');
            }
        }
    }

    public function deleteLesson(int $idLesson): ?int
    {
        $lesson = $this->getLesson($idLesson);
        if (!$lesson) {
            return null;
        }
        $courseId = $lesson->getCourse()->getIdCourse();
        $this->entityManager->remove($lesson);
        $this->entityManager->flush();
        return $courseId;
    }

    public function validateLesson(Lesson $lesson)
    {
        $errors = $this->validator->validate($lesson);
        return $errors;
    }

    public function createEditForm(Lesson $lesson): mixed
    {
        return $this->formFactory->create(LessonType::class, $lesson);
    }

    public function handleLessonUpdate(Lesson $lesson): void
    {
        $this->updateLesson();
    }

    public function updateLesson(): void
    {
        $this->entityManager->flush();
    }
} 