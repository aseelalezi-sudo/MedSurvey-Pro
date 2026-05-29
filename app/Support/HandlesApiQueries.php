<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HandlesApiQueries
{
    protected function getPagination(Request $request): array
    {
        return [
            "page" => max(1, (int) $request->query("page", 1)),
            "limit" => min(200, max(1, (int) $request->query("limit", 50))),
        ];
    }

    protected function applyFilters(Builder $query, Request $request, array $filterMap): Builder
    {
        foreach ($filterMap as $field => $queryParam) {
            $query->when(
                $request->query($queryParam),
                fn ($q) => $q->where($field, $request->query($queryParam))
            );
        }
        return $query;
    }

    protected function applySearch(Builder $query, Request $request, array $columns): Builder
    {
        if ($search = $request->query("search")) {
            $safe = addcslashes($search, "%_");
            $query->where(function ($q) use ($safe, $columns): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, "like", "%{$safe}%");
                }
            });
        }
        return $query;
    }
}
