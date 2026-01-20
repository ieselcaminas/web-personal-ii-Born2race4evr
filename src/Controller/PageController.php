<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Comment;
use App\Entity\Contact;
use App\Form\PostFormType;
use App\Form\CommentFormType;
use App\Form\ContactFormType;
use App\Repository\PostRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class PageController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('page/index.html.twig', []);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('page/about.html.twig', []);
    }

    #[Route('/portfolio', name: 'portfolio')]
    public function portfolio(): Response
    {
        return $this->render('page/portfolio.html.twig', []);
    }

    #[Route('/blog', name: 'blog')]
    public function blog(ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll();

        
        return $this->render('page/blog.html.twig', [
            'posts' => $posts,
        ]);
    }


    #[Route('/contact', name: 'contact')]
    public function contact(ManagerRegistry $doctrine, Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contacto = $form->getData();    
            $entityManager = $doctrine->getManager();    
            $entityManager->persist($contacto);
            $entityManager->flush();

            return $this->redirectToRoute('thankyou');
        }

        return $this->render('page/contact.html.twig', [
            'form' => $form->createView()    
        ]);
    }

    #[Route('/thankyou', name: 'thankyou')]
    public function thankyou(): Response
    {
        return $this->render('page/thankyou.html.twig', []);
    }

    #[Route('/components', name: 'components')]
    public function components(): Response
    {
        return $this->render('page/components.html.twig', []);
    }

    #[Route('/blog/new', name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Recogemos los datos básicos (Título, Contenido...)
            $post = $form->getData();   
            
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('image')->getData();

            // Si el usuario ha subido una imagen, la procesamos
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                // Usamos el slugger para limpiar el nombre del archivo (quita espacios, acentos...)
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                // Movemos el archivo a la carpeta public/images/blog
                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/images/blog',
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Aquí podrías añadir un mensaje de error si falla la subida
                }

                // Guardamos SOLO el nombre del archivo en la base de datos
                $post->setImage($newFilename);
            }
            // -----------------------------------

            $post->setSlug($slugger->slug($post->getTitle()));
            $post->setPostUser($this->getUser());
            $post->setNumLikes(0);
            $post->setNumComments(0);

            $entityManager = $doctrine->getManager();    
            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);

        }

        return $this->render('blog/new_post.html.twig', array(
            'form' => $form->createView()    
        ));
    }

    #[Route('/single_post/{id}', name: 'single_post')]
    public function post(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->find($id);
        /** @var PostRepository $repository */
        $recents = $repository->findRecents();

        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $form->getData();
            
            $comment->setPost($post);

            $comment->setPublishedAt(new \DateTime()); 

            $post->setNumComments($post->getNumComments() + 1);

            $entityManager = $doctrine->getManager();    
            $entityManager->persist($comment);
            $entityManager->flush();

            return $this->redirectToRoute('single_post', ["id" => $post->getId()]);
        }

        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
            'recents' => $recents,
            'commentForm' => $form->createView()
        ]);
    }
}
