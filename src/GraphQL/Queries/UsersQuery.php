<?php

namespace Statamic\GraphQL\Queries;

use GraphQL\Type\Definition\Type;
use Statamic\Facades\GraphQL;
use Statamic\Facades\User;
use Statamic\GraphQL\Middleware\ResolvePage;
use Statamic\GraphQL\Types\JsonArgument;
use Statamic\GraphQL\Types\UserType;
use Statamic\Support\Arr;
use Statamic\Support\Str;
use Statamic\Tags\Concerns\QueriesConditions;

class UsersQuery extends Query
{
    use QueriesConditions;

    protected $attributes = [
        'name' => 'users',
    ];

    protected $middleware = [
        ResolvePage::class,
    ];

    public function type(): Type
    {
        return GraphQL::paginate(GraphQL::type(UserType::NAME));
    }

    public function args(): array
    {
        return [
            'limit' => GraphQL::int(),
            'page' => GraphQL::int(),
            'filter' => GraphQL::type(JsonArgument::NAME),
            'sort' => GraphQL::listOf(GraphQL::string()),
        ];
    }

    public function resolve($root, $args)
    {
        $query = User::query();

        if ($filters = $args['filter'] ?? null) {
            $this->filterQuery($query, $filters);
        }

        if ($sort = $args['sort'] ?? null) {
            $this->sortQuery($query, $sort);
        }

        return $query->paginate($args['limit'] ?? 1000);
    }

    private function filterQuery($query, $filters)
    {
        $filters = collect($filters)->reject(function ($_, $filter) {
            return Str::startsWith($filter, 'password');
        });

        foreach ($filters as $field => $definitions) {
            if (! is_array($definitions)) {
                $definitions = [['equals' => $definitions]];
            }

            if (Arr::assoc($definitions)) {
                $definitions = collect($definitions)->map(function ($value, $key) {
                    return [$key => $value];
                })->values()->all();
            }

            foreach ($definitions as $definition) {
                $condition = array_keys($definition)[0];
                $value = array_values($definition)[0];
                $this->queryCondition($query, $field, $condition, $value);
            }
        }
    }

    private function sortQuery($query, $sorts)
    {
        foreach ($sorts as $sort) {
            $order = 'asc';

            if (Str::contains($sort, ' ')) {
                [$sort, $order] = explode(' ', $sort);
            }

            $query->orderBy($sort, $order);
        }
    }
}
