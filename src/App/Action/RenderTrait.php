<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

trait RenderTrait
{
    /** @var TemplateRendererInterface */
    protected $view;

    public function render(ResponseInterface $res, $template, $params = [])
    {
        $body = $res->getBody();
        $body->write($this->view->render($template, $params));
        return $res;
    }

    public function renderNotFound()
    {
        return new HtmlResponse($this->view->render('notFound.php'), 404);
    }
}
