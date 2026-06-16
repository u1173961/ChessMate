<?php

namespace CM\UserBundle\Tests\Entity;

use CM\UserBundle\Entity\User;

class UserTest extends \PHPUnit\Framework\TestCase
{
    private $user;

    public function setUp(): void
    {
        //create new user with rating of 1500 & RD of 200
        $this->user = new User();
        $this->user->setDeviation(200);
    }

    /**
     * Test updating ratings & deviation using the example provided at http://www.glicko.net/glicko/glicko.pdf
     */
    public function testUpdateRatingsAndRD()
    {
        //3 matches over ratings period
        $matches = array(
                array('opRating' => 1400, 'opRD' => 30, 'result' => 1),
                array('opRating' => 1550, 'opRD' => 100, 'result' => 0),
                array('opRating' => 1700, 'opRD' => 300, 'result' => 0)
        );

        $this->user->updateRating($matches);

        $this->assertEquals(1464, $this->user->getRating());
        $this->assertEquals(151.4, $this->user->getDeviation());
    }

    public function tearDown(): void
    {
        unset($this->user);
    }
}
