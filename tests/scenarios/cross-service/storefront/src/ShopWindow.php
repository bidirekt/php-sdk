<?php

declare(strict_types=1);

namespace Storefront;

use Storefront\Petstore\PetstoreClient;

final class ShopWindow
{
    public function __construct(private PetstoreClient $petstore)
    {
    }

    public function render(): string
    {
        $html = '<ul>';
        foreach ($this->petstore->pets() as $pet) {
            if ($pet->status === 'available') {
                $html .= sprintf('<li><a href="/pets/%d">%s</a></li>', $pet->id, htmlspecialchars($pet->name));
            }
        }

        return $html . '</ul>';
    }
}
