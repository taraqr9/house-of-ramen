<?php

namespace App\Enums;

enum ImageCollectionOutcomeEnum
{
    case VERIFIED;
    case NEEDS_REVIEW;
    case NOT_FOUND;
}
