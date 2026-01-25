<?php

namespace App\Dto;


use Symfony\Component\Serializer\Attribute\Groups;

final readonly class RfidGroupeOutput
{
    public function __construct(
        #[Groups(['rfid_groupe:read'])]
        public string $id,
        #[Groups(['rfid_groupe:read'])]
        public string $nom,
    ) {
    }
}
