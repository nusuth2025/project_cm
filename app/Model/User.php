<?php
declare(strict_types=1);

namespace App\Model;

class User
{
    public int $id;
    public string $username;
    public string $email;
    public string $passwordHash;
    public \DateTimeImmutable $createdAt;

    public static function fromRow(array $row): self
    {
        $user = new self();
        $user->id           = (int) $row['id'];
        $user->username     = $row['username'];
        $user->email        = $row['email'];
        $user->passwordHash = $row['password_hash'];
        $user->createdAt    = new \DateTimeImmutable($row['created_at']);
        return $user;
    }
}
