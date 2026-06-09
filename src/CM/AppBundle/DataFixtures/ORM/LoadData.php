<?php

namespace CM\AppBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use FOS\UserBundle\Model\UserManagerInterface;

class LoadData extends AbstractFixture implements OrderedFixtureInterface, ORMFixtureInterface
{
    private $userManager;

    public function __construct(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }
	
	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager)
	{
		//create users
		$user1 = $this->userManager->createUser();
		$user1->setUsername('Rex');
		$user1->setPlainPassword('pass');
		$user1->setRegistered(true);	
		$user1->setEmail('me@here.com');
		$user1->setLastActiveTime(new \DateTime());
		$user1->setEnabled(true);
        $user1->setRoles(array('ROLE_ADMIN'));
        $this->userManager->updateUser($user1, true);
		
		$user2 = $this->userManager->createUser();
		$user2->setUsername('Rex2');
		$user2->setPlainPassword('pass');
		$user2->setRegistered(true);	
		$user2->setEmail('me@here2.com');
		$user2->setLastActiveTime(new \DateTime());
		$user2->setEnabled(true);
		$user2->setChatty(false);
        $this->userManager->updateUser($user2, true);
	}

    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 1;
    }
}
