<?php

namespace App\Controller\Admin;

use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Sylius\Component\Grid\View\GridViewFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GridController extends AbstractController
{
    public function index(
        Request $request,
        GridProviderInterface $gridProvider,
        GridViewFactoryInterface $gridViewFactory,
        string $grid
    ): Response {
        $definition = $gridProvider->get($grid);
        $parameters = new Parameters($request->query->all());
        $gridView = $gridViewFactory->create($definition, $parameters);

        return $this->render('admin/grid/index.html.twig', [
            'grid' => $gridView,
            'grid_code' => $grid,
        ]);

    }
}
