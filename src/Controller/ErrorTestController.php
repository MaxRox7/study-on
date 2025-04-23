<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ErrorTestController extends AbstractController
{
    #[Route('/test-error/{type}', name: 'test_error')]
    public function test(string $type): Response
    {
        return match ($type) {
            '403' => throw new AccessDeniedException('403 — доступ запрещён!'),
            '404' => throw new NotFoundHttpException('404 — страница не найдена!'),
            '500' => throw new \Exception('500 — внутренняя ошибка сервера!'),
            default => throw new \Exception('500 — внутренняя ошибка сервера!'),
        };
    }
}
