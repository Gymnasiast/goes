<?php
declare(strict_types=1);

namespace App\Controller;

use RuntimeException;
use RCTPHP\Object\OpenRCT2\MusicObject;
use RCTPHP\Object\OpenRCT2\ObjectSerializer;
use GdImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use ZipArchive;
use function array_key_exists;
use function array_keys;
use function array_map;
use function explode;
use function file_get_contents;
use function imagecreatefrompng;
use function strtolower;
use function tempnam;
use function trim;
use function unlink;

final class Music extends AbstractController
{
    private const MAX_MUSIC_TRACKS = 3;

    private const FILE_FORMAT = [
        'audio/ogg' => 'ogg',
    ];

    private const EXTRA_LANGUAGES = [
        'ar-EG' => 'Arabic (Egypt)',
        'ca-ES' => 'Catalan',
        'da-DK' => 'Danish',
        'de-DE' => 'German (Germany)',
        'en-US' => 'English (US)',
        'eo-ZZ' => 'Esperanto',
        'es-ES' => 'Spanish (Spain)',
        'fi-FI' => 'Finnish',
        'fr-FR' => 'French',
        'hu-HU' => 'Hungarian',
        'it-IT' => 'Italian',
        'ja-JP' => 'Japanese',
        'ko-KR' => 'Korean',
        'nb-NO' => 'Norwegian (Bokmål)',
        'nl-NL' => 'Dutch',
        'pt-BR' => 'Portuguese (Brazil)',
        'ru-RU' => 'Russian',
        'sv-SE' => 'Swedish',
        'tr-TR' => 'Turkish',
        'zh-CN' => 'Simplified Chinese',
        'zh-TW' => 'Traditional Chinese',
    ];

    #[Route('/music', methods: ['GET', 'HEAD'])]
    public function showForm(): Response
    {
        $extraLanguages = self::EXTRA_LANGUAGES;
        asort($extraLanguages);
        return $this->render('music.html.twig', [
            'maxTracks' => self::MAX_MUSIC_TRACKS,
            'extraLanguages' => $extraLanguages,
        ]);
    }

    #[Route('/music', methods: ['POST'])]
    public function process(Request $request): Response
    {
        try {
            return $this->buildObject($request);
        } catch (\Exception $ex) {
            return new JsonResponse([
                'error' => $ex->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        //$response = new BinaryFileResponse($zipFilename);
    }

    private function buildObject(Request $request): Response
    {
        $post = $request->request;
        $userIdentifier = $post->get('user_identifier');
        $objectIdentifier = $post->get('object_identifier');
        $fullIdentifier = strtolower("{$userIdentifier}.music.{$objectIdentifier}");
        $creators = explode(',', $post->get('creators_names'));
        $creators = array_map('trim', $creators);
        $styleDescriptionEnglish = $post->get('style_description');
        $version = $post->get('version') ?: '1.0';
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

            $mimeType = $upload->getMimeType();
            if (!array_key_exists($mimeType, self::FILE_FORMAT))
            {
                throw new RuntimeException("Music file #{$i} has an incorrect file type: {$mimeType}. Please select an OGG file.");
            }

            $extension = self::FILE_FORMAT[$mimeType] ?? $upload->getClientOriginalExtension();
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

        $nameTable = [
            'en-GB' => $styleDescriptionEnglish,
        ];
        foreach (array_keys(self::EXTRA_LANGUAGES) as $code)
        {
            $styleDescriptionTranslated = $post->get('style_description_' . $code);
            if (!empty($styleDescriptionTranslated) && $styleDescriptionTranslated !== $styleDescriptionEnglish)
            {
                $nameTable[$code] = $styleDescriptionTranslated;
            }
        }

        $object = new MusicObject();
        $object->id = $fullIdentifier;
        $object->authors = $creators;
        $object->version = $version;
        $object->strings = [
            'name' => $nameTable,
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
            throw new RuntimeException('Not a valid PNG!');

        if (imagesx($image) !== 112 || imagesy($image) !== 112)
            throw new RuntimeException('Image size is incorrect, please upload a PNG file with 112 × 112 pixels!');

        return $previewImage;
    }
}
