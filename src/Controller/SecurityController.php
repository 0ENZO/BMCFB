<?php

namespace App\Controller;

use Exception;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Modification de mot de passe par l'utilisateur
     * @Route("/password_change", name="app_password_change")
     * @IsGranted("ROLE_USER")
     */
    public function password_change(Request $request, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, LoginFormAuthenticator $authenticator): Response
    {

        if ($request->isMethod('POST')) {

            $em = $this->getDoctrine()->getManager();
            $user = $this->getUser();
            $passwordOld = $request->request->get('passwordOld');
            $passwordNew = $request->request->get('passwordNew');
            $passwordNewConfirm = $request->request->get('passwordNewConfirm');

            if (!$passwordEncoder->isPasswordValid($user, $passwordOld)) {
                $this->addFlash('warning', 'Mot de passe erroné');
                return $this->redirectToRoute($request->get('_route')); // current route
            }

            if ($passwordNew != $passwordNewConfirm) {
                $this->addFlash('warning', 'Les mots de passe ne correspondent pas');
                return $this->redirectToRoute($request->get('_route'));
            }

            $user->setPassword($passwordEncoder->encodePassword($user, $passwordNew));
            $em->persist($user);
            $em->flush();
            // return $guardHandler->authenticateUserAndHandleSuccess($user, $request, $authenticator, 'main');
            $this->addFlash('info', 'Mot de passe modifié');
            return $this->redirectToRoute('user_profile');

        }

        return $this->render('security/password_change.html.twig');
    }

     /**
     * Demande d'envoi de mail pour remise à zéro du mot de passe
     * @Route("/password_request", name="app_password_request")
     */
    public function password_request(Request $request, UserPasswordEncoderInterface $encoder, MailerInterface $mailer, TokenGeneratorInterface $tokenGenerator): Response
    {

        if ($request->isMethod('POST')) {

            $em = $this->getDoctrine()->getManager();
            $email = $request->request->get('email');
            $user = $em->getRepository(User::class)->findOneByEmail($email);

            if ($user === null) {
                $this->addFlash('warning', 'Email inconnu');
                return $this->redirectToRoute($request->get('_route'));
            }

            $token = $tokenGenerator->generateToken();

            try {
                $user->setResetToken($token);
                $em->flush();
            } catch (\Exception $e) {
                $this->addFlash('warning', $e->getMessage());
                return $this->redirectToRoute('home');
            }

            $url = $this->generateUrl('app_password_reset', array('token' => $token), UrlGeneratorInterface::ABSOLUTE_URL);

            /* $message = (new \Swift_Message('Learn | Demande de modification du mot de passe'))
                ->setFrom('ampli@dsides.net')
                ->setTo($user->getEmail())
                ->setBcc('ampli@dsides.net')
                // ->setBody($this->renderView('email/moderation.html.twig', ['idea' => $idea]), 'text/html')
                ->setBody(
                    "Rendez-vous à <a href ='" . $url . "'>cette adresse</a> pour modifier votre mot de passe" . $url,
                    'text/html'
                );
            $mailer->send($message); */

            $email = (new TemplatedEmail())
                ->from('equipe@makelearn.fr')
                ->to($user->getEmail())
                ->bcc('equipe@makelearn.fr')
                ->replyTo('equipe@makelearn.fr')
                ->subject('BMCFB | Demande de modification du mot de passe')
                // ->text('Sending emails is fun again!')
                // ->html('<p>See Twig integration for better HTML integration!</p>')
                // path of the Twig template to render
                ->htmlTemplate('email/password_request.html.twig')
                // pass variables (name => value) to the template
                ->context([
                    'url' => $url,
                ])
            ;

            $mailer->send($email);


            // forcer la déconnexion ici, au cas où ?

            // Attention, pas de prise en charge des alertes sur la home de Learn
            // $this->addFlash('info', "Email envoyé. Veuillez vérifier votre boîte de réception.");
            // return $this->redirectToRoute('home');
            return $this->render('security/password_requested.html.twig');

        }

        return $this->render('security/password_request.html.twig');

    }

    /**
     * Remise à zéro du mot de passe
     * @Route("/password_reset/{token}", name="app_password_reset")
     */
    public function password_reset(Request $request, string $token, UserPasswordEncoderInterface $passwordEncoder)
    {

        if ($request->isMethod('POST')) {

            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository(User::class)->findOneByResetToken($token);
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('passwordConfirm');

            if ($user === null) {
                $this->addFlash('warning', "La modification du mot de passe a échoué : Token inconnu");
                return $this->redirectToRoute('home');
            }

            if ($password != $passwordConfirm) {
                $this->addFlash('warning', 'Les mots de passe ne correspondent pas');
                return $this->redirectToRoute($request->get('_route'), ['token' => $token]);
            }

            $user->setResetToken(null);
            $user->setPassword($passwordEncoder->encodePassword($user, $password));
            $em->flush();

            $this->addFlash('info', "Le mot de passe a été modifié, vous pouvez vous connecter");
            return $this->redirectToRoute('app_login');

        } 
        
        return $this->render('security/password_reset.html.twig', ['token' => $token]);

    }

    /**
     * @Route("/login_check", name="login_check")
     */
    public function check()
    {
        throw new \LogicException('This code should never be reached');
    }

    /**
     * @Route("/magiclogin/{userEmail}", name="login_link")
     */
    public function requestLoginLink(LoginLinkHandlerInterface $loginLinkHandler, Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, 
    string $userEmail = null, MailerInterface $mailer)
    {
        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $userEmail]);

        if(filter_var($userEmail, FILTER_VALIDATE_EMAIL)){
            if (!$user) {
                $user = new User();
                $plainPassword = 'azerty';
                $password = $passwordEncoder->encodePassword($user, $plainPassword);
                $user->setPassword($password);
                $user->setEmail($userEmail);
                $em->persist($user);
                $em->flush();
            }
            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);
            $loginLink = $loginLinkDetails->getUrl();

            $email = (new TemplatedEmail())
                ->from('equipe@makelearn.fr')
                ->to($userEmail)
                ->bcc('equipe@makelearn.fr')
                ->replyTo('equipe@makelearn.fr')
                ->subject('BMCFB | Lien de connexion')
                ->htmlTemplate('email/send_magic_link.html.twig')
                ->context([
                    'loginLink' => $loginLink,
                ]);
            $mailer->send($email);

            return $this->redirectToRoute('magic_link_sent', [
                'email' => $userEmail,
            ]);

        }else{
            dump('EMAIL PAS VALIDE');
            throw new BadRequestHttpException('Email non valide');
        }
    }

    /**
     * @Route("/requestTest", name="requestTest")
     */
    public function requestTest()
    {
        
        $response = new Response();
        $response->setContent(json_encode([
            'data' => 'oeoeoeoeoeoeooeoee',
        ]));
        $response->headers->set('Content-Type', 'application/json');
    }
}
