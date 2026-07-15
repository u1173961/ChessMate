<?php

namespace CM\AppBundle\Tests\Helpers;

use CM\AppBundle\Entity\Board;
use CM\AppBundle\Helpers\GameOverHelper;

class GameOverHelperTest extends \PHPUnit\Framework\TestCase
{
    private $helper;
    private $game;

    public function setUp(): void
    {
        $this->helper = new GameOverHelper();
        $this->game = $this->getMockBuilder('CM\AppBundle\Entity\Game')
                            ->disableOriginalConstructor()->getMock();
        $this->game->method('getBoard')->willReturn(new Board());
    }

    public function testCheckmate1()
    {
        $checkmate = array(
            array('R','N','B',false,'K',false,'N','R'),
            array('P','P','P','P',false,'P','P','P'),
            array(false, false, false, false, false, false, false, false),
            array(false, false, 'B', false, 'P', false, false, false),
            array(false, 'p', false, false, 'p', false, false, false),
            array(false, false, 'n', false, false, false, false, false),
            array('p',false,'p','p',false,'Q','p','p'),
            array('r',false,'b','q','k','b','n','r')
        );

        $this->helper->setGlobals($this->game, $checkmate);

        $this->assertEquals('Checkmate', $this->helper->getGameOver('w'));
    }

    public function testCheckmate2()
    {
        $checkmate = array(
            array('R',false,'B','Q',false,'R','K',false),
            array('P','P','P','P',false,false,false,'q'),
            array(false, false, 'N', false, 'P', false, 'P', false),
            array(false, false, 'B', false, false, 'P', 'n', false),
            array(false, false, false, false, 'p', false, 'N', false),
            array(false, false, false, 'p', false, false, false, false),
            array('p','p','p',false,false,'p','p','p'),
            array('r',false,'b',false,'k','b',false,'r')
        );

        $this->helper->setGlobals($this->game, $checkmate);

        $this->assertEquals('Checkmate', $this->helper->getGameOver('b'));
    }

    public function testBlockableCheckmate()
    {
        $blockable = array(
            array('R','N','B',false,'K',false,'N','R'),
            array('P','P','P','P',false,'P','P','P'),
            array(false, false, false, false, false, false, false, false),
            array(false, false, 'B', false, 'P', false, false, false),
            array(false, 'p', 'Q', false, 'p', false, false, false),
            array(false, false, 'n', false, false, false, 'b', false),
            array('p',false,'p','p',false,false,'p','p'),
            array('r',false,'b',false,'q','k','n','r')
        );

        $this->helper->setGlobals($this->game, $blockable);

        $this->assertFalse($this->helper->getGameOver('w'));
    }

    public function testStalemate()
    {
        $stalemate = array(
            array(false, false, false, false, false, false, false, false),
            array(false, 'r', false, false, false, false, false, false),
            array(false, false, false, false, 'k', false, false, false),
            array(false, false, false, false, false, false, false, false),
            array(false, false, false, false, false, false, false, false),
            array(false, false, false, false, false, false, 'r', false),
            array(false, false, 'K', false, false, false, false, false),
            array(false, false, false, false, 'q', false, false, false)
        );

        $this->helper->setGlobals($this->game, $stalemate);

        $this->assertEquals('Stalemate', $this->helper->getGameOver('b'));
    }

    public function testDrawn()
    {
        $drawn = array(
            array(false, false, false, false, false, false, false, false),
            array(false, false, false, false, false, false, false, false),
            array(false, false, false, false, 'k', false, false, false),
            array(false, false, false, false, false, false, false, false),
            array(false, false, false, false, false, false, false, false),
            array(false, false, false, false, false, false, false, false),
            array(false, false, 'K', false, false, false, false, false),
            array(false, false, false, false, false, false, false, false)
        );

        $this->helper->setGlobals($this->game, $drawn);

        $this->assertEquals('Drawn', $this->helper->getGameOver('b'));
    }

    public function tearDown(): void
    {
        unset($this->game);
        unset($this->helper);
    }
}
