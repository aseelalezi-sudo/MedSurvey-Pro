<?php

namespace App\Traits;

use App\Support\Cuid;

trait UsesCuid
{
    protected static function bootUsesCuid(): void
    {
        static::creating(function ($model): void {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = Cuid::make();
            }
        });
    }
}
