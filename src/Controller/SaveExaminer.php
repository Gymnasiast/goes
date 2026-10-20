<?php
declare(strict_types=1);

namespace App\Controller;

use RCTPHP\RCT1\Research\Entry\RideEntry as RCT1RideEntry;
use RCTPHP\RCT1\Research\Entry\RideImprovementEntry as RCT1RideImprovementEntry;
use RCTPHP\RCT1\Research\Entry\SceneryEntry as RCT1SceneryEntry;
use RCTPHP\RCT1\Research\Entry\VehicleEntry as RCT1VehicleEntry;
use RCTPHP\RCT1\S4\S4;
use RCTPHP\RCT12\Research\ResearchItem;
use RCTPHP\RCT2\Research\Entry\SceneryEntry as RCT2SceneryEntry;
use RCTPHP\RCT2\Research\Entry\VehicleEntry as RCT2VehicleEntry;
use RCTPHP\RCT2\S6\Chunks\AvailableItemsChunk;
use RCTPHP\RCT2\S6\S6;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Cyndaron\BinaryHandler\BinaryReader;


final class SaveExaminer extends AbstractController
{
    #[Route('/save-examiner', methods: ['GET', 'HEAD'])]
    public function showForm(): Response
    {
        return $this->render('save-examiner.html.twig', [
            'title' => 'Save Examiner',
            'bodyClass' => 'save-examiner',
            'error' => null,
            'result' => false,
        ]);
    }

    private function rct1ResearchItemToText(ResearchItem $item): string
    {
        if ($item instanceof RCT1RideEntry)
        {
            return "Ride entry: ride type " . $item->type->name . "\n";
        }
        elseif ($item instanceof RCT1VehicleEntry)
        {
            return "Vehicle entry: ride type " . $item->rideType->name . ", vehicle type {$item->type->name}\n";
        }
        elseif ($item instanceof RCT1SceneryEntry)
        {
            return "Scenery entry: item {$item->type->name}\n";
        }
        elseif ($item instanceof RCT1RideImprovementEntry)
        {
            return "Ride improvement: type {$item->type->name}, ride type {$item->rideType->name}\n";
        }
        else
        {
            return 'Unknown entry!';
        }
    }

    private function rct2ResearchItemToText(AvailableItemsChunk $availableItemsChunk, ResearchItem $item): string
    {
        if ($item instanceof RCT2VehicleEntry)
        {
            $name = '';
            if (array_key_exists($item->index, $availableItemsChunk->vehicles))
            {
                $name = $availableItemsChunk->vehicles[$item->index]->name;
            }

            return "Vehicle entry: ride type " . $item->rideType->name . ", index {$item->index}, DAT name {$name}\n";
        }
        elseif ($item instanceof RCT2SceneryEntry)
        {
            $name = '';
            if (array_key_exists($item->index, $availableItemsChunk->sceneryGroups))
            {
                $name = $availableItemsChunk->sceneryGroups[$item->index]->name;
            }

            return "Scenery entry: index {$item->index}, DAT name {$name}\n";
        }
        else
        {
            return 'Unknown entry!';
        }
    }

    #[Route('/save-examiner', methods: ['POST'])]
    public function result(Request $request): Response
    {
        $file = $request->files->get('save');

        if (!$file instanceof UploadedFile)
        {
            return $this->render('save-examiner.html.twig', [
                'title' => 'Save Examiner',
                'bodyClass' => 'save-examiner',
                'error' => 'File upload did not work!',
                'result' => false,
            ]);
        }

        $uninventedItems = [];
        $inventedItems = [];
        $randomPairs = [];

        $originalExtension = strtolower($file->getClientOriginalExtension());
        $reader = BinaryReader::fromFile($file->getPathname());
        if ($originalExtension === 'sv4' || $originalExtension === 'sc4')
        {
            $s4 = S4::fromReader($reader);
            foreach ($s4->researchLists->uninventedItems as $item)
            {
                $uninventedItems[] = $this->rct1ResearchItemToText($item);
            }
            foreach ($s4->researchLists->inventedItems as $item)
            {
                $inventedItems[] = $this->rct1ResearchItemToText($item);
            }
            foreach ($s4->researchLists->randomItems as $pair)
            {
                $item1 = $this->rct1ResearchItemToText($pair->item1);
                $item2 = $this->rct1ResearchItemToText($pair->item2);

                $randomPairs[] = "50% chance of swapping ‘{$item1}’ and ‘{$item2}’";
            }

        }
        elseif ($originalExtension === 'sv6' || $originalExtension === 'sc6' || $originalExtension === 'sv7')
        {
            // TODO: make RCT-PHP autodetect scenario status
            $isScenario = $originalExtension === 'sc6';
            $s6 = S6::createFromReader($reader, $isScenario);
            foreach ($s6->researchLists->uninventedItems as $item)
            {
                $uninventedItems[] = $this->rct2ResearchItemToText($s6->availableItemsChunk, $item);
            }
            foreach ($s6->researchLists->inventedItems as $item)
            {
                $inventedItems[] = $this->rct2ResearchItemToText($s6->availableItemsChunk, $item);
            }
            foreach ($s6->researchLists->randomItems as $pair)
            {
                $item1 = $this->rct2ResearchItemToText($s6->availableItemsChunk, $pair->item1);
                $item2 = $this->rct2ResearchItemToText($s6->availableItemsChunk, $pair->item2);

                $randomPairs[] = "50% chance of swapping ‘{$item1}’ and ‘{$item2}’";
            }
        }
        elseif ($originalExtension === 'park')
        {
            return $this->render('save-examiner.html.twig', [
                'title' => 'Save Examiner',
                'bodyClass' => 'save-examiner',
                'error' => '.park files are not yet supported!',
                'result' => false,
            ]);
        }
        else
        {
            return $this->render('save-examiner.html.twig', [
                'title' => 'Save Examiner',
                'bodyClass' => 'save-examiner',
                'error' => 'File type not recognized!',
                'result' => false,
            ]);
        }

 
        return $this->render('save-examiner.html.twig', [
            'title' => 'Save Examiner',
            'bodyClass' => 'save-examiner',
            'error' => null,
            'result' => true,
            'inventedItems' => $inventedItems,
            'uninventedItems' => $uninventedItems,
            'randomPairs' => $randomPairs,
        ]);
    }
}
