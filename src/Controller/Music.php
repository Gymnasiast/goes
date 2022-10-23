<?php
declare(strict_types=1);

namespace App\Controller;

use App\OpenRCT2\MusicObject;
use App\OpenRCT2\ObjectSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use ZipArchive;
use function file_get_contents;
use function tempnam;
use function unlink;
use function var_dump;

final class Music extends AbstractController
{
    private const MAX_MUSIC_TRACKS = 3;

    #[Route('/music', methods: ['GET', 'HEAD'])]
    public function showForm(): Response
    {
        return $this->render('music.html.twig', [
            'maxTracks' => self::MAX_MUSIC_TRACKS,
        ]);
    }

    #[Route('/music', methods: ['POST'])]
    public function process(Request $request): Response
    {
        $post = $request->request;
        $userIdentifier = $post->get('user_identifier');
        $objectIdentifier = $post->get('object_identifier');
        $fullIdentifier = "{$userIdentifier}.music.{$objectIdentifier}";
        $creators = explode(',', $post->get('creators_names'));
        $creators = array_map('trim', $creators);
        $styleDescription = $post->get('style_description');

        $tracks = [];
        $filemap = [];
        $newIndex = 0;
        for ($i = 1; $i <= self::MAX_MUSIC_TRACKS; $i++)
        {
            /** @var UploadedFile|null $upload */
            $upload = $request->files->get("track_{$i}_upload");
            if ($upload === null)
                continue;

            $extension = $upload->getClientOriginalExtension();
            $newFilename = "music/{$newIndex}.{$extension}";
            $tracks[] = [
                'source' => $newFilename,
                'name' => $post->get("track_{$i}_name", ''),
                'composer' => $post->get("track_{$i}_composer", ''),
            ];
            $filemap[$upload->getPathname()] = $newFilename;

            $newIndex++;
        }

        $object = new MusicObject();
        $object->id = $fullIdentifier;
        $object->authors = $creators;
        $object->strings = [
            'name' => [
                'en-GB' => $styleDescription,
            ]
        ];
        $object->properties = [
            'tracks' => $tracks,
        ];

        $serializer = new ObjectSerializer($object);
        $json = $serializer->serializeToJson();

        $zip = new ZipArchive();
        $zipFilename = tempnam('/tmp', 'zip');
        if ($zip->open($zipFilename, ZipArchive::CREATE) !== true) {
            throw new \Exception("Cannot create zipfile!");
        }

        $zip->addFromString('object.json', $json);
        foreach ($filemap as $tempname => $newName)
        {
            $zip->addFile($tempname, $newName);
        }

        $zip->close();

        $zipContents = file_get_contents($zipFilename);
        unlink($zipFilename);

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            "$fullIdentifier.parkobj",
        );

        return new Response($zipContents, Response::HTTP_OK, [
            'Content-Disposition' => $disposition
        ]);

        //$response = new BinaryFileResponse($zipFilename);
    }

    private function getTempDir(): string
    {
        return '/tmp';
    }
}
