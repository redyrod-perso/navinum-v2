<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RfidGroupe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class RfidGroupeProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private HubInterface $hub,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isDelete = $operation->getName() === '_api_/rfid_groupes/{id}{._format}_delete';

        // Persister ou supprimer l'entité
        $result = $isDelete
            ? $this->removeProcessor->process($data, $operation, $uriVariables, $context)
            : $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // Publier sur Mercure uniquement pour POST (création)
        if (!$isDelete && $result instanceof RfidGroupe && $operation->getName() === '_api_/rfid_groupes{._format}_post') {
            try {
                $update = new Update(
                    'rfid-groupes',
                    json_encode([
                        'type' => 'groupe_created',
                        'groupe' => [
                            'id' => $result->getId()->toRfc4122(),
                            'nom' => $result->getNom(),
                        ]
                    ])
                );

                $this->hub->publish($update);
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas la création
                error_log('Erreur publication Mercure: ' . $e->getMessage());
            }
        }

        return $result;
    }
}
