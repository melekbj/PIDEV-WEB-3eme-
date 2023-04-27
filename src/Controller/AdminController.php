<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Store;
use App\Entity\Rating;
use App\Form\UserType;
use App\Form\FormEvent;
use Twilio\Rest\Client;
use App\Entity\Evenement;
use App\Entity\EventType;
use App\Form\RegisterType;
use App\Entity\Reclamation;
use App\Form\FormEventType;
use App\Entity\CategorieStore;
use App\Service\QrcodeService;
use App\Entity\TypeReclamation;
use App\Service\SendSmsService;
use App\Service\SendMailService;
use App\Form\ReclamationTypeType;
use App\Repository\UserRepository;
use App\Repository\StoreRepository;
use App\Repository\EvenementRepository;
use App\Repository\EventTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ReclamationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\CategorieStoreRepository;
use App\Repository\TypeReclamationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\Persistence\ManagerRegistry as PersistenceManagerRegistry;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin')]
    public function index(QrcodeService $qrcodeService): Response
    {
        $user = $this->getUser();
        $image = $user->getImage();

        $em = $this->getDoctrine()->getManager();
        $userRepository = $em->getRepository(User::class);
        // Get the count of users with etat = 0
        $user0 = $userRepository->count(['etat' => 0]);
        // Get the count of users with etat = 1
        $user1 = $userRepository->count(['etat' => 1]);
        // Get the count of users with etat = -1
        $user2 = $userRepository->count(['etat' => -1]);
        // Get the count of users with etat = -2
        $user3 = $userRepository->count(['etat' => -2]);
 
        $qrcodeDataUri = $qrcodeService->qrcode($user0,$user1,$user2,$user3);

        

        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
            'image' => $image,
            'well' => $user0,
            'pending' => $user1,
            'blocked' => $user2,
            'disapproved' => $user3,
            'qrcode' => $qrcodeDataUri,
        ]);
    }


    #[Route('/liste_des_utilisateurs', name: 'app_users')]
    #[Security("is_granted('ROLE_ADMIN')")]
    public function ListeU(PaginatorInterface $paginator, UserRepository $userRepository, Request $request): Response
    {
        // Get the current user
        $user = $this->getUser();

        // Get the image associated with the user
        $image = $user->getImage();

        // Get all users except admins and blocked users, ordered by name
        $usersQuery = $userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :roles1 OR u.roles LIKE :roles2')
            ->andWhere('u.etat <> :etat')
            ->orderBy('u.nom', 'ASC')
            ->setParameters([
                'roles1' => '%ROLE_CLIENT%',
                'roles2' => '%ROLE_PARTNER%',
                'etat' => 1,
            ])
            ->getQuery();

        // Paginate users
        $users = $paginator->paginate(
            $usersQuery,
            $request->query->getInt('page', 1), // current page number
            5 // number of results per page
        );

        return $this->render('admin/ListeUsers.html.twig', [
            'users' => $users,
            'image' => $image,
        ]);
    }


    #[Route('/liste_des_partenaires', name: 'app_partners')]
    public function ListeP(Request $request,PaginatorInterface $paginator): Response
    {
        // Get the current user
        $user = $this->getUser();
        
        // Get the image associated with the user
        $image = $user->getImage();
        //recuperer le repository
        $repository = $this->getDoctrine()->getRepository(User::class);
        //utiliser findAll() pour recuperer toutes les classes
        $users = $repository->findBy(['etat' => [1, -2]]);
        
        // Paginate the results
        $users = $paginator->paginate(
            $users,
            $request->query->getInt('page', 1),
            3 // items per page
        );
        

        return $this->render('admin/ListePartners.html.twig', [
            'users' => $users,
            'image' => $image,
        ]);
    }

    #[Route('/deleteU/{id}', name: 'app_deleteU')]
    public function deleteU($id, UserRepository $rep, ManagerRegistry $doctrine ): Response
    {

        //recuperer la classe a supprimer
        $users = $rep->find($id);
        $rep=$doctrine->getManager();
        //supprimer la classe        
        $rep->remove($users);
        $rep->flush();
        //flash message
        $this->addFlash('success', 'User deleted successfully!');
        return $this->redirectToRoute('app_users'); 
        
    }


    #[Route('/updateU/{id}', name: 'app_updateU')]
    public function updateU($id, Request $request, UserRepository $rep, ManagerRegistry $doctrine): Response
    {
        // Get the current user
        $user = $this->getUser();
        
        // Get the image associated with the user
        $image = $user->getImage();
        // récupérer la classe à modifier
        $users = $rep->find($id);
        // créer un formulaire
        $form = $this->createForm(UserType::class, $users);
        // récupérer les données saisies
        $form->handleRequest($request);
        // vérifier si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // récupérer les données saisies
            $users = $form->getData();
            // persister les données
            $rep = $doctrine->getManager();
            $rep->persist($users);
            $rep->flush();
            //flash message
            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('app_users');
        }
        return $this->render('admin/EditUsers.html.twig', [
            'form' => $form->createView(),
            'image' => $image,
        ]);
    }


    #[Route('/profile', name: 'app_profile')]
    public function updateProfile(Request $request)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        // Get the image associated with the user
        $image = $user->getImage();

        // Create a new userType form and populate it with the user's data
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save the updated user information to the database
            $entityManager->persist($user);
            $entityManager->flush();

            // Redirect to the user's profile page with a success message
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('app_profile');
            
        }
        // If the form was not submitted or is not valid, render the profile edit form
        return $this->render('admin/profile.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'image' => $image,
        ]);
    }

    
    #[Route('/updatePassword', name: 'update_password', methods:['POST'])] 
    public function updatePassword(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        // Get the current user
        $user = $this->getUser();
 
        // Get the submitted form data
        $actualPassword = $request->request->get('actualPassword');
        $newPassword = $request->request->get('newPassword');
        $confirmPassword = $request->request->get('ConfirmPassword');
        
        // Check that the current password is correct
        if (!$passwordEncoder->isPasswordValid($user, $actualPassword)) {
            $this->addFlash('error', 'Current password is incorrect.');
            return $this->redirectToRoute('app_profile');
        }
        
        // Check that the new password and confirm password match
        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'New password and confirm password do not match.');
            return $this->redirectToRoute('app_profile');
        }
        
        // Encode the new password and update the user's password
        $newEncodedPassword = $passwordEncoder->encodePassword($user, $newPassword);
        $user->setPassword($newEncodedPassword);
        $this->getDoctrine()->getManager()->flush();
        
        // Redirect to a success page
        $this->addFlash('success', 'Password updated successfully.');
        return $this->redirectToRoute('app_profile');
    }


    #[Route('/blockU/{id}', name: 'app_blockU')]
    public function blockU($id, UserRepository $rep, ManagerRegistry $doctrine, SendMailService $mail): Response
    {
        // Get the user to deactivate
        $user = $rep->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Set the user's etat to -1
        $user->setEtat(-1);



        $em = $doctrine->getManager();
        $em->persist($user);
        $em->flush();
        
        // Envoi du mail
        $mail->sendMail(
            'melekbejaoui29@gmail.com', 'Storeship',
            $user->getEmail(),
            'Account Restriction',
            'block',
            [
                'user' => $user,
            ]
        );

        // $accountSid = 'ACa141e95e70a02a529768fb9df90ebcea';
        // $authToken = 'f742163ccee2b8b28cfe63cc8b40d341';
        // $fromNumber = '+15076288954';
    
        // // Instantiate the Twilio client
        // $twilio = new Client($accountSid, $authToken);
    
        // // Instantiate the SendSmsService and set the required parameters
        // $sms = new SendSmsService();
        // $sms->setAccountSid($accountSid);
        // $sms->setAuthToken($authToken);
        // $sms->setFromNumber($fromNumber);
        // $sms->setClient($twilio);
    
        // // Send an SMS to the user
        // $sms->send($user->getPhone(), 'Your account has been approved.');

        //flash message
        $this->addFlash('success', 'User blocked successfully!');

        return $this->redirectToRoute('app_users');
    }


    #[Route('/approveU/{id}', name: 'app_approveU')]
    public function approveU($id, UserRepository $rep, ManagerRegistry $doctrine, SendMailService $mail): Response
    {
        // Get the user to deactivate
        $user = $rep->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Set the user's etat to -1
        $user->setEtat(0);



        $em = $doctrine->getManager();
        $em->persist($user);
        $em->flush();
        
        // Envoi du mail
        $mail->sendMail(
            'melekbejaoui29@gmail.com', 'Storeship',
            $user->getEmail(),
            'Account Approval Confirmation',
            'approve',
            [
                'user' => $user,
            ]
        );

        // $accountSid = 'ACa141e95e70a02a529768fb9df90ebcea';
        // $authToken = 'f742163ccee2b8b28cfe63cc8b40d341';
        // $fromNumber = '+15076288954';
    
        // // Instantiate the Twilio client
        // $twilio = new Client($accountSid, $authToken);
    
        // // Instantiate the SendSmsService and set the required parameters
        // $sms = new SendSmsService();
        // $sms->setAccountSid($accountSid);
        // $sms->setAuthToken($authToken);
        // $sms->setFromNumber($fromNumber);
        // $sms->setClient($twilio);
    
        // // Send an SMS to the user
        // $sms->send($user->getPhone(), 'Your account has been approved.');

        //flash message
        $this->addFlash('success', 'User approved successfully!');

        return $this->redirectToRoute('app_users');
    }

    #[Route('/disapproveU/{id}', name: 'app_disapproveU')]
    public function disapproveU($id, UserRepository $rep, ManagerRegistry $doctrine, SendMailService $mail): Response
    {   
        // Get the user to deactivate
        $user = $rep->find($id);
    
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
    
        // Set the user's etat to -2
        $user->setEtat(-2);
    
        $em = $doctrine->getManager();
        $em->persist($user);
        $em->flush();
    
        // Envoi du mail
        $mail->sendMail(
            'melekbejaoui29@gmail.com', 'Storeship',
            $user->getEmail(),
            'Account Disapproval Notice',
            'disapprove',
            [
                'user' => $user,
            ]
        );

        // $accountSid = 'ACa141e95e70a02a529768fb9df90ebcea';
        // $authToken = '55d176b56ee67e33afb9fdfa4b670479';
        // $fromNumber = '+15076288954';
    
        // // Instantiate the Twilio client
        // $twilio = new Client($accountSid, $authToken);
    
        // // Instantiate the SendSmsService and set the required parameters
        // $sms = new SendSmsService();
        // $sms->setAccountSid($accountSid);
        // $sms->setAuthToken($authToken);
        // $sms->setFromNumber($fromNumber);
        // $sms->setClient($twilio);
    
        // // Send an SMS to the user
        // $sms->send($user->getPhone(), 'Your account has been disapproved.');
    
        //flash message
        $this->addFlash('error', 'User Disapproved !');
    
        return $this->redirectToRoute('app_partners');
    }
    
// ................................................ Gesion Events..................................................................................................... 

    #[Route('/event/new', name: 'app_events_new')]
    public function newEvent(Request $request, PersistenceManagerRegistry $doctrine): Response
    {
        $user = $this->getUser();
        // Get the image associated with the user
        $image = $user->getImage();

        $events = new Evenement();
        $form = $this->createForm(FormEvent::class, $events);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $entityManager = $doctrine->getManager();
            $entityManager->persist($events);
            $entityManager->flush();
            $this->addFlash('success', 'Event ajouté avec succès');
            return $this->redirectToRoute('app_events_liste');
        }
        
        return $this->render('admin/Events/addEvent.html.twig', [
            'form' =>$form->createView(),
            'image' => $image,
        ]);
    }

    #[Route('/events/liste', name: 'app_events_liste')]
    public function newType(Request $request, PersistenceManagerRegistry $doctrine, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        // Get the image associated with the user
        $image = $user->getImage();

        $eventRepository = $entityManager->getRepository(Evenement::class);
        $events = $eventRepository->findAll();

        $event = new EventType();
        $form = $this->createForm(FormEventType::class, $event);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $entityManager = $doctrine->getManager();
            $entityManager->persist($event);
            $entityManager->flush();
            $this->addFlash('success', 'Event type ajouté avec succès');
            return $this->redirectToRoute('app_events_liste');
        }
        
        return $this->render('admin/Events/listeEvents.html.twig', [
            'typeForm' =>$form->createView(),
            'image' => $image,
            'events' => $events,
        ]);
    }

    #[Route('/deleteEvent/{id}', name: 'app_deleteEvent')]
    public function deleteEvent($id, EvenementRepository $rep, ManagerRegistry $doctrine ): Response
    {
        //recuperer la classe a supprimer
        $events = $rep->find($id);
        $rep=$doctrine->getManager();
        //supprimer la classe        
        $rep->remove($events);
        $rep->flush();
        //flash message
        $this->addFlash('success', 'Event deleted successfully!');
        return $this->redirectToRoute('app_events_liste'); 
        
    }

    #[Route('/updateEvent/{id}', name: 'app_updateEvent')]
    public function updateEvent($id, Request $request, EvenementRepository $rep, ManagerRegistry $doctrine): Response
    {
        // Get the current user
        $user = $this->getUser();
        // Get the image associated with the user
        $image = $user->getImage();
        // récupérer la classe à modifier
        $events = $rep->find($id);
        // créer un formulaire
        $form = $this->createForm(FormEvent::class, $events);
        // récupérer les données saisies
        $form->handleRequest($request);
        // vérifier si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // récupérer les données saisies
            $events = $form->getData();
            // persister les données
            $rep = $doctrine->getManager();
            $rep->persist($events);
            $rep->flush();
            //flash message
            $this->addFlash('success', 'Event updated successfully!');
            return $this->redirectToRoute('app_events_liste');
        }
        return $this->render('admin/Events/EditEvents.html.twig', [
            'form' => $form->createView(),
            'image' => $image,
        ]);
    }

// ................................................ Gesion Types Events..................................................................................................... 

    #[Route('/type_events/liste', name: 'app_types_events_liste')]
    public function EventsTypes(Request $request, PersistenceManagerRegistry $doctrine, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        // Get the image associated with the user
        $image = $user->getImage();

        $eventRepository = $entityManager->getRepository(EventType::class);
        $events = $eventRepository->findAll();

        return $this->render('admin/Events/listeTypesEvents.html.twig', [
            // 'typeForm' =>$form->createView(),
            'image' => $image,
            'events' => $events,
        ]);
    }

    #[Route('/delete_Event_type/{id}', name: 'app_deleteEventType')]
    public function deleteEventType($id, EventTypeRepository $rep, ManagerRegistry $doctrine ): Response
    {
        //recuperer la classe a supprimer
        $events = $rep->find($id);
        $rep=$doctrine->getManager();
        //supprimer la classe        
        $rep->remove($events);
        $rep->flush();
        //flash message
        $this->addFlash('success', 'Event types deleted successfully!');
        return $this->redirectToRoute('app_types_events_liste'); 
        
    }

// .........................................Gestion Store..........................................................


    #[Route('/list_stores', name: 'app_store_index', methods: ['GET'])]
    public function liststore(StoreRepository $storeRepository, EntityManagerInterface $entityManager,Request $request): Response
    {
            $user = $this->getUser();
            // Get the image associated with the user
            $image = $user->getImage();
            $location=$request->get('localtion');
            $nom=$request->get('nom');
    
    
            $stores = $storeRepository->findLocalisationOrNom($location,$nom);
            $averageRatings = [];
                
            foreach ($stores as $store) {
                $ratings = $entityManager->getRepository(Rating::class)->findBy(['store' => $store]);
                $ratingValue = 0;
                $count = count($ratings);
                if ($count > 0) {
                    foreach ($ratings as $rating) {
                        $ratingValue += $rating->getRate();
                    }
                    $averageRating = $ratingValue / $count;
                } else {
                    $averageRating = 0;
                }
                $averageRatings[$store->getId()] = $averageRating;
            }
    
            return $this->render('admin/store/index.html.twig', [
                'stores' => $stores,
                'averageRatings' => $averageRatings,
                'image' => $image,
            ]);
    }

    #[Route('/store/{id}', name: 'app_store_show', methods: ['GET'])]
    public function show(Store $store, Security $security, StoreRepository $storeRepository): Response
    {
        $user = $this->getUser();
        // Get the image associated with the user
        $image = $user->getImage();
        return $this->render('admin/store/show.html.twig', [
            'store' => $store,
            'image' => $image,
        ]);
    }

    #[Route('/categorie_store', name: 'app_categorie_store_index', methods: ['GET'])]
    public function ListCategorie(CategorieStoreRepository $categorieStoreRepository,PersistenceManagerRegistry $doctrine, EntityManagerInterface $entityManager): Response
    {
            $user = $this->getUser();
            // Get the image associated with the user
            $image = $user->getImage();
            $cat = $entityManager->getRepository(CategorieStore::class);
            $categoriestores = $cat->findAll();

            return $this->render('admin/categorie_store/index.html.twig', [
                'categorie_store' => $categoriestores,
                'image' => $image,
            ]);
    }

// .........................................Gestion Reclamation..........................................................

    #[Route('/liste_des_reclamation', name: 'app_reclamations_list')]
    public function ListeReclamations(Request $request,PaginatorInterface $paginator,EntityManagerInterface $entityManager): Response
    {
            // Get the current user
            $user = $this->getUser();
            // Get the image associated with the user
            $image = $user->getImage();

            $recRepo = $entityManager->getRepository(Reclamation::class);
            $reclamations = $recRepo->findAll();

            $reclamationType = new TypeReclamation();
            $form = $this->createForm(ReclamationTypeType::class, $reclamationType);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($reclamationType);
                $entityManager->flush();

                $this->addFlash('success', 'Reclamation type added successfully.');

                return $this->redirectToRoute('app_reclamations_list');
            }
            

            return $this->render('admin/reclamation/listeReclamation.html.twig', [
                'image' => $image,
                'reclamations' => $reclamations,
                'typeForm' => $form->createView(),
            ]);
    }

    #[Route('/acceptR/{id}', name: 'app_acceptR')]
    public function acceptR($id, ReclamationRepository $rep, ManagerRegistry $doctrine): Response
    {
            // Get the reclamation to deactivate
            $reclamation = $rep->find($id);
    
            if (!$reclamation) {
                throw $this->createNotFoundException('User not found');
            }
    
            // Set the reclamation's etat to -1
            $reclamation->setEtat('accepted');
    
    
            $em = $doctrine->getManager();
            $em->persist($reclamation);
            $em->flush();
            
          
            //flash message
            $this->addFlash('success', 'reclamation accepeted successfully!');
    
            return $this->redirectToRoute('app_reclamations_list');
    }
    #[Route('/refuseR/{id}', name: 'app_refusR')]
    public function refusR($id, ReclamationRepository $rep, ManagerRegistry $doctrine): Response
    {
            // Get the reclamation to deactivate
            $reclamation = $rep->find($id);
    
            if (!$reclamation) {
                throw $this->createNotFoundException('User not found');
            }
    
            // Set the reclamation's etat to -1
            $reclamation->setEtat('refused');
    
    
            $em = $doctrine->getManager();
            $em->persist($reclamation);
            $em->flush();
            
          
            //flash message
            $this->addFlash('success', 'reclamation refused successfully!');
    
            return $this->redirectToRoute('app_reclamations_list');
    }

// .........................................Gestion Reclamation Type..........................................................
    
    #[Route('/type_reclamation/liste', name: 'app_types_reclamation_liste')]
    public function ReclamationTypes(Request $request, PersistenceManagerRegistry $doctrine, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        // Get the image associated with the user
        $image = $user->getImage();
        $recRepo = $entityManager->getRepository(TypeReclamation::class);
        $reclamations = $recRepo->findAll();
        return $this->render('admin/reclamation/listeTypeReclamation.html.twig', [
            // 'typeForm' =>$form->createView(),
            'image' => $image,
            'reclamations' => $reclamations,
        ]);
    }


    #[Route('/delete_Reclamation_type/{id}', name: 'app_deletetypeReclamation')]
    public function deleteReclamationType($id, TypeReclamationRepository $rep, ManagerRegistry $doctrine ): Response
    {
        //recuperer la classe a supprimer
        $reclamations = $rep->find($id);
        $rep=$doctrine->getManager();
        //supprimer la classe        
        $rep->remove($reclamations);
        $rep->flush();
        //flash message
        $this->addFlash('success', 'Reclamation types deleted successfully!');
        return $this->redirectToRoute('app_types_reclamation_liste'); 
        
    }

    #[Route('/delete_Reclamation_type/{id}', name: 'app_updatetypeReclamation')]
    public function updateReclamationType($id, Request $request, TypeReclamationRepository $rep, ManagerRegistry $doctrine): Response
    {
        // Get the current user
        $user = $this->getUser();
        // Get the image associated with the user
        $image = $user->getImage();
        // récupérer la classe à modifier
        $reclamations = $rep->find($id);
        // créer un formulaire
        $form = $this->createForm(ReclamationTypeType::class, $reclamations);
        // récupérer les données saisies
        $form->handleRequest($request);
        // vérifier si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // récupérer les données saisies
            $reclamations = $form->getData();
            // persister les données
            $rep = $doctrine->getManager();
            $rep->persist($reclamations);
            $rep->flush();
            //flash message
            $this->addFlash('success', 'Event updated successfully!');
            return $this->redirectToRoute('app_types_reclamation_liste');
        }
        return $this->render('admin/reclamation/editTypeReclamation.html.twig', [
            'form' => $form->createView(),
            'image' => $image,
        ]);
    }


}
