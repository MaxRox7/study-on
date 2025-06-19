<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_course', type: 'integer')]
    private ?int $idCourse = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Символьный код обязателен.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Символьный код должен содержать минимум {{ limit }} символа.',
        maxMessage: 'Символьный код не может превышать {{ limit }} символов.'
    )]
    private ?string $symbolCode = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Название курса обязательно.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Название курса должно содержать минимум {{ limit }} символа.',
        maxMessage: 'Название курса не может превышать {{ limit }} символов.'
    )]
    private ?string $titleCourse = null;

    #[ORM\Column(type: 'text', length: 1000, nullable: true)]
    #[Assert\Length(
        min: 3,
        max: 1000,
        minMessage: 'Описание курса должно содержать минимум {{ limit }} символа.',
        maxMessage: 'Описание курса не может превышать {{ limit }} символов.',
    )]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Lesson::class, cascade: ['persist', 'remove'])]
    private Collection $lessons;

    public function __construct()
    {
        $this->lessons = new ArrayCollection();
    }

    public function getIdCourse(): ?int
    {
        return $this->idCourse;
    }

    public function getSymbolCode(): ?string
    {
        return $this->symbolCode;
    }

    public function setSymbolCode(string $symbolCode): static
    {
        $this->symbolCode = $symbolCode;

        return $this;
    }

    public function getTitleCourse(): ?string
    {
        return $this->titleCourse;
    }

    public function setTitleCourse(string $titleCourse): static
    {
        $this->titleCourse = $titleCourse;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLessons(): Collection
    {
        return $this->lessons;
    }

    public function addLesson(Lesson $lesson): static
    {
        if (!$this->lessons->contains($lesson)) {
            $this->lessons[] = $lesson;
            $lesson->setCourse($this);
        }

        return $this;
    }

    public function removeLesson(Lesson $lesson): static
    {
        if ($this->lessons->removeElement($lesson)) {
            if ($lesson->getCourse() === $this) {
                $lesson->setCourse(null);
            }
        }

        return $this;
    }
}
