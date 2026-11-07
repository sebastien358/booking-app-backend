<?php

namespace App\Services;

use App\Entity\Picture;
use App\Entity\Staff;
use Doctrine\ORM\EntityManagerInterface;

class StaffService
{
    private EntityManagerInterface $entityManager;
    private UploadFileService $uploadFileService;

    public function __construct(EntityManagerInterface $entityManager, UploadFileService $uploadFileService)
    {
        $this->uploadFileService = $uploadFileService;
        $this->entityManager = $entityManager;
    }

    public function staffDisplay($staffs, $request, $serializer)
    {
        $elems = ['groups' => ['staffs', 'picture'], 'circular_reference_handler' => function ($object) {
            return $object->getId();
        }];

        $urlImage = $request->getSchemeAndHttpHost() . '/images/';

        if (!is_array($staffs)) {
            $dataStaff = $serializer->normalize($staffs, 'json', $elems);
            if (isset($dataStaff['picture']['filename'])) {
                $dataStaff['picture']['filename'] = $urlImage . $dataStaff['picture']['filename'];
            }
            return $dataStaff;
        }

        $dataStaffs = $serializer->normalize($staffs, 'json', $elems);
        foreach ($dataStaffs as &$staff) {
            if (isset($staff['picture'])) {
                $staff['picture']['filename'] = $urlImage . $staff['picture']['filename'];
            }
        }
        unset($staff);
        return $dataStaffs;
    }

    public function staffImage(Staff $staff, $image)
    {
        if (!$image) return;

        if ($image->getSize() > 5 * 1024 * 1024) {
            throw new \Exception('La taille de l\'image est trop grande', $image->getClientOriginalName());
        }

        $picture = new Picture();
        $newFilename = $this->uploadFileService->upload($image);
        $picture->setFilename($newFilename);
        $picture->setStaff($staff);
        $this->entityManager->persist($picture);
    }
}