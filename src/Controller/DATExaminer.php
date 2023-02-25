<?php
declare(strict_types=1);

namespace App\Controller;

use App\Base\Metadata;
use RCTPHP\Object\DatHeader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use function asort;
use function dechex;
use function str_pad;
use function strtoupper;
use const STR_PAD_LEFT;

final class DATExaminer extends AbstractController
{
    #[Route('/dat-examiner', methods: ['GET', 'HEAD'])]
    public function showForm(): Response
    {
        return $this->render('dat-examiner.html.twig', [
            'title' => 'Scenery Group Creator',
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
                'title' => 'Scenery Group Creator',
                'error' => 'File upload did not work!',
                'result' => false,
            ]);
        }

        $header = new DatHeader($file->getPathname());
        $flags = str_pad(strtoupper(dechex($header->flags)), 8, '0', STR_PAD_LEFT);
        $checksum = str_pad(strtoupper(dechex($header->checksum)), 8, '0', STR_PAD_LEFT);
        $originalId = "{$flags}|{$header->name}|{$checksum}";
        $sceneryGroupEntry = "\$DAT:{$flags}|{$header->name}";

        return $this->render('dat-examiner.html.twig', [
            'title' => 'Scenery Group Creator',
            'error' => null,
            'result' => true,
            'name' => $header->name,
            'flags' => $flags,
            'checksum' => $checksum,
            'originalId' => $originalId,
            'sceneryGroupEntry' => $sceneryGroupEntry,
        ]);
    }
}
