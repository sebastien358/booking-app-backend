<?php

namespace App\Services;

use App\Entity\Picture;
use Doctrine\ORM\EntityManagerInterface;

class TestimonialService
{
    private EntityManagerInterface $entityManager;
    private UploadFileService $uploadFileService;

    public function __construct(EntityManagerInterface $entityManager, UploadFileService $uploadFileService)
    {
        $this->entityManager = $entityManager;
        $this->uploadFileService = $uploadFileService;
    }

    public function testimonialDisplay($testimonials, $request, $serializer)
    {

        $elems = ['groups' => ['testimonials', 'picture'], 'circular_reference_handler' => function ($object) {
            return $object->getId();
        }];

        $urlImage = $request->getSchemeAndHttpHost() . '/images/';

        if (!is_array($testimonials)) {
            $data = $serializer->normalize($testimonials, 'json', $elems);

            if (!empty($data['picture']['filename'])) {
                $data['picture']['filename'] = $urlImage . $data['picture']['filename'];
            }

            return $data;
        }

        $dataList = $serializer->normalize($testimonials, 'json', $elems);

        foreach ($dataList as &$item) {
            if (!empty($item['picture']['filename'])) {
                $item['picture']['filename'] = $urlImage . $item['picture']['filename'];
            }
        }

        return $dataList;
    }

    public function testimonialImage($image, $testimonial) {
        if (!$image) {
            return null;
        };

        if ($image->getSize() > 5 * 1024 * 1024) {
            throw new \Exception('La taille de l\'image est trop grande'. $image->getClientOriginalName());
        }

        $picture = new Picture();
        $newFilename = $this->uploadFileService->upload($image);

        $picture->setFilename($newFilename);
        $picture->setTestimonial($testimonial);
        $testimonial->setPicture($picture);

        $this->entityManager->persist($picture);
    }
}