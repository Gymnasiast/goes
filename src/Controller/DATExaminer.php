<?php
declare(strict_types=1);

namespace App\Controller;

use RCTPHP\RCT2\Object\DATHeader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use TXweb\BinaryHandler\BinaryReader;

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

        $reader = BinaryReader::fromFile($file->getPathname());
        $header = new DATHeader($reader);

        return $this->render('dat-examiner.html.twig', [
            'title' => 'Scenery Group Creator',
            'error' => null,
            'result' => true,
            'name' => $header->name,
            'flags' => $header->getFlagsFormatted(),
            'checksum' => $header->getChecksumFormatted(),
            'originalId' => $header->getAsOriginalId(),
            'sceneryGroupEntry' => $header->getAsSceneryGroupListEntry(),
        ]);
    }
}
