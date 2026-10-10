<?php

namespace App\Support;

final class DesignCatalog
{
    /** @return list<string> */
    public static function sizes(): array
    {
        return ['4″ hoop', '6″ hoop', '8″ hoop', 'Saree border', 'Motif'];
    }

    /** @return list<string> */
    public static function colors(): array
    {
        return ['Maroon', 'Gold', 'Ivory', 'Forest', 'Navy', 'Blush', 'Black', 'Copper'];
    }
}
