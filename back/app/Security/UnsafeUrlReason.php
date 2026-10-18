<?php

namespace App\Security;

enum UnsafeUrlReason: string
{
    case Invalid = 'invalid';
    case TooLong = 'too_long';
    case UnsupportedScheme = 'unsupported_scheme';
    case Credentials = 'credentials';
    case ForbiddenDestination = 'forbidden_destination';

    public function message(): string
    {
        return match ($this) {
            self::Invalid => 'The :attribute must be a valid URL.',
            self::TooLong => 'The :attribute is too long.',
            self::UnsupportedScheme => 'The :attribute must use http or https.',
            self::Credentials => 'The :attribute must not contain a username or password.',
            self::ForbiddenDestination => 'The :attribute points to an address that is not allowed.',
        };
    }
}
