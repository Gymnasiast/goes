<?php
declare(strict_types=1);

namespace App;

use RCTPHP\Object\OpenRCT2\BaseObject;
use RCTPHP\Object\OpenRCT2\ObjectType;

final class SceneryGroupObject extends BaseObject
{
    public function __construct()
    {
        $this->objectType = ObjectType::SCENERY_GROUP;
    }

    public array $properties = [];
    public array $images = [];
}
