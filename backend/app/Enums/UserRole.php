<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Producer = 'producer';
    case Admin = 'admin';
}
