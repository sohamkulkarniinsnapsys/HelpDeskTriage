<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Access = 'access';
    case Hardware = 'hardware';
    case Network = 'network';
    case Bug = 'bug';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Access',
            self::Hardware => 'Hardware',
            self::Network => 'Network',
            self::Bug => 'Bug',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Access => 'key',
            self::Hardware => 'desktop',
            self::Network => 'wifi',
            self::Bug => 'bug',
            self::Other => 'help-circle',
        };
    }
}
