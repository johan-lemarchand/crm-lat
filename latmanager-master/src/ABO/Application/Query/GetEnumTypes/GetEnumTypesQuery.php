<?php

namespace App\ABO\Application\Query\GetEnumTypes;

readonly class GetEnumTypesQuery
{
    public function __construct(
        public string $type
    ) {}
} 