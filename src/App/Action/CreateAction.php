<?php

namespace App\Action;

use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Zend\Expressive\Template\TemplateRendererInterface;
use App\Service\RaffleService;

class CreateAction
{
    use RenderTrait;

    protected $raffleService;

    public function __construct(TemplateRendererInterface $templateRenderer, RaffleService $raffleService)
    {
        $this->view = $templateRenderer;
        $this->raffleService = $raffleService;
    }

    public function __invoke(Request $req, Response $res, callable $next) {
        parse_str($req->getBody(), $body);

        $items = trim($body['raffle_items']);
        $name = trim($body['raffle_name']);

        $errors = [];

        if (!strlen($items))
            $errors['raffle_name'] = true;
        if (!strlen($name))
            $errors['raffle_items'] = true;

        if (count($errors))
            return $this->render($res, 'home',
                ['raffleItems' => $items, 'raffleName' => $name, 'errors' => $errors]);

        $id = $this->raffleService->create($name, explode("\n", trim($items)));

        return FigResponseCookies::set($res, SetCookie::create('sid' . $id, $this->raffleService->getSid($id)))
            ->withHeader('Location', '/')->withStatus(302);
    }
}
