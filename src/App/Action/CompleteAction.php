<?php

namespace App\Action;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Zend\Expressive\Template\TemplateRendererInterface;
use App\Service\RaffleService;
use App\Service\Auth;

class CompleteAction
{
    use RenderTrait;

    protected $auth;
    protected $raffleService;

    public function __construct(TemplateRendererInterface $templateRenderer, RaffleService $raffleService, Auth $auth)
    {
        $this->view = $templateRenderer;
        $this->auth = $auth;
        $this->raffleService = $raffleService;
    }

    public function __invoke(Request $req, Response $res, callable $next) {
        $id = $req->getAttribute('id');
        $rs = $this->raffleService;

        if (!$rs->raffleExists($id))
            return $this->renderNotFound();

        if (!$this->auth->isAuthorized($req, $id))
            return $res->withHeader('Location', '/')->withStatus('302', 'Found');

        $data = ['raffleName' => $rs->getName($id)];

        if (!$rs->isComplete($id))
            $data['winnerNumbers'] = $rs->complete($id);

        return $this->render($res, 'finished', $data);
    }
}
