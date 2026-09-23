<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Draft = 'DRAFT';
    case Review = 'REVIEW';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';
}
