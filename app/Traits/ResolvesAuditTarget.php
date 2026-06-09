<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ResolvesAuditTarget
{
    /**
     * Resolves a model from the request attributes if it was already fetched by AuditMutatingApiRequests.
     * Otherwise, fetches it using the provided closure.
     *
     * @template T of Model
     *
     * @param  callable(): ?T  $fallback
     * @return T|null
     */
    protected function resolveAuditTarget(Request $request, string $attributeKey, callable $fallback): ?Model
    {
        $preTarget = $request->attributes->get($attributeKey);

        if ($preTarget instanceof Model) {
            return $preTarget;
        }

        return $fallback();
    }
}
