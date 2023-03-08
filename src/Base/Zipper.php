<?php
namespace App\Base;

use RCTPHP\OpenRCT2\Object\BaseObject;
use RCTPHP\OpenRCT2\Object\ObjectSerializer;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;
use function file_get_contents;
use function tempnam;
use function unlink;

final class Zipper
{
    public function __construct(private readonly BaseObject $object, private readonly array $fileMap = [])
    {
    }

    public function getZipContents(): string
    {
        $serializer = new ObjectSerializer($this->object);
        $json = $serializer->serializeToJson();

        $zip = new ZipArchive();
        $zipFilename = tempnam('/tmp', 'zip');
        if ($zip->open($zipFilename, ZipArchive::CREATE) !== true) {
            throw new \Exception("Cannot create zipfile!");
        }

        $zip->addFromString('object.json', $json);
        foreach ($this->fileMap as $tempname => $newName)
        {
            $zip->addFile($tempname, $newName);
        }

        $zip->close();

        $zipContents = file_get_contents($zipFilename);
        unlink($zipFilename);

        return $zipContents;
    }

    public function getResponse(): Response
    {
        $fullIdentifier = $this->object->id;
        $zipContents = $this->getZipContents();
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            "$fullIdentifier.parkobj",
        );

        return new Response($zipContents, Response::HTTP_OK, [
            'Content-Disposition' => $disposition
        ]);
    }
}
