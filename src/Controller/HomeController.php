<?php

namespace App\Controller;

use App\Form\LostFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use App\Form\UserFormType;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class HomeController extends AbstractController
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
     * @Route("/")
     * @Route("/home", name="home")
     */
    public function index()
    {
        if ($this->getUser())
        {
            $user = $this->getUser();
            $created = $user->getState();
        }else{
            $created = 'none';
        }
        if ($created == null)
        {
            return $this->redirectToRoute('create_covid_card', [
                'id' => $user->getId()
            ]);
        }else {
            return $this->render('home/taches-index.html.twig');
        }
    }

    /**
     * @Route("/contact", name="contact")
     */
    public function contact(Request $request){
        if($request->request->count() > 0){
            if ($request->request->get('submitForm')) {
                if ($request->request->get('g-recaptcha-response')) {
                    if (filter_var($request->request->get('email'), FILTER_VALIDATE_EMAIL)) {
                        $mail = htmlspecialchars($request->request->get('email'));
                        $name = htmlspecialchars($request->request->get('name'));
                        $msg = htmlspecialchars($request->request->get('msg'));
                        $headers = "MIME-Version: 1.0\r\n";
                        $headers = "From:$mail" . PHP_EOL;
                        $headers .= 'Content-type: text/html; charset=utf-8' . PHP_EOL;
                        $remoteIp = $_SERVER["REMOTE_ADDR"];
                        $recaptcha = new \ReCaptcha\ReCaptcha('6LdqWuQUAAAAAGvvgaIivln1HSH9WL1ypIpHFHE1'); //dev
                        //  $recaptcha = new \ReCaptcha\ReCaptcha('6Lch-_8UAAAAACVH7R9TZ5Cppd_RqaO9Ixu5DEHR'); //prod
                        $resp = $recaptcha->verify($request->request->get('g-recaptcha-response'),  $remoteIp);
                        if ($resp->isSuccess()) {
                            mail("dzaidzai@hotmail.fr", $name, $msg, $headers);
                            return $this->redirectToRoute('success_contact');
                        }
                    } else {
                        return $this->redirectToRoute('error_contact');
                    }
                }
            }
        }
        return $this->render('pet/contact.html.twig');
    }

    /**
     * @Route("covidGame/storyTry", name="storyTry")
     */
    public function  storyTry(UserRepository $userRepository, Request $request)
    {
        if(!isset($_SESSION)){
            session_start();
        }
        if ($request->request->get('storyTry') && $request->request->get('storyTry') == 'true') {
            if ($request->request->get('pseudo')) {
                $_SESSION['pseudo'] = htmlspecialchars($request->request->get('pseudo')) ;
                $user = $_SESSION['pseudo'];
            }
            if($request->request->get('age')){
                $_SESSION['age'] = htmlspecialchars($request->request->get('age'));
                if (intval($_SESSION['age'])) {
                    $age = $_SESSION['age'];
                }
            }
            if ($request->request->get('exitGame') && $request->request->get('exitGame') == 'false'){
                $_SESSION = array();
                session_unset();
                session_destroy();
                header("Cache-Control:no-cache");
                return $this->redirectToRoute('home');
            }
            return $this->render('home/try.html.twig', [
                'user' => $user,
                'age' => $age
            ]);
        }
        return $this->render('home/storyTry.html.twig');
    }

    /**
     * @Route("gameTry", name="gameTry")
     */
    public function gameTry(Request $request){
        if ($request->request->get('nextGame')){
            return $this->redirectToRoute('storyTryGame');
        }
        if ($request->request->get('exitGame') && $request->request->get('exitGame') == 'false'){
            $_SESSION = array();
            session_unset();
            session_destroy();
            header("Cache-Control:no-cache");
            return $this->redirectToRoute('home');
        }
        return $this->render('home/gameTry.html.twig', [
            'user' => $_SESSION['pseudo'],
            'age' => $_SESSION['age']
        ]);
    }

    /**
     * @Route("storyTryGame", name="storyTryGame")
     */
    public function storyTryGame(){



        return $this->render('home/storyTryGame.html.twig', [
            'user' => $_SESSION['pseudo'],
            'age' => $_SESSION['age']
        ]);
    }

    /**
     * @Route("/old/pass/user/{id}", name="old_pass")
     */
    public function oldPass(Request $request, UserPasswordEncoderInterface $encoder)
    {
        if($request->request->get('new_pass') && $request->request->get('new_pass') == 'new_pass')
        {
            $user = $this->getUser();
            $em = $this->getDoctrine()->getManager();
            // voir l'épisode 2 de cette série pour retrouver la méthode loadUserByUsername:
            $user = $em->getRepository(User::class) ->findOneBy([
                'id' => $user->getId()
            ]);
            $oldPass =  $request->request->get('old_pass');
            if ($encoder->isPasswordValid($user, $oldPass))
            {
                return $this->redirectToRoute('new_pass', [
                    'id' => $user->getId()
                ]);
            }else{
                return $this->redirectToRoute('error_old_pass');
            }
        }
        return $this->render('security/modif_pass.html.twig');
    }

    /**
     * @route("new/pass/user/{id}", name="new_pass")
     */
    public  function newPass(Request $request, UserPasswordEncoderInterface $encoder)
    {
        if($request->request->get('new_pass') && $request->request->get('new_pass') == 'new_pass')
        {
            $newPass = $request->request->get('new_pass_user');
            $confirmPass = $request->request->get('confirm_new_pass');
            $user = $this->getUser();
            if ($newPass == $confirmPass)
            {
                $user = $this->getUser();
                $em = $this->getDoctrine()->getManager();
                $encode = $encoder->encodePassword($user, $newPass);
                $pass = $user->setPassword($encode);
                $em->persist($pass);
                $em->flush();
                return $this->redirectToRoute('success_change_pass');
            }else{
                return $this->redirectToRoute('error_change_pass', [
                    'id' => $user->getId()
                ]);
            }
        }
        return $this->render('security/new_pass_user.html.twig');
    }

    /**
     * @Route("/error/pass/user", name="error_old_pass")
     */
    public function errorOldPass()
    {
        return $this->render('security/error_pass.html.twig');
    }

    /**
     * @Route("/success/pass/user", name="success_change_pass")
     */
    public function successModifPass()
    {
        return $this->render('security/success_change_pass.html.twig');
    }

    /**
     * @Route("/error/pass/user/{id}", name="error_change_pass")
     */
    public function errorModifPass()
    {
        return $this->render('security/error_change_pass.html.twig');
    }
}
