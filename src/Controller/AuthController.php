<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\Address;


class AuthController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_users');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $name = $request->request->get('name');
            $password = $request->request->get('password');

            // Validate
            if (empty($email) || empty($name) || empty($password)) {
                $error = 'All fields are required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address';
            } else {
                try {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setName($name);
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                    $user->setStatus('unverified');
                    $user->setVerificationToken(bin2hex(random_bytes(32)));

                    $entityManager->persist($user);
                    $entityManager->flush();

                    $verifyUrl = $this->generateUrl('verify_email', [
                        'token' => $user->getVerificationToken()
                    ], UrlGeneratorInterface::ABSOLUTE_URL);

                    $email = (new Email())
                        ->from(new Address($this->getParameter('mailer_from'), 'User Management App'))
                        ->to($user->getEmail())
                        ->subject('Verify your email address')
                        ->html("<p>Please verify your email by clicking: <a href=\"{$verifyUrl}\">Verify Email</a></p>");

                    $mailer->send($email);

                    $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');
                    return $this->redirectToRoute('login');
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'UNIQ_EMAIL')) {
                        $error = 'This email is already registered';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        }

        return $this->render('auth/register.html.twig', ['error' => $error]);
    }

    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_users');
        }

        $error = $authUtils->getLastAuthenticationError();
        $lastUsername = $authUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        throw new \LogicException('This should never be reached');
    }

    #[Route('/verify/{token}', name: 'verify_email')]
    public function verifyEmail(string $token, EntityManagerInterface $entityManager): Response
    {
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Invalid or expired verification token');
            return $this->redirectToRoute('login');
        }

        $user->setStatus('active');
        $user->setVerificationToken(null);
        $user->setEmailVerifiedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Email verified successfully! You can now log in.');
        return $this->redirectToRoute('login');
    }
}
