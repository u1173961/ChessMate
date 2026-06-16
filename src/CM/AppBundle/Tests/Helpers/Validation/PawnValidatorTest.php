<?php

namespace CM\AppBundle\Tests\Helpers\Validation;

use CM\AppBundle\Helpers\Validation\PawnValidator;

class PawnValidatorTest extends \PHPUnit\Framework\TestCase
{
    private $helper;
    private $game;
    private $board;

    public function setUp(): void
    {
        $this->helper = new PawnValidator();
        $this->game = $this->getMockBuilder('CM\AppBundle\Entity\Game')
                            ->disableOriginalConstructor()->getMock();
        $board = $this->getMockBuilder('CM\AppBundle\Entity\Board')
                            ->disableOriginalConstructor()->getMock();
        $board->expects($this->any())
            ->method('getEnPassant')
            ->will($this->returnValue(false));
        $this->game->expects($this->any())
            ->method('getBoard')
            ->will($this->returnValue($board));
    }

    public function testMoves()
    {
        $this->board = $this->getBoard();

        $this->helper->setGlobals($this->game, $this->board);
        //valid moves
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'P', 'from' => array(1,0), 'to' => array(2,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'P', 'from' => array(1,0), 'to' => array(3,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'P', 'from' => array(3,1), 'to' => array(4,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'p', 'from' => array(6,4), 'to' => array(5,4))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'p', 'from' => array(6,4), 'to' => array(4,4))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'P', 'from' => array(3,5), 'to' => array(4,5))));
        //invalid moves
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'P', 'from' => array(1,0), 'to' => array(1,1))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'P', 'from' => array(1,0), 'to' => array(2,3))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'P', 'from' => array(3,1), 'to' => array(5,1))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'P', 'from' => array(3,1), 'to' => array(4,2))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'p', 'from' => array(6,4), 'to' => array(6,2))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'p', 'from' => array(6,4), 'to' => array(3,4))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'p', 'from' => array(6,6), 'to' => array(4,4))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'p', 'from' => array(6,6), 'to' => array(7,7))));
    }

    private function getBoard()
    {
        return array(
            array('R','N','B','Q','K','B','N',false),
            array('P', false, false, 'P', 'R', false, false, false),
            array(false, false, 'P', false, false, false, false, false),
            array(false, 'P', false, false, false, 'P', false, false),
            array( 'q', false,false, 'p', false, false, false, false),
            array(false, false, false, false, false, false, false, 'p'),
            array('p', false, false, false, 'p', false, 'p', 'r'),
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
