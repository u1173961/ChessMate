<?php

namespace CM\AppBundle\Helpers;

/**
 * Manipulate FEN strings
 * and translate to/from array representation of board
 */
class FENHelper
{
    private $startFEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR';

    /**
     * Get FEN from abstract board array
     * @param array $board
     * @return string
     */
    public function getFENFromBoard(array $board)
    {
        $fen = array();
        for ($i = 7; $i > -1; $i--) {
            //get row
            $row = $board[$i];
            $fenRow = '';
            $count = 0;
            for ($j = 0; $j < 8; $j++) {
                if ($row[$j]) {
                    $entry = $row[$j];
                    if ($count != 0) {
                        $entry = $count . $entry;
                        $count = 0;
                    }
                    $fenRow = $fenRow . $entry;
                } else {
                    $count++;
                    if ($j == 7 && $count != 0) {
                        $fenRow .= $count;
                    }
                }
            }
            $fen[7 - $i] = $fenRow;
        }
        return implode('/', $fen);
    }

    /**
     * Get abstract board array from FEN
     * @param string $fen
     * @return array
     */
    public function getBoardFromFEN($fen)
    {
        if ($fen == $this->startFEN) {
            return $this->getDefaultBoard();
        }
        $split = explode('/', $fen);
        $board = array(array(),array(),array(),array(),array(),array(),array(),array());
        for ($i = 7; $i > -1; $i--) {
            $row = $split[$i];
            if (strlen($row) == 1) {
                //empty row
                $board[7 - $i] = array(false, false, false, false, false, false, false, false);
            } else {
                $offset = 0;
                for ($j = 0; $j < strlen($row); $j++) {
                    $entry = $row[$j];
                    if (is_numeric($entry)) {
                        for ($k = 0; $k < $entry; $k++) {
                            $board[7 - $i][$j + $offset] = false;
                            $offset++;
                        }
                        $offset--;
                    } else {
                        $board[7 - $i][$j + $offset] = $entry;
                    }
                }
            }
        }
        return $board;
    }

    /**
     * Translate column index to FEN position
     * @param string $row FEN
     * @param int $col array index
     * @return int
     */
    public function getFenIndex(string $row, int $col)
    {
        $count = 0;
        for ($i = 0; $i < strlen($row); $i++) {
            if ($count == $col) {
                return $i;
            } elseif (is_numeric($row[$i])) {
                $count += $row[$i];
                if ($count > $col) {
                    break;
                }
            } else {
                $count++;
            }
        }
        return $i;
    }

    /**
     * Get piece from FEN
     * @param string fen
     * @param int row
     * @param int col
     * @return char
     */
    public function getPieceFromFEN($fen, $row, $col)
    {
        $split = explode('/', $fen);
        $fRow = $split[7 - $row];
        $fCol = $this->getFenIndex($fRow, $col);
        return $fRow[$fCol];
    }

    private function getDefaultBoard()
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
