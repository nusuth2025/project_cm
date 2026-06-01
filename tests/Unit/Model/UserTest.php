<?php
declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testFromRowHydratesAllFields(): void
    {
        $row = [
            'id'            => '4',
            'username'      => 'testuser',
            'email'         => 'test@example.com',
            'password_hash' => '$2y$12$abc...',
            'created_at'    => '2024-03-15 08:00:00',
        ];

        $user = User::fromRow($row);

        self::assertSame(4, $user->id);
        self::assertSame('testuser', $user->username);
        self::assertSame('test@example.com', $user->email);
        self::assertSame('$2y$12$abc...', $user->passwordHash);
        self::assertInstanceOf(\DateTimeImmutable::class, $user->createdAt);
        self::assertSame('2024-03-15', $user->createdAt->format('Y-m-d'));
    }

    public function testFromRowCastsIdToInt(): void
    {
        $row = [
            'id'            => '99',
            'username'      => 'admin',
            'email'         => 'admin@example.com',
            'password_hash' => 'hash',
            'created_at'    => '2024-01-01 00:00:00',
        ];

        $user = User::fromRow($row);

        self::assertIsInt($user->id);
        self::assertSame(99, $user->id);
    }
}
