<?php

namespace App\Services;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadFileService
{
    private $targetDirectory;
    private EntityManagerInterface $entityManager;

    public function __construct(string $targetDirectory, EntityManagerInterface $entityManager)
    {
        $this->targetDirectory = $targetDirectory;
        $this->entityManager = $entityManager;
    }

    public function upload(UploadedFile $file)
    {
        $newFilename = uniqid().'.'.$file->guessExtension();
        $file->move($this->targetDirectory, $newFilename);
        return $newFilename;
    }

    public function removeTestimonialImage($testimonial, $image) {
        if (!$image) {
            return;
        }

        $file = $this->targetDirectory . '/' . $image->getFilename();
        if (file_exists($file)) {
            unlink($file);
        }

        $this->entityManager->remove($testimonial);
    }
}