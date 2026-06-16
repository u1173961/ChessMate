<?php

namespace CM\AppBundle\Tests\Helpers;

use CM\AppBundle\Helpers\FENHelper;

class FENHelperTest extends \PHPUnit\Framework\TestCase
{
    private $helper;
    private $board;

    public function setUp(): void
    {
        $this->helper = new FENHelper();
        $this->board = $this->getBoard();
    }

    public function testGetFENFromBoard()
    {
        $expected = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR';
        $actual = $this->helper->getFENFromBoard($this->board);
        $this->assertEquals($expected, $actual);
    }

    public function testGetBoardFromFEN()
    {
        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR';
        $expected = $this->board;
        $actual = $this->helper->getBoardFromFEN($fen);
        $this->assertEquals($expected, $actual);
    }

    public function testGetFENIndex()
    {
        //test index translation
        $this->assertEquals(0, $this->helper->getFenIndex('pppppppp', 0));
        $this->assertEquals(1, $this->helper->getFenIndex('pppppppp', 1));
        $this->assertEquals(2, $this->helper->getFenIndex('pppppppp', 2));
        $this->assertEquals(3, $this->helper->getFenIndex('pppppppp', 3));
        $this->assertEquals(4, $this->helper->getFenIndex('pppppppp', 4));
        $this->assertEquals(5, $this->helper->getFenIndex('pppppppp', 5));
        $this->assertEquals(6, $this->helper->getFenIndex('pppppppp', 6));
        $this->assertEquals(7, $this->helper->getFenIndex('pppppppp', 7));
        $this->assertEquals(4, $this->helper->getFenIndex('pp4pp', 7));
        $this->assertEquals(3, $this->helper->getFenIndex('1p4pp', 6));
        $this->assertEquals(4, $this->helper->getFenIndex('1p3p2', 7));
        $this->assertEquals(0, $this->helper->getFenIndex('1p3p2', 0));
        $this->assertEquals(1, $this->helper->getFenIndex('1p3p2', 1));
        $this->assertEquals(2, $this->helper->getFenIndex('1p3p2', 2));
        $this->assertEquals(2, $this->helper->getFenIndex('1p3p2', 3));
        $this->assertEquals(2, $this->helper->getFenIndex('1p3p2', 4));
        $this->assertEquals(3, $this->helper->getFenIndex('1p3p2', 5));
        $this->assertEquals(4, $this->helper->getFenIndex('1p3p2', 6));
        $this->assertEquals(4, $this->helper->getFenIndex('1p3p2', 7));
    }

    public function testGetPieceFromFEN()
    {
        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR';
        $this->assertEquals('p', $this->helper->getPieceFromFEN($fen, 6, 5));
        $this->assertEquals('Q', $this->helper->getPieceFromFEN($fen, 0, 3));
        $this->assertEquals('k', $this->helper->getPieceFromFEN($fen, 7, 4));
    }

    public function tearDown(): void
    {
        unset($this->helper);
        unset($this->board);
    }

    private function getBoard()
    {
        return array(
                array('R','N','B','Q','K','B','N','R'),
                array('P','P','P','P','P','P','P','P'),
                array(false, false, false, false, false, false, false, false),
                array(false, false, false, false, false, false, false, false),
                array(false, false, false, false, false, false, false, false),
                array(false, false, false, false, false, false, false, false),
                array('p','p','p','p','p','p','p','p'),
                array('r','n','b','q','k','b','n','r')
        );
    }
}
