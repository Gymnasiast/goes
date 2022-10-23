<?php
declare(strict_types=1);

namespace App\OpenRCT2;

// TODO: Move all these files to the RCT-PHP libary!
abstract class BaseObject
{
    public string $id = '';
    /** @var string[] */
    public array $authors = [];
    public string $version = '1.0';
    public string $sourceGame = 'custom';
    public ObjectType $objectType;
    public array $strings = [];
}
