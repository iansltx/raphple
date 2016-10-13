<?php

namespace App\Service;

interface SMS
{
    public function send($to, $text);
}
