<?php

namespace App\Controller;

use App\Entity\Image;
use App\Entity\Category;
use App\Form\ImageFormType;
use App\Form\CategoryFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;


class AdminController extends AbstractController
{
    #[Route('/admin/images', name: 'app_images')]
    public function images(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $image = new Image();
        $form = $this->createForm(ImageFormType::class, $image);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                // El Slugger hace que el nombre del archivo sea seguro en cuanto a 
                // caracteres especiales como espacios o acentos
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                // El servidor almacena el archivo en un directorio temporal y
                // debemos moverlo a su ubicación definitiva, dentro de una ruta que
                // hemos definido en los parámetros de configuración (services.yaml)
                // y que debe existir previamente dentro de la carpeta `public` proyecto
                try {

                    // Primero lo movemos al directorio de imágenes
                    $file->move(
                        $this->getParameter('images_directory'), $newFilename
                    );
                    $filesystem = new Filesystem();
                    // Y ahora lo duplicamos en el directorio de portfolio
                    $filesystem->copy(
                        $this->getParameter('images_directory') . '/'. $newFilename, 
                        $this->getParameter('portfolio_directory') . '/'.  $newFilename, true);

                } catch (FileException $e) {
                    return new Response("Error al subir el archivo: " . $e->getMessage());
                }

                // asignamos el nombre del archivo, que se llama `file`, a la entidad Image
                $image->setFile($newFilename);
            }
            $image = $form->getData();   
            $entityManager = $doctrine->getManager();    
            $entityManager->persist($image);
            $entityManager->flush();
        }
        return $this->render('admin/images.html.twig', array(
                        'form' => $form->createView()
                    ));
    }



    #[Route('/admin/categories', name: 'app_categories')]
    public function categories(ManagerRegistry $doctrine, Request $request): Response
    {
        $repositorio = $doctrine->getRepository(Category::class);

        $categories = $repositorio->findAll();

        $category = new Category();
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $category = $form->getData();    
            $entityManager = $doctrine->getManager();    
            $entityManager->persist($category);
            $entityManager->flush();
        }
        return $this->render('admin/categories.html.twig', array(
            'form' => $form->createView(),
            'categories' => $categories   
        ));
    }

}
