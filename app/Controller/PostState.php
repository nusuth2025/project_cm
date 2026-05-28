<?php
declare(strict_types=1);

namespace App\Controller;

enum PostState: string
{
    case NotSet  = 'not_set';
    case Valid   = 'valid';
    case Invalid = 'invalid';
    case Problem = 'problem'; // Wort in der Auswahl nicht gefunden
}
