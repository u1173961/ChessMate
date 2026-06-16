<?php

namespace CM\AppBundle\Tests\Helpers\Validation;

use CM\AppBundle\Helpers\Validation\QueenValidator;

class QueenValidatorTest extends \PHPUnit\Framework\TestCase
{
    private $helper;
    private $game;
    private $board;

    public function setUp(): void
    {
        $this->helper = new QueenValidator();
        $this->game = $this->getMockBuilder('CM\AppBundle\Entity\Game')
                            ->disableOriginalConstructor()->getMock();
    }

    public function testMoves()
    {
        $this->board = $this->getBoard();

        $this->helper->setGlobals($this->game, $this->board);
        //valid moves
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,3), 'to' => array(0,4))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,3), 'to' => array(3,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,3), 'to' => array(3,6))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(4,1), 'to' => array(6,1))));
        //invalid moves
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(0,3), 'to' => array(1,5))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(4,1), 'to' => array(4,6))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(0,3), 'to' => array(6,5))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(4,1), 'to' => array(2,6))));
    }

    private function getBoard()
    {
        return array(
            array('R','N','B','Q','K','B',false,'R'),
            array(false, false, false, false, false, false, false, false),
            array(false, false, false, false, false, false, false, 'N'),
            array(false, 'P', false, false, false, 'P', false, false),
            array(false, 'q', false, 'p', false, false, false, false),
            array(false, false, false, false, false, false, false, 'p'),
            array(false, false, false, false, false, false, false, false),
            array('r','n','b',false,'k','b','n','r')
        );
    }

    public function tearDown(): void
    {
        unset($this->board);
        unset($this->game);
        unset($this->helper);
    }
}
