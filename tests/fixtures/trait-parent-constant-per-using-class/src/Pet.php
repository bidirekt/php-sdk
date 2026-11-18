<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

trait HasStatus
{
    #[BidirektField(description: parent::NOTE)]
    public Status $status;
}

class BaseA { public const NOTE = 'from A'; }
class BaseB { public const NOTE = 'from B'; }

#[BidirektSchema]
final class PetA extends BaseA
{
    use HasStatus;
}

#[BidirektSchema]
final class PetB extends BaseB
{
    use HasStatus;
}

final class Controller
{
    #[BidirektProvider(method: 'GET', path: '/status', responses: [200 => Status::class])]
    public function status(): Status { return Status::On; }
}
