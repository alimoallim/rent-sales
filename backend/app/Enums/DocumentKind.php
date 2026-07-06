<?php

namespace App\Enums;

enum DocumentKind: string
{
    case Photo = 'photo';
    case Signature = 'signature';
    case IdDocument = 'id_document';
}
