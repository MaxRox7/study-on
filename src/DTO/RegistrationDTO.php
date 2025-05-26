<?php

// src/DTO/RegistrationDTO.php
namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RegistrationDTO
{
    #[Assert\NotBlank(message: "Email обязателен")]
    #[Assert\Email(message: "Неверный формат email")]
    public string $email = '';

    #[Assert\NotBlank(message: "Пароль обязателен")]
    #[Assert\Length(min: 6, minMessage: "Пароль должен содержать минимум {{ limit }} символов")]
    public string $password = '';

    #[Assert\NotBlank(message: "Подтверждение пароля обязательно")]
    #[Assert\EqualTo(propertyPath: "password", message: "Пароль и подтверждение пароля должны совпадать")]
    public string $confirmPassword = '';

    // Геттеры и сеттеры
    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getConfirmPassword(): string
    {
        return $this->confirmPassword;
    }

    public function setConfirmPassword(string $confirmPassword): self
    {
        $this->confirmPassword = $confirmPassword;
        return $this;
    }
}
