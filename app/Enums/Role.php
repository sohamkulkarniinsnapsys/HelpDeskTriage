<?php

namespace App\Enums;

enum Role: string
{
    case Employee = 'employee';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Agent => 'Agent',
        };
    }

    public function isAgent(): bool
    {
        return $this === self::Agent;
    }

    public function isEmployee(): bool
    {
        return $this === self::Employee;
    }
}
