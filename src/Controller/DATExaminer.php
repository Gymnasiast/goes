<?php
declare(strict_types=1);

namespace App\Controller;

use RCTPHP\RCT2\Object\DATDetector;
use RCTPHP\RCT2\Object\DATHeader;
use RCTPHP\RCT2\Object\ObjectType;
use RCTPHP\RCT2\Object\SceneryGroupObject;
use RCTPHP\Sawyer\Object\StringTable;
use RCTPHP\Sawyer\Object\StringTableOwner;
use RCTPHP\Sawyer\Object\WithPreview;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Cyndaron\BinaryHandler\BinaryReader;

final class DATExaminer extends AbstractController
{
    #[Route('/dat-examiner', methods: ['GET', 'HEAD'])]
    public function showForm(): Response
    {
        return $this->render('dat-examiner.html.twig', [
            'title' => 'DAT Examiner',
            'bodyClass' => 'dat-examiner',
            'error' => null,
            'result' => false,
        ]);
    }

    #[Route('/dat-examiner', methods: ['POST'])]
    public function result(Request $request): Response
    {
        $file = $request->files->get('dat');

        if ($file === null)
        {
            return $this->render('dat-examiner.html.twig', [
                'title' => 'DAT Examiner',
                'bodyClass' => 'dat-examiner',
                'error' => 'File upload did not work!',
                'result' => false,
            ]);
        }

        $reader = BinaryReader::fromFile($file->getPathname());
        $detector = DATDetector::fromReader($reader);
        $header = $detector->getHeader();
        $object = $detector->getObject();

        $typeMap = [
            "Ride",
            "Small scenery",
            "Large scenery",
            "Wall",
            "Banner",
            "Footpath",
            "Footpath item",
            "Scenery group",
            "Park entrance",
            "Water",
            "Scenario Text",
        ];
        $shortDescription = 'N/A';
        $stringTables = [];
        if ($object instanceof StringTableOwner)
        {
            $stringTables = $object->getStringTables();
            /** @var StringTable $stringTable */
            $stringTable = reset($stringTables);
            foreach ($stringTable->strings as $string)
            {
                $normalized = trim($string->toUtf8());
                if ($normalized !== '')
                {
                    $shortDescription = $normalized;
                    break;
                }
            }
        }

        $image = null;
        if ($object instanceof WithPreview)
        {
            ob_start();
            $image = $object->getPreview();
            imagepalettetotruecolor($image);
            imagewebp($image);
            $image = base64_encode(ob_get_clean());
        }
        $referencedObjects = [];
        if ($object instanceof SceneryGroupObject)
        {
            $referencedObjects = $object->objects;
        }

        return $this->render('dat-examiner.html.twig', [
            'title' => 'DAT Examiner',
            'bodyClass' => 'dat-examiner',
            'error' => null,
            'result' => true,
            'name' => $header->name,
            'flags' => $header->getFlagsFormatted(),
            'checksum' => $header->getChecksumFormatted(),
            'originalId' => $header->getAsOriginalId(),
            'sceneryGroupEntry' => $header->getAsSceneryGroupListEntry(),
            'objectType' => $typeMap[$header->getType()->value] ?? 'Unknown',
            'shortDescription' => $shortDescription,
            'stringTables' => $stringTables,
            'image' => $image,
            'referencedObjects' => $referencedObjects,
        ]);
    }
}
