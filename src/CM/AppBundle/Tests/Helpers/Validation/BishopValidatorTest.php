<?php

namespace CM\AppBundle\Tests\Helpers\Validation;

use CM\AppBundle\Helpers\Validation\BishopValidator;

class BishopValidatorTest extends \PHPUnit\Framework\TestCase
{
    private $helper;
    private $game;
    private $board;

    public function setUp(): void
    {
        $this->helper = new BishopValidator();
        $this->game = $this->getMockBuilder('CM\AppBundle\Entity\Game')
                            ->disableOriginalConstructor()->getMock();
    }

    public function testMoves()
    {
        $this->board = $this->getBoard();

        $this->helper->setGlobals($this->game, $this->board);
        //valid moves
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,2), 'to' => array(2,0))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,2), 'to' => array(2,4))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,5), 'to' => array(3,2))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,5), 'to' => array(1,6))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(7,2), 'to' => array(4,5))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(7,5), 'to' => array(4,2))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(7,5), 'to' => array(5,7))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(7,2), 'to' => array(2,7))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(7,5), 'to' => array(3,1))));
        $this->assertEquals(true, $this->helper->validatePiece(array('from' => array(0,5), 'to' => array(4,1))));
        //invalid moves
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(0,2), 'to' => array(0,3))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(0,5), 'to' => array(2,4))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(7,2), 'to' => array(7,4))));
        $this->assertEquals(false, $this->helper->validatePiece(array('from' => array(7,5), 'to' => array(4,1))));
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
