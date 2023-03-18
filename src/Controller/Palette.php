<?php
declare(strict_types=1);

namespace App\Controller;

use App\Base\Metadata;
use App\Base\Zipper;
use RCTPHP\ExternalTools\RCT2PaletteMakerFile;
use RCTPHP\OpenRCT2\Object\MusicObject;
use RCTPHP\OpenRCT2\Object\ObjectSerializer;
use RCTPHP\OpenRCT2\Object\WaterPaletteGroup;
use RCTPHP\OpenRCT2\Object\WaterProperties;
use RCTPHP\OpenRCT2\Object\WaterPropertiesPalettes;
use RCTPHP\RCT2\Object\DATDetector;
use RCTPHP\RCT2\Object\WaterObject;
use RCTPHP\Util\RGB;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use TXweb\BinaryHandler\BinaryReader;
use ZipArchive;
use function array_key_exists;
use function array_keys;
use function array_map;
use function asort;
use function explode;
use function file_get_contents;
use function hexdec;
use function is_array;
use function json_decode;
use function json_encode;
use function stream_get_contents;
use function strtolower;
use function substr;

final class Palette extends AbstractController
{
    #[Route('/palette', methods: ['GET', 'HEAD'])]
    public function showForm(): Response
    {
        $extraLanguages = Metadata::EXTRA_LANGUAGES;
        asort($extraLanguages);
        return $this->render('palette.html.twig', [
            'title' => 'Palette Creator',
            'extraLanguages' => $extraLanguages,
        ]);
    }

    #[Route('/palette/get-default', methods: ['GET', 'HEAD'])]
    public function getDefaultPalette(): JsonResponse
    {
        $data = file_get_contents(__DIR__ . '/../../assets/wtrcyan.json');
        return new JsonResponse($data, json: true);
    }

    #[Route('/palette/extract', methods: ['POST'])]
    public function extractPalette(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('object');
        if ($uploadedFile === null)
        {
            return new JsonResponse(['error' => 'No file uploaded!'], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        switch ($extension)
        {
            case 'bmp':
                try
                {
                    $palFile = new RCT2PaletteMakerFile($uploadedFile->getPathname());
                    $converted = $palFile->toOpenRCT2Object();
                    $serializer = new ObjectSerializer($converted);
                    return new JsonResponse($serializer->serializeToJson(), json: true);
                }
                catch (Throwable)
                {
                    return new JsonResponse(['error' => 'Could not open BMP file!'], Response::HTTP_BAD_REQUEST);
                }
            case 'parkobj':
                $zip = new ZipArchive();
                $open = $zip->open($uploadedFile->getPathname());
                if ($open !== true)
                {
                    return new JsonResponse(['error' => 'Could not extract files from PARKOBJ! The file might be damaged.'], Response::HTTP_BAD_REQUEST);
                }

                $stream = $zip->getStream('object.json');
                if ($stream === false)
                {
                    return new JsonResponse(['error' => 'Could not load object metadata. The object may be damaged!'], Response::HTTP_BAD_REQUEST);
                }

                $data = stream_get_contents($stream);
                return $this->checkJson($data);
            case 'dat':
                $reader = BinaryReader::fromFile($uploadedFile->getPathname());
                $detector = new DATDetector($reader);
                $object = $detector->getObject();
                if (!$object instanceof WaterObject)
                {
                    return new JsonResponse(['error' => 'The provided object is not a palette!'], Response::HTTP_BAD_REQUEST);
                }
                $converted = $object->toOpenRCT2Object();
                $serializer = new ObjectSerializer($converted);
                return new JsonResponse($serializer->serializeToJson(), json: true);
            case 'json':
                $json = file_get_contents($uploadedFile->getPathname());
                return $this->checkJson($json);
        }

        return new JsonResponse(['error' => 'Extension not supported!'], Response::HTTP_BAD_REQUEST);
    }

    private function checkJson(string $json): JsonResponse
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !array_key_exists('objectType', $decoded))
        {
            return new JsonResponse(['error' => 'Could not load object metadata. The object may be damaged!'], Response::HTTP_BAD_REQUEST);
        }

        if ($decoded['objectType'] !== 'water')
        {
            return new JsonResponse(['error' => 'The provided object is not a palette!'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($json, json: true);
    }

    #[Route('/palette', methods: ['POST'])]
    public function process(Request $request): Response
    {
        try {
            return $this->buildObject($request);
        } catch (\Exception $ex) {
            return new JsonResponse([
                'error' => $ex->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function buildObject(Request $request): Response
    {
        $post = $request->request;
        $userIdentifier = $post->get('user_identifier');
        $objectIdentifier = $post->get('object_identifier');
        $fullIdentifier = strtolower("{$userIdentifier}.palette.{$objectIdentifier}");
        $creators = explode(',', $post->get('creators_names'));
        $creators = array_map('trim', $creators);
        $styleDescriptionEnglish = $post->get('description');
        $version = $post->get('version') ?: '1.0';
        $allowDucks = $post->getBoolean('allow_ducks');

        $nameTable = [
            'en-GB' => $styleDescriptionEnglish,
        ];
        foreach (array_keys(Metadata::EXTRA_LANGUAGES) as $code)
        {
            $styleDescriptionTranslated = $post->get('description_' . $code);
            if (!empty($styleDescriptionTranslated) && $styleDescriptionTranslated !== $styleDescriptionEnglish)
            {
                $nameTable[$code] = $styleDescriptionTranslated;
            }
        }

        $rgbGeneral = [];
        for ($index = 0; $index < 236; $index++)
        {
            $hex = $post->get('palette_colour_' . $index);
            $rgbGeneral[] = RGB::fromHex($hex);
        }
        $rgbWaves0 = [];
        for (;$index < 251; $index++)
        {
            $hex = $post->get('palette_colour_' . $index);
            $rgbWaves0[] = RGB::fromHex($hex);
        }
        $rgbWaves1 = [];
        for (; $index < 266; $index++)
        {
            $hex = $post->get('palette_colour_' . $index);
            $rgbWaves1[] = RGB::fromHex($hex);
        }
        $rgbWaves2 = [];
        for (; $index < 281; $index++)
        {
            $hex = $post->get('palette_colour_' . $index);
            $rgbWaves2[] = RGB::fromHex($hex);
        }
        $rgbSparkles0 = [];
        for (; $index < 296; $index++)
        {
            $hex = $post->get('palette_colour_' . $index);
            $rgbSparkles0[] = RGB::fromHex($hex);
        }
        $rgbSparkles1 = [];
        for (; $index < 311; $index++)
        {
            $hex = $post->get('palette_colour_' . $index);
            $rgbSparkles1[] = RGB::fromHex($hex);
        }
        $rgbSparkles2 = [];
        for (; $index < 326; $index++)
        {
            $hex = $post->get('palette_colour_' . $index);
            $rgbSparkles2[] = RGB::fromHex($hex);
        }

        $object = new \RCTPHP\OpenRCT2\Object\WaterObject();
        $object->id = $fullIdentifier;
        $object->authors = $creators;
        $object->version = $version;
        $object->strings = [
            'name' => $nameTable,
        ];
        $object->properties = new WaterProperties(
            $allowDucks,
            new WaterPropertiesPalettes([
                    WaterPaletteGroup::GENERAL->value => new \RCTPHP\Sawyer\ImageTable\Palette(10, 236, $rgbGeneral),
                    WaterPaletteGroup::WAVES_0->value => new \RCTPHP\Sawyer\ImageTable\Palette(16, 15, $rgbWaves0),
                    WaterPaletteGroup::WAVES_1->value => new \RCTPHP\Sawyer\ImageTable\Palette(32, 15, $rgbWaves1),
                    WaterPaletteGroup::WAVES_2->value => new \RCTPHP\Sawyer\ImageTable\Palette(48, 15, $rgbWaves2),
                    WaterPaletteGroup::SPARKLES_0->value => new \RCTPHP\Sawyer\ImageTable\Palette(80, 15, $rgbSparkles0),
                    WaterPaletteGroup::SPARKLES_1->value => new \RCTPHP\Sawyer\ImageTable\Palette(96, 15, $rgbSparkles1),
                    WaterPaletteGroup::SPARKLES_2->value => new \RCTPHP\Sawyer\ImageTable\Palette(112, 15, $rgbSparkles2),
                ]

            )
        );

        $zipper = new Zipper($object);
        return $zipper->getResponse();
    }
}
