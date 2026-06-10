<?php

namespace CM\UserBundle\Form;

use CM\UserBundle\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, array(
                'constraints' => array(
                    new NotBlank(),
                    new Length(max: 180),
                ),
            ))
            ->add('email', EmailType::class, array(
                'constraints' => array(
                    new NotBlank(),
                    new Email(),
                    new Length(max: 180),
                ),
            ))
            ->add('plainPassword', RepeatedType::class, array(
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'first_options' => array('label' => 'Password'),
                'second_options' => array('label' => 'Repeat password'),
                'constraints' => array(
                    new NotBlank(),
                    new Length(min: 4, max: 4096),
                ),
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
            'data_class' => User::class,
        ));
    }
}
