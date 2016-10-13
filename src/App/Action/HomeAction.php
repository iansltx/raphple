<?php

namespace App\Action;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Zend\Expressive\Template\TemplateRendererInterface;

class HomeAction
{
    use RenderTrait;

    public function __construct(TemplateRendererInterface $templateRenderer)
    {
        $this->view = $templateRenderer;
    }

    public function __invoke(Request $req, Response $res, callable $next) {
        return $this->render($res, 'home');
    }
}
