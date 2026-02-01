<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConfigController extends AbstractController
{
    #[Route('/config.js', name: 'config_js', methods: ['GET'])]
    public function configJs(): Response
    {
        $mercurePublicUrl = $this->getParameter('mercure.public_url');

        $js = <<<JS
// Configuration injectée depuis le backend
window.APP_CONFIG = {
    MERCURE_PUBLIC_URL: '{$mercurePublicUrl}'
};
JS;

        $response = new Response($js);
        $response->headers->set('Content-Type', 'application/javascript');
        $response->headers->set('Cache-Control', 'public, max-age=3600'); // Cache 1h

        return $response;
    }
}
