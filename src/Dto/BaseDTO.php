<?php

namespace Polymer\DTO;

use Cerbero\Dto\Dto;
use const Cerbero\Dto\IGNORE_UNKNOWN_PROPERTIES;
use const Cerbero\Dto\NONE;
use const Cerbero\Dto\PARTIAL;

class BaseDTO extends Dto
{
    public function __construct(array $data = [], int $flags = NONE)
    {
        parent::__construct($data, $flags | PARTIAL | IGNORE_UNKNOWN_PROPERTIES);
    }
}