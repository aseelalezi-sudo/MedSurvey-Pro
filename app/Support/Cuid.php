<?php

namespace App\Support;

use Illuminate\Support\Str;

class Cuid
{
    public static function make(): string
    {
        return 'c'.Str::lower(Str::ulid()->toBase32());
    }
}

