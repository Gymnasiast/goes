<?php
declare(strict_types=1);

namespace App\Controller;

use App\OpenRCT2\MusicObject;
use App\OpenRCT2\ObjectSerializer;
use GdImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use ZipArchive;
use function file_get_contents;
use function imagecreatefrompng;
use function tempnam;
use function unlink;

final class Music extends AbstractController
{
    private const MAX_MUSIC_TRACKS = 3;

    private const FILE_FORMAT = [
        'audio/ogg' => 'ogg',
    ];

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
        $fullIdentifier = strtolower("{$userIdentifier}.music.{$objectIdentifier}");
        $creators = explode(',', $post->get('creators_names'));
        $creators = array_map('trim', $creators);
        $styleDescription = $post->get('style_description');
        $previewImage = $this->getPreviewImage($request);

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
            $trackName = trim($post->get("track_{$i}_name", ''));
            $composer = trim($post->get("track_{$i}_composer", ''));
            $entry = [
                'source' => $newFilename,
            ];
            if ($trackName)
                $entry['name'] = $trackName;
            if ($composer)
                $entry['composer'] = $composer;

            $tracks[] = $entry;
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
        if ($previewImage !== null)
        {
            $object->images = [
                ['path' => 'images/preview.png'],
            ];
            $filemap[$previewImage->getPathname()] = 'images/preview.png';
        }

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

    private function getPreviewImage(Request $request): UploadedFile|null
    {
        /** @var UploadedFile|null $previewImage */
        $previewImage = $request->files->get('preview_image');
        if ($previewImage === null)
            return null;

        /** @var GdImage|false $image */
        $image = @imagecreatefrompng($previewImage->getPathname());
        if ($image === false)
            return null;

        if (imagesx($image) !== 112 || imagesy($image) !== 112)
            return null;

        return $previewImage;
    }
}
