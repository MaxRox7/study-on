<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_course', type: 'integer')]
    private ?int $idCourse = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $symbolCode = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title_course = null;

    #[ORM\Column(type: 'text', length: 1000, nullable: true)]
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
        return $this->title_course;
    }

    public function setTitleCourse(string $title_course): static
    {
        $this->title_course = $title_course;

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
