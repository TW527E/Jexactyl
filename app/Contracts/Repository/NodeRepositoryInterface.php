<?php

namespace Everest\Contracts\Repository;

interface NodeRepositoryInterface extends RepositoryInterface
{
    public const THRESHOLD_PERCENTAGE_LOW = 75;
    public const THRESHOLD_PERCENTAGE_MEDIUM = 90;

}
