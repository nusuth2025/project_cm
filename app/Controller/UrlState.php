<?php
declare(strict_types=1);

namespace App\Controller;

enum UrlState: string
{
    case NotSet     = 'not_set';
    case Valid      = 'valid';
    case NotWorking = 'not_working';
}
