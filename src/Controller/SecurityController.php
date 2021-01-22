<?php

namespace App\Controller;

use App\Security\LoginFormAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

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

}
