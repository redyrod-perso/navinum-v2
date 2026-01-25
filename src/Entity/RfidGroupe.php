<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Dto\RfidGroupeOutput;
use App\Repository\RfidGroupeRepository;
use App\State\Provider\RfidGroupeProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RfidGroupeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'rfid_groupe')]
#[ORM\UniqueConstraint(name: 'uniq_rfid_groupe_nom', columns: ['nom'])]
#[UniqueEntity(
    fields: ['nom'],
    message: 'Un groupe avec ce nom existe déjà.'
)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            validationContext: ['groups' => ['Default', 'rfid_groupe:create']],
            processor: \App\State\Processor\RfidGroupeProcessor::class
        ),
        new Put(
            processor: \App\State\Processor\RfidGroupeProcessor::class
        ),
        new Delete(
            processor: \App\State\Processor\RfidGroupeProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['rfid_groupe:read']],
    denormalizationContext: ['groups' => ['rfid_groupe:write']],
    paginationEnabled: true,
    paginationItemsPerPage: 30
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'nom' => 'partial'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt'])]
class RfidGroupe
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le nom ne peut pas être vide.', groups: ['rfid_groupe:create'])]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['rfid_groupe:read', 'rfid_groupe:write'])]
    private ?string $nom = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    #[Groups(['rfid_groupe:write'])]
    private bool $isTosync = true;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function isTosync(): bool
    {
        return $this->isTosync;
    }

    public function setIsTosync(bool $isTosync): self
    {
        $this->isTosync = $isTosync;
        return $this;
    }
}
