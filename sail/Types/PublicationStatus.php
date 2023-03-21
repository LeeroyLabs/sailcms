<?php

namespace SailCMS\Types;

enum PublicationStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case EXPIRED = 'expired';
}