<?php

namespace CM\AppBundle\DataFixtures\ORM;

use CM\UserBundle\Entity\User;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoadData extends AbstractFixture implements OrderedFixtureInterface, ORMFixtureInterface
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
	
	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager): void
	{
		//create users
		$user1 = new User();
		$user1->setUsername('Rex');
		$user1->setPlainPassword('pass');
		$user1->setRegistered(true);	
		$user1->setEmail('me@here.com');
		$user1->setLastActiveTime(new \DateTime());
		$user1->setEnabled(true);
        $user1->setRoles(array('ROLE_ADMIN'));
        $user1->setSalt(null);
        $user1->setPassword($this->passwordHasher->hashPassword($user1, $user1->getPlainPassword()));
        $user1->eraseCredentials();
        $manager->persist($user1);
		
		$user2 = new User();
		$user2->setUsername('Rex2');
		$user2->setPlainPassword('pass');
		$user2->setRegistered(true);	
		$user2->setEmail('me@here2.com');
		$user2->setLastActiveTime(new \DateTime());
		$user2->setEnabled(true);
		$user2->setChatty(false);
        $user2->setSalt(null);
        $user2->setPassword($this->passwordHasher->hashPassword($user2, $user2->getPlainPassword()));
        $user2->eraseCredentials();
        $manager->persist($user2);

        $manager->flush();
	}

    /**
     * {@inheritDoc}
     */
    public function getOrder(): int
    {
        return 1;
    }
}
