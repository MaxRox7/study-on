<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private $email;

    /**
     * @var list<string> The user roles
     */
    private $roles = [];

    /**
     * @var string|null The JWT token of the user
     */
    private $apiToken;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * Get the JWT token for the user.
     *
     * @return string|null
     */
    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    /**
     * Set the JWT token for the user.
     *
     * @param string|null $apiToken
     * @return $this
     */
    public function setApiToken(?string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }
}
