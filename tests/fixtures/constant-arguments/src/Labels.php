<?php

namespace App;

class BaseLabels
{
    public const SHOWN = 'Shown on the card';
}

interface Described
{
    public const NOTE = self::KEPT;
    public const KEPT = 'Kept by the shelter';
}

final class Labels extends BaseLabels implements Described
{
    public const NICKNAME = 'nick_name';
    public const CARD = self::SHOWN;
}
