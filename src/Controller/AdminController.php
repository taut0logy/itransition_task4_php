<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/', name: 'admin_users')]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAllOrderedByLastLogin();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/action', name: 'users_bulk_action', methods: ['POST'])]
    public function bulkAction(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $action = $request->request->get('action');
        $userIds = $request->request->all('users') ?? [];

        if (empty($userIds)) {
            $this->addFlash('error', 'No users selected');
            return $this->redirectToRoute('admin_users');
        }

        $users = $userRepository->findBy(['id' => $userIds]);
        $currentUser = $this->getUser();
        $affectsSelf = false;

        if ($currentUser instanceof User && in_array($currentUser->getId(), $userIds)) {
            $affectsSelf = true;
        }

        foreach ($users as $user) {
            match ($action) {
                'block' => $user->setStatus('blocked'),
                'unblock' => $user->setStatus($user->isEmailVerified() ? 'active' : 'unverified'),
                'delete' => $entityManager->remove($user),
                default => null
            };
        }

        $entityManager->flush();

        // If current user blocked/deleted themselves, logout and redirect to login
        if ($affectsSelf && ($action === 'block' || $action === 'delete')) {
            $security->logout(false);
            $this->addFlash('info', 'You have ' . ($action === 'delete' ? 'deleted' : 'blocked') . ' your own account');
            return $this->redirectToRoute('login');
        }

        $this->addFlash('success', ucfirst($action) . ' action completed successfully');
        return $this->redirectToRoute('admin_users');
    }
}
