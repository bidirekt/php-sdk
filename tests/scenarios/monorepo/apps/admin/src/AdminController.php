<?php

declare(strict_types=1);

namespace Acme\Admin;

use Bidirekt\PhpSdk\BidirektProvider;

final class AdminController
{
    /**
     * @return list<AdminUser>
     */
    #[BidirektProvider(method: 'GET', path: '/admin/users', responses: [200 => [AdminUser::class]])]
    public function users(): array
    {
        return [];
    }
}
