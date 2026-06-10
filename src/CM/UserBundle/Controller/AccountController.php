<?php

namespace CM\UserBundle\Controller;

use CM\UserBundle\Entity\User;
use CM\UserBundle\Form\ProfileType;
use CM\UserBundle\Form\RegistrationType;
use CM\UserBundle\Form\ResetPasswordRequestType;
use CM\UserBundle\Form\ResetPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function registerAction(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('cm_start');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->addUniqueFieldErrors($form, $user);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRegistered(true);
            $user->setEnabled(true);
            $user->setSalt($this->generateSalt());
            $user->setPassword($this->passwordHasher->hashPassword($user, (string) $user->getPlainPassword()));
            $user->eraseCredentials();

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Your account has been created. You can log in now.');

            return $this->redirectToRoute('fos_user_security_login');
        }

        return $this->render('@CMUser/Account/register.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function editProfileAction(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || !$user->getRegistered()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->addUniqueFieldErrors($form, $user, $user);
            $this->addOptionalPasswordError($form, (string) $user->getPlainPassword());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ('' !== trim((string) $user->getPlainPassword())) {
                $user->setSalt($this->generateSalt());
                $user->setPassword($this->passwordHasher->hashPassword($user, (string) $user->getPlainPassword()));
            }

            $user->eraseCredentials();
            $this->entityManager->flush();
            $this->addFlash('success', 'Your profile has been updated.');

            return $this->redirectToRoute('cm_user_profile_edit');
        }

        return $this->render('@CMUser/Account/profile.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function requestPasswordResetAction(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('cm_start');
        }

        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        $resetUser = null;
        $resetUrl = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $identifier = mb_strtolower(trim((string) $form->get('identifier')->getData()));
            $resetUser = $this->entityManager->getRepository(User::class)->findOneBy(array(
                'usernameCanonical' => $identifier,
            ));

            if (!$resetUser instanceof User) {
                $resetUser = $this->entityManager->getRepository(User::class)->findOneBy(array(
                    'emailCanonical' => $identifier,
                ));
            }

            if ($resetUser instanceof User && $resetUser->getRegistered()) {
                $resetUser->setConfirmationToken(bin2hex(random_bytes(32)));
                $resetUser->setPasswordRequestedAt(new \DateTime());
                $this->entityManager->flush();

                $resetUrl = $this->generateUrl('cm_user_resetting_reset', array(
                    'token' => $resetUser->getConfirmationToken(),
                ));
            }
        }

        return $this->render('@CMUser/Account/reset_request.html.twig', array(
            'form' => $form->createView(),
            'reset_user' => $resetUser,
            'reset_url' => $resetUrl,
        ));
    }

    public function resetPasswordAction(Request $request, string $token): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('cm_start');
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(array(
            'confirmationToken' => $token,
        ));

        if (!$user instanceof User || !$user->getRegistered()) {
            throw $this->createNotFoundException('Invalid password reset token.');
        }

        $requestedAt = $user->getPasswordRequestedAt();
        if (!$requestedAt instanceof \DateTimeInterface || $requestedAt < new \DateTime('-2 hours')) {
            $user->setConfirmationToken(null);
            $user->setPasswordRequestedAt(null);
            $this->entityManager->flush();
            $this->addFlash('error', 'That password reset link has expired. Please request a new one.');

            return $this->redirectToRoute('cm_user_resetting_request');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPlainPassword((string) $form->get('plainPassword')->getData());
            $user->setSalt($this->generateSalt());
            $user->setPassword($this->passwordHasher->hashPassword($user, (string) $user->getPlainPassword()));
            $user->setConfirmationToken(null);
            $user->setPasswordRequestedAt(null);
            $user->eraseCredentials();
            $this->entityManager->flush();

            $this->addFlash('success', 'Your password has been reset. You can log in now.');

            return $this->redirectToRoute('fos_user_security_login');
        }

        return $this->render('@CMUser/Account/reset.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    private function generateSalt(): string
    {
        return rtrim(str_replace('+', '.', base64_encode(random_bytes(32))), '=');
    }

    private function addUniqueFieldErrors(FormInterface $form, User $user, ?User $ignoredUser = null): void
    {
        $repository = $this->entityManager->getRepository(User::class);
        $existingUsername = $repository->findOneBy(array(
            'usernameCanonical' => $user->getUsernameCanonical(),
        ));
        $existingEmail = $repository->findOneBy(array(
            'emailCanonical' => $user->getEmailCanonical(),
        ));

        if ($existingUsername instanceof User && $existingUsername->getId() !== $ignoredUser?->getId()) {
            $form->get('username')->addError(new FormError('That username is already in use.'));
        }

        if ($existingEmail instanceof User && $existingEmail->getId() !== $ignoredUser?->getId()) {
            $form->get('email')->addError(new FormError('That email address is already in use.'));
        }
    }

    private function addOptionalPasswordError(FormInterface $form, string $plainPassword): void
    {
        $trimmedPassword = trim($plainPassword);

        if ('' !== $trimmedPassword && mb_strlen($trimmedPassword) < 4) {
            $form->get('plainPassword')->addError(new FormError('Your new password must be at least 4 characters long.'));
        }
    }
}
