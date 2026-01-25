<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Dto\RfidGroupeOutput;
use App\Entity\RfidGroupe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RfidGroupeProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $isCollection = $operation instanceof CollectionOperationInterface;

        $data = $isCollection
            ? $this->collectionProvider->provide($operation, $uriVariables, $context)
            : $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$data) {
            return null;
        }

        if ($isCollection) {
            $dtos = [];
            foreach ($data as $rfidGroupe) {
                $dtos[] = $this->transformToDto($rfidGroupe);
            }

            if ($data instanceof PaginatorInterface) {
                return new TraversablePaginator(
                    new \ArrayIterator($dtos),
                    $data->getCurrentPage(),
                    $data->getItemsPerPage(),
                    $data->getTotalItems()
                );
            }

            return $dtos;
        }

        return $this->transformToDto($data);
    }

    private function transformToDto(RfidGroupe $rfidGroupe): RfidGroupeOutput
    {
        return new RfidGroupeOutput(
            id: $rfidGroupe->getId()->toRfc4122(),
            nom: $rfidGroupe->getNom(),
        );
    }
}
