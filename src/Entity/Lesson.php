<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LessonRepository::class)]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_lesson', type: 'integer')]
    private ?int $idLesson = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'lessons')]
    #[ORM\JoinColumn(name: 'id_course', referencedColumnName: 'id_course', nullable: false)]
    private ?Course $course = null;

    #[ORM\Column(name: 'title_lesson', type: 'string', length: 255)] // Явно указываем имя колонки в БД
    #[Assert\NotBlank(message: 'Название урока обязательно.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Название урока должно содержать минимум {{ limit }} символа.',
        maxMessage: 'Название урока не может превышать {{ limit }} символов.'
    )]
    private ?string $titleLesson = null; // camelCase для свойства

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Содержимое урока обязательно.')]
    #[Assert\Length(
        min: 3,
        minMessage: 'Содержимое урока должно содержать минимум {{ limit }} символа.'
    )]
    private ?string $content = null;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[Assert\NotNull(message: 'Порядковый номер обязателен')]
    #[Assert\Positive(message: 'Порядковый номер должен быть положительным')]
    #[Assert\Range(
        min: 1,
        max: 1000,
        notInRangeMessage: 'Порядковый номер должен быть от {{ min }} до {{ max }}'
    )]
    private ?int $orderNumber = null;

    public function getIdLesson(): ?int
    {
        return $this->idLesson;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getTitleLesson(): ?string
    {
        return $this->titleLesson;
    }

    public function setTitleLesson(string $titleLesson): static
    {
        $this->titleLesson = $titleLesson;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getOrderNumber(): ?int
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?int $orderNumber): static
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }
}
