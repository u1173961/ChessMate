<?php

namespace CM\AppBundle\Tests\Helpers\Validation;

use CM\AppBundle\Helpers\Validation\RookValidator;

class RookValidatorTest extends \PHPUnit\Framework\TestCase
{
    private $helper;
    private $game;
    private $board;

    public function setUp(): void
    {
        $this->helper = new RookValidator();
        $this->game = $this->getMockBuilder('CM\AppBundle\Entity\Game')
                            ->disableOriginalConstructor()->getMock();
    }

    public function testMoves()
    {
        $this->board = $this->getBoard();

        $this->helper->setGlobals($this->game, $this->board);
        //valid moves
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,0), 'to' => array(4,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,0), 'to' => array(6,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(1,4), 'to' => array(1,2))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(1,4), 'to' => array(1,7))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(1,4), 'to' => array(1,5))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(7,0), 'to' => array(5,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(7,0), 'to' => array(2,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(6,7), 'to' => array(6,4))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(6,7), 'to' => array(6,0))));
        //invalid moves
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(0,0), 'to' => array(2,2))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(0,0), 'to' => array(5,3))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(1,4), 'to' => array(7,5))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(1,4), 'to' => array(2,5))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(7,0), 'to' => array(7,3))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(7,0), 'to' => array(0,2))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(6,7), 'to' => array(5,2))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(6,7), 'to' => array(4,2))));
    }

    private function getBoard()
    {
        return array(
            array('R','N','B','Q','K','B','N',false),
            array(false, false, false, false, 'R', false, false, false),
            array(false, false, false, false, false, false, false, false),
            array(false, 'P', false, false, false, 'P', false, false),
            array(false, 'q', false, 'p', false, false, false, false),
            array(false, false, false, false, false, false, false, 'p'),
            array(false, false, false, false, false, false, false, 'r'),
            array('r','n','b',false,'k','b','n',false)
        );
    }

    public function tearDown(): void
    {
        unset($this->board);
        unset($this->game);
        unset($this->helper);
    }
}
