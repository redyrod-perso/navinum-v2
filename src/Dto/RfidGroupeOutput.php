<?php

namespace App\Dto;

use Symfony\Component\Uid\Uuid;

final readonly class RfidGroupeOutput
{
    public function __construct(
        public Uuid $id,
        public string $nom,
    ) {
    }
}
