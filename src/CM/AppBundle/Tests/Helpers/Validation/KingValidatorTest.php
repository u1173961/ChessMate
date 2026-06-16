<?php

namespace CM\AppBundle\Tests\Helpers\Validation;

use CM\AppBundle\Helpers\Validation\KingValidator;

class KingValidatorTest extends \PHPUnit\Framework\TestCase
{
    private $helper;
    private $game;
    private $board;

    public function setUp(): void
    {
        $this->helper = new KingValidator();
        $this->game = $this->getMockBuilder('CM\AppBundle\Entity\Game')
                            ->disableOriginalConstructor()->getMock();
        $board = $this->getMockBuilder('CM\AppBundle\Entity\Board')
                            ->disableOriginalConstructor()->getMock();
        $board->expects($this->any())
            ->method('getPlayerCastling')
            ->will($this->returnValue(array()));
        $this->game->expects($this->any())
            ->method('getBoard')
            ->will($this->returnValue($board));
    }

    public function testMoves()
    {
        $this->board = $this->getBoard();

        $this->helper->setGlobals($this->game, $this->board);
        //valid moves
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'K', 'from' => array(0,4), 'to' => array(1,4))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'K', 'from' => array(0,4), 'to' => array(1,3))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'K', 'from' => array(0,4), 'to' => array(1,5))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'k', 'from' => array(7,4), 'to' => array(7,3))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'k', 'from' => array(7,4), 'to' => array(6,3))));
        $this->assertEquals(true, $this->helper->validatePiece(array('piece' => 'k', 'from' => array(7,4), 'to' => array(6,5))));
        //invalid moves
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'K', 'from' => array(0,4), 'to' => array(2,4))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'K', 'from' => array(0,4), 'to' => array(2,3))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'K', 'from' => array(0,4), 'to' => array(2,5))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'k', 'from' => array(7,4), 'to' => array(7,2))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'k', 'from' => array(7,4), 'to' => array(2,5))));
        $this->assertEquals(false, $this->helper->validatePiece(array('piece' => 'k', 'from' => array(7,4), 'to' => array(5,4))));
    }

    private function getBoard()
    {
        return array(
            array('R','N','B','Q','K','B','N',false),
            array('P', false, false, false, false, false, false, false),
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
