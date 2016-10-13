<?php

namespace App\Action;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Template\TemplateRendererInterface;
use App\Service\Auth;
use App\Service\RaffleService;

class GetAction
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

        parse_str($req->getUri()->getQuery(), $qs);

        if ($qs['show'] === 'entrants') {
            $output = ['is_complete' => $rs->isComplete($id)];

            $numbers = $rs->getEntrantPhoneNumbers($id);
            $output['count'] = count($numbers);

            if ($this->auth->isAuthorized($req, $id))
                $output['numbers'] = array_map(function ($number) {
                    return 'xxx-xxx-' . substr($number, -4);
                }, $numbers);

            return new JsonResponse($output);
        }

        if ($rs->isComplete($id))
            return $this->render($res, 'finished', ['raffleName' => $rs->getName($id)]);

        return $this->render($res, 'waiting', [
            'phoneNumber' => $rs->getPhoneNumber($id),
            'code' => $rs->getCode($id),
            'entrantNumbers' => $this->auth->isAuthorized($req, $id) ? $rs->getEntrantPhoneNumbers($id) : null,
            'entrantCount' => $rs->getEntrantCount($id)
        ]);
    }
}
