<?php
declare(strict_types=1);

namespace App\Controller;

use App\Base\Metadata;
use App\Base\Zipper;
use Cyndaron\BinaryHandler\BinaryReader;
use Exception;
use GdImage;
use RCTPHP\OpenRCT2\Object\SceneryGroupObject;
use RCTPHP\OpenRCT2\Object\SceneryGroupProperties;
use RCTPHP\RCT2\Object\DATHeader;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use ZipArchive;
use function array_key_exists;
use function array_keys;
use function array_map;
use function asort;
use function dechex;
use function explode;
use function imagecreatefrompng;
use function imagepng;
use function imagesx;
use function imagesy;
use function json_decode;
use function str_pad;
use function strtolower;
use function tempnam;
use const STR_PAD_LEFT;

final class SceneryGroup extends AbstractController
{
    const TAB_PREVIEW_UNSELECTED = __DIR__ . '/../../assets/0.png';
    const TAB_PREVIEW_SELECTED = __DIR__ . '/../../assets/1.png';

    #[Route('/scenery-group', methods: ['GET', 'HEAD'])]
    public function showForm(): Response
    {
        $extraLanguages = Metadata::EXTRA_LANGUAGES;
        asort($extraLanguages);
        return $this->render('scenery-group.html.twig', [
            'title' => 'Scenery Group Creator',
            'bodyClass' => 'scenery-group',
            'extraLanguages' => $extraLanguages,
        ]);
    }

    #[Route('/scenery-group', methods: ['POST'])]
    public function process(Request $request): Response
    {
        try {
            return $this->buildObject($request);
        } catch (Exception $ex) {
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
        $fullIdentifier = strtolower("{$userIdentifier}.scenery_group.{$objectIdentifier}");
        $creators = explode(',', $post->get('creators_names'));
        $creators = array_map('trim', $creators);
        $nameEnglish = $post->get('name_en-GB');
        $version = $post->get('version') ?: '1.0';
        $priority = $post->get('priority') ?: 40;

        $identifiers = $post->all('identifiers');
        if (count($identifiers) === 0)
        {
            throw new Exception('Could not decode list of objects to include!');
        }

        $entriesList = [];
        foreach ($identifiers as $identifier)
        {
            $entriesList[] = $identifier;
        }

        $previewImages = $this->addPreviewImages($request);
        $imageTable = [];
        $filemap = [];
        foreach ($previewImages as $i => $tempFilename)
        {
            $filenameInZip = "images/$i.png";
            $imageTable[] = ['path' => $filenameInZip];
            $filemap[$tempFilename] = $filenameInZip;
        }

        $nameTable = [
            'en-GB' => $nameEnglish,
        ];
        foreach (array_keys(Metadata::EXTRA_LANGUAGES) as $code)
        {
            $nameTranslated = $post->get('name_' . $code);
            if (!empty($nameTranslated) && $nameTranslated !== $nameEnglish)
            {
                $nameTable[$code] = $nameTranslated;
            }
        }

        $object = new SceneryGroupObject();
        $object->id = $fullIdentifier;
        $object->authors = $creators;
        $object->version = $version;
        $object->strings = [
            'name' => $nameTable,
        ];
        $object->properties = new SceneryGroupProperties(
            entries: $entriesList,
            priority: $priority,
        );
        $object->images = $imageTable;

        $zipper = new Zipper($object, $filemap);
        return $zipper->getResponse();
    }

    #[Route('/get-identifier', methods: ['POST'])]
    public function getIdentifier(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $object */
        $object = $request->files->get('object');
        if ($object === null)
        {
            return new JsonResponse(['error' => 'No file uploaded!'], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower($object->getClientOriginalExtension());
        if ($extension === 'dat')
        {
            $reader = BinaryReader::fromFile($object->getPathname());
            $header = DATHeader::fromReader($reader);
            $flags = str_pad(strtoupper(dechex($header->flags)), 8, '0', STR_PAD_LEFT);
            $identifier = "\$DAT:{$flags}|{$header->name}";
            return new JsonResponse(['type' => 'dat', 'identifier' => $identifier]);
        }
        if ($extension === 'parkobj')
        {
            $zip = new ZipArchive;
            if ($zip->open($object->getPathname()) !== true)
            {
                return new JsonResponse(['error' => 'Could not open ZIP!'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $json = $zip->getFromName('object.json');
            return $this->getFromJson($json);
        }
        if ($extension === 'json')
        {
            return $this->getFromJson($object->getContent());
        }

        return new JsonResponse(['error' => 'Unknown file type'], Response::HTTP_BAD_REQUEST);
    }

    private function getFromJson(string $json): JsonResponse
    {
        $structure = json_decode($json, true);
        if ($structure === null || !array_key_exists('id', $structure))
        {
            return new JsonResponse(['error' => 'Could not decode object JSON!'], Response::HTTP_BAD_REQUEST);
        }

        $identifier = $structure['id'];
        return new JsonResponse(['type' => 'json', 'identifier' => $identifier]);
    }

    /**
     * @param Request $request
     * @return string[] List of temporary file names
     */
    private function addPreviewImages(Request $request): array
    {
        /** @var UploadedFile|null $previewImage */
        $previewImage = $request->files->get('preview_image');
        if ($previewImage === null)
            return [];

        /** @var GdImage|false $image */
        $image = @imagecreatefrompng($previewImage->getPathname());
        if ($image === false)
            throw new RuntimeException('Not a valid PNG!');

        if (imagesx($image) !== 29 || imagesy($image) !== 25)
            throw new RuntimeException('Image size is incorrect, please upload a PNG file with 29 × 25 pixels!');

        $imageUnselected = @imagecreatefrompng(self::TAB_PREVIEW_UNSELECTED);
        $imageSelected  = @imagecreatefrompng(self::TAB_PREVIEW_SELECTED);

        imagecopy($imageUnselected, $image, 1, 2, 0, 0, 29, 24);
        imagecopy($imageSelected, $image, 1, 2, 0, 0, 29, 25);

        $ret = [];
        foreach ([$imageUnselected, $imageSelected] as $image)
        {
            $filename = tempnam('/tmp', 'tab-image');
            imagepng($image, $filename);
            $ret[] = $filename;
        }

        return $ret;
    }
}
