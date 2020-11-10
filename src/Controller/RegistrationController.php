<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\AuthAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use App\Form\UserFormType;
use App\Security\UserAuthenticator;
use Faker\Provider\DateTime;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class RegistrationController extends AbstractController
{

    /**
     * Method qui retourne un TOKEN csrf
     * @return HASH
     */
    public function generateCSRFToken()
    {
        $length = 5;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ%=%%%%%%%%%%%%%%%%%%%';
        $character = strlen($characters);
        $strng = '';
        for ($i = 0; $i < $length; $i++) {
            $strng .= $characters[mt_rand(0, $character - 1)];
        }
        return $strng;
    }

    /**
     * @Route("/register", name="app_register")
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, AuthAuthenticator $authenticator): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if($request->request->count() > 0){
                $remoteIp = $_SERVER["REMOTE_ADDR"];
                $resp = $request->get('g-recaptcha-response');
                $recaptcha = new \ReCaptcha\ReCaptcha('6LdqWuQUAAAAAGvvgaIivln1HSH9WL1ypIpHFHE1'); //dev
                //  $recaptcha = new \ReCaptcha\ReCaptcha('6Lch-_8UAAAAACVH7R9TZ5Cppd_RqaO9Ixu5DEHR'); //prod
                $resp = $recaptcha->verify($request->request->get('g-recaptcha-response'),  $remoteIp);
                if ($resp->isSuccess()) {
                    $token = $this->generateCSRFToken();
                    // $user->setPassword($passwordEncoder->encodePassword($user, $form->get('plainPassword')->getData()));
                    $hash = $passwordEncoder->encodePassword($user, $token);
                    $user->setPassword($hash)
                        ->setCreatedAt(new \DateTime());
                    // $user->setCreatedAt(new \DateTime());
                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->persist($user);
                    $entityManager->flush();
                    // do anything else you need here, like send an email
                    $mail = htmlspecialchars($user->getEmail());
                    $name = $user->getEmail();
                    $str = 'Bienvenue sur CovidGame.fr, vous pouvez maintenant avoir accès à nos services,  voici votre adresse de messagerie enregistrer ' . $user->getEmail() . '  et votre  mot de passe de connexion : ' . PHP_EOL . $token . PHP_EOL . '  modifiez le dans les paramètres de votre profil';
                    $msg = htmlspecialchars($str);
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers = "From:contact@covidgame.fr" . PHP_EOL;
                    $headers .= 'Content-type: text/html; charset=utf-8' . PHP_EOL;
                    mail($mail, $name, $msg, $headers);
                    return $this->redirectToRoute('success_registration');
                }
            }
        }
        return $this->render('register/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }


    /**
     * @Route("success_register", name="success_registration")
     */
    public function successRegistration(): Response
    {
        return $this->render('register/success_register.html.twig');
    }

    /**
     * @Route("create_admin_card/{id}", name="create_covid_card")
     */
    public function createAdminCard(Request $request, User $user): Response
    {
        if ($this->getUser()) {
            $user = $this->getUser();
            $created = $user->getState();
        } else {
            $created = 'none';
        }
        $form = $this->createForm(UserFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setState(true);
            $manager = $this->getDoctrine()->getManager();
            $manager->persist($user);
            $manager->flush();
            return $this->redirectToRoute('success_create_card', [
                'id' => $user->getId()
            ]);
        }
        if ($this->getUser()) {
            return $this->render('register/create_covid_card.html.twig', [
                'UserForm' => $form->createView()
            ]);
        } else {
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("success_create_card", name="success_create_card")
     */
    public function successCard(): Response
    {
        return $this->render('register/success_create_card.html.twig');
    }

    /**
     * @Route("pass/lost", name="pass_lost")
     */
    public function passLost(Request $request, UserPasswordEncoderInterface $encoder)
    {
        if ($request->request->get('send') && $request->request->get('send') == 'new_pass')
        {
            if ( filter_var($request->request->get('email_pass_lost'), FILTER_VALIDATE_EMAIL)) {
                $mailUser = $request->request->get('email_pass_lost');
                $em = $this->getDoctrine()->getManager();
                // voir l'épisode 2 de cette série pour retrouver la méthode loadUserByUsername:
                $user = $em->getRepository(User::class) ->findOneBy([
                    'email' => $mailUser
                ]);
                $pwd = $this->generateCSRFToken();
                $encode = $encoder->encodePassword($user, $pwd);
                $newPass =  $user->setPassword($encode);
                $mail = $mailUser;
                $name = '';
                $str = 'CovidGame :  voici votre nouveau mot de passe : '.$pwd;
                $msg = htmlspecialchars($str);
                $headers = "MIME-Version: 1.0\r\n";
                $headers = "From:contact@covidgame.fr" . PHP_EOL;
                $headers .= 'Content-type: text/html; charset=utf-8' . PHP_EOL;
                mail($mail, $name, $msg, $headers);
                $em = $this->getDoctrine()->getManager();
                $em->persist($newPass);
                $em->flush();
                return $this->render('security/success_pass_lost.html.twig');
            }else{
                return $this->redirectToRoute('error_contact');
            }
        }
        return $this->render('security/new_pass.html.twig');
    }

}
