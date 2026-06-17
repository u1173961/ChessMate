<?php

namespace CM\AppBundle\Helpers\Validation;

use CM\AppBundle\Entity\Game;
use Exception;

/**
 * Move validator
 */
abstract class ValidationHelper
{
    protected $game;
    protected $board;
    protected $enPassant = null;
    protected $castling = null;
    protected $pieceSwapped = false;
    protected $checkThreat = null;

    /**
     * Validate chess move
     *
     * @param array $move [from, to, pieceType, pieceColour]
     * @param Game $game The game
     *
     * @return array
     */
    public function validateMove(array $move, Game $game, array $board): array
    {
        $this->setGlobals($game, $board);
        $validResponse = ['valid' => true];
        $invalidResponse = ['valid' => false];
        //check piece matches origin
        //and target square is not occupied by own piece
        $colour = $this->getPieceColour($move['piece']);
        if (
            $this->board[$move['from'][0]][$move['from'][1]] != $move['piece']
            || (
                $this->board[$move['to'][0]][$move['to'][1]]
                && $this->getPieceColour($this->board[$move['to'][0]][$move['to'][1]]) == $colour
            )
        ) {
            return $invalidResponse;
        }
        //validate piece
        $valid = $this->validatePiece($move);
        if ($valid) {
            $this->applyMoveToBoard($move);
            //update En passant
            $this->setEnPassant($move['piece'], $move['from'][0], $move['from'][1], $move['to'][0]);
            //check changes
            if ($move['enPassant'] != $this->enPassant) {
                return $invalidResponse;
            }
            //update castling availability
            if (in_array($move['piece'], ['k', 'K', 'r', 'R'])) {
                $this->updateCastling($move['piece'], $colour, $move['from'][0], $move['from'][1]);
            }
            //get opponent colour
            $opColour = $this->getOpponentColour($colour);
            //if in check, invalidate move
            if ($this->inCheck($opColour, $this->getKingSquare($colour))) {
                return $invalidResponse;
            }
            //check changes to castling
            if ($move['castling'] != $this->castling) {
                return $invalidResponse;
            }
            //check changes to board
            if ($move['newBoard'] != $this->board) {
                return $invalidResponse;
            }
            //add/remove En passant
            $this->game->getBoard()->setEnPassant($this->enPassant);
            //flag pawn as swapped/reset
            $this->game->getBoard()->setPawnSwapped($this->pieceSwapped);
            return $validResponse;
        }

        return $invalidResponse;
    }

    /**
     * Overridden function
     * @return boolean
     */
    public function validatePiece(array $move): bool
    {
        return false;
    }

    public function setGlobals($game, $board)
    {
        $this->game = $game;
        $this->board = $board;
        $this->enPassant = null;
        $this->castling = [
            'w' => $game->getBoard()->getPlayerCastling(0),
            'b' => $game->getBoard()->getPlayerCastling(1),
        ];
        $this->pieceSwapped = false;
        $this->checkThreat = null;
    }

    /**
     * Apply the validated move to the internal board before state checks.
     * @param array $move
     * @return void
     */
    protected function applyMoveToBoard(array $move): void
    {
        $from = $move['from'];
        $to = $move['to'];
        $piece = $move['piece'];
        $colour = $this->getPieceColour($piece);

        if (
            strtoupper($piece) == 'P'
            && (($colour == 'w' && $to[0] == 7) || ($colour == 'b' && $to[0] == 0))
        ) {
            $this->board[$to[0]][$to[1]] = $move['newPiece'];
            $this->board[$from[0]][$from[1]] = false;
            $this->pieceSwapped = true;
            return;
        }

        $this->updateAbstractBoard($from, $to);
    }

    /**
     * Update castling availability for player.
     * @param string $moved
     * @param string $colour
     * @param int $fRow
     * @param int $fCol
     * @return void
     */
    protected function updateCastling(string $moved, string $colour, int $fRow, int $fCol): void
    {
        if ($pCastling = $this->castling[$colour]) {
            if ($moved == 'k' || $moved == 'K') {
                $pCastling = '';
            } elseif (
                ($fCol === 0 || $fCol === 7)
                && (
                    ($moved == 'r' && $fRow === 7)
                    || ($moved == 'R' && $fRow === 0)
                )
            ) {
                if (\strlen($pCastling) > 1) {
                    //castle possible on both sides
                    $pCastling = $pCastling[$fCol === 0 ? 0 : 1];
                } elseif ($pCastling == 'K' || $pCastling == 'k') {
                    //castle king-side possible
                    if ($fCol === 7) {
                        //castle no longer possible
                        $pCastling = '';
                    }
                } elseif ($fCol === 0) {
                    //queen-side castle no longer possible
                    $pCastling = '';
                }
            }
            $this->castling[$colour] = $pCastling;
        }
    }

    /**
     * Get piece colour
     * @param string $piece
     * @return string
     */
    protected function getPieceColour(string $piece)
    {
        if (strtoupper($piece) === $piece) {
            return 'w';
        }
        return 'b';
    }

    /**
     * Get player's index
     * @param string $colour
     * @return int
     */
    protected function getPlayerIndex(string $colour)
    {
        if ($colour === 'w') {
            return 0;
        }
        return 1;
    }

    /**
     * Get player's piece (uppercase for white)
     * @param string $colour
     * @param string $piece
     * @return string
     */
    protected function getPlayerPiece(string $colour, string $piece)
    {
        if ($colour == 'w') {
            return strtoupper($piece);
        }
        return $piece;
    }

    /**
     * Set/unset En passant availability
     * @param string $moved
     * @param int $fRow
     * @param int $fCol
     * @param int $tRow
     */
    protected function setEnPassant(string $moved, int $fRow, int $fCol, int $tRow)
    {
        if ($moved == 'p' && $fRow == 6 && $tRow == 4) {
            $this->enPassant = array(5, $fCol);
        } elseif ($moved == 'P' && $fRow == 1 && $tRow == 3) {
            $this->enPassant = array(2, $fCol);
        } else {
            $this->enPassant = null;
        }
    }

    /**
     * Get array indices for given colour king's square
     * @param string $colour w/b
     * @return array [y,x]
     */
    protected function getKingSquare(string $colour): array
    {
        $king = $this->getPlayerPiece($colour, 'k');
        //get king's position
        for ($row = 0; $row < 8; $row++) {
            $col = array_search($king, $this->board[$row]);
            if ($col !== false) {
                return [$row, $col];
            }
        }
        throw new Exception('No king on board!');
    }

    /**
     * Check if king is in check
     * @param string $opColour The threatening player
     * @param array $kingSquare The threatened square
     */
    protected function inCheck(string $opColour, array $kingSquare)
    {
        //check in check
        return (
            $this->inCheckByPawn($opColour, $kingSquare)
            || $this->inCheckByKnight($opColour, $kingSquare)
            || $this->inCheckOnXAxis($opColour, $kingSquare)
            || $this->inCheckOnYAxis($opColour, $kingSquare)
            || $this->inCheckOnDiagonal($opColour, $kingSquare)
        );
    }

    /**
     * Check if in check on diagonal
     * @param string $opColour
     * @param array $kingSquare
     * @return bool
     */
    protected function inCheckOnDiagonal(string $opColour, array $kingSquare): bool
    {
        $row = $kingSquare[0];
        $col = $kingSquare[1];
        $blocks = [false,false,false,false];
        $bishop = $this->getPlayerPiece($opColour, 'b');
        $queen = $this->getPlayerPiece($opColour, 'q');
        for ($i = 1; $i < 8; $i++) {
            $threats = [
                $this->getPieceAt($row + $i, $col - $i),
                $this->getPieceAt($row + $i, $col + $i),
                $this->getPieceAt($row - $i, $col - $i),
                $this->getPieceAt($row - $i, $col + $i)

            ];
            if (!$blocks[0] && ($threats[0] == $bishop || $threats[0] == $queen)) {
                $this->checkThreat = array($row + $i, $col - $i);
                return true;
            }
            if (!$blocks[1] && ($threats[1] == $bishop || $threats[1] == $queen)) {
                $this->checkThreat = array($row + $i, $col + $i);
                return true;
            }
            if (!$blocks[2] && ($threats[2] == $bishop || $threats[2] == $queen)) {
                $this->checkThreat = array($row - $i, $col - $i);
                return true;
            }
            if (!$blocks[3] && ($threats[3] == $bishop || $threats[3] == $queen)) {
                $this->checkThreat = array($row - $i, $col + $i);
                return true;
            }
            //get blocking pieces
            for ($j = 0; $j < 4; $j++) {
                if (!$blocks[$j]) {
                    $blocks[$j] = $threats[$j];
                }
            }
        }
        return false;
    }

    /**
     * Check if in check on x-axis
     * @param string $opColour
     * @param array $kingSquare
     * @return bool
     */
    protected function inCheckOnXAxis(string $opColour, array $kingSquare): bool
    {
        $row = $kingSquare[0];
        $rook = $this->getPlayerPiece($opColour, 'r');
        $queen = $this->getPlayerPiece($opColour, 'q');
        //radiate out (for checkmates)
        for ($col = $kingSquare[1] - 1; $col >= 0; $col--) {
            if ($this->board[$row][$col] == $rook || $this->board[$row][$col] == $queen) {
                if (!$this->xAxisBlocked($kingSquare[1], $col, $row)) {
                    $this->checkThreat = array($row, $col);
                    return true;
                }
            }
        }
        for ($col = $kingSquare[1] + 1; $col < 8; $col++) {
            if ($this->board[$row][$col] == $rook || $this->board[$row][$col] == $queen) {
                if (!$this->xAxisBlocked($kingSquare[1], $col, $row)) {
                    $this->checkThreat = array($row, $col);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if in check on y-axis
     * @param string $opColour
     * @param array $kingSquare
     * @return bool
     */
    protected function inCheckOnYAxis(string $opColour, array $kingSquare): bool
    {
        $col = $kingSquare[1];
        $rook = $this->getPlayerPiece($opColour, 'r');
        $queen = $this->getPlayerPiece($opColour, 'q');
        //radiate out
        for ($row = $kingSquare[0] - 1; $row >= 0; $row--) {
            if ($this->board[$row][$col] == $rook || $this->board[$row][$col] == $queen) {
                if (!$this->yAxisBlocked($kingSquare[0], $row, $col)) {
                    $this->checkThreat = array($row, $col);
                    return true;
                }
            }
        }
        for ($row = $kingSquare[0] + 1; $row < 8; $row++) {
            if ($this->board[$row][$col] == $rook || $this->board[$row][$col] == $queen) {
                if (!$this->yAxisBlocked($kingSquare[0], $row, $col)) {
                    $this->checkThreat = array($row, $col);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if king is in check by knight
     * @param string $opColour
     * @param array $kingSquare
     * @return bool
     */
    protected function inCheckByKnight(string $opColour, array $kingSquare): bool
    {
        $x = $kingSquare[1];
        $y = $kingSquare[0];
        $knight = $this->getPlayerPiece($opColour, 'n');
        if ($this->pieceAt($y + 2, $x - 1, $knight)) {
            $this->checkThreat = array($y + 2, $x - 1);
            return true;
        }
        if ($this->pieceAt($y + 2, $x + 1, $knight)) {
            $this->checkThreat = array($y + 2, $x + 1);
            return true;
        }
        if ($this->pieceAt($y + 1, $x - 2, $knight)) {
            $this->checkThreat = array($y + 1, $x - 2);
            return true;
        }
        if ($this->pieceAt($y + 1, $x + 2, $knight)) {
            $this->checkThreat = array($y + 1, $x + 2);
            return true;
        }
        if ($this->pieceAt($y - 1, $x - 2, $knight)) {
            $this->checkThreat = array($y - 1, $x - 2);
            return true;
        }
        if ($this->pieceAt($y - 1, $x + 2, $knight)) {
            $this->checkThreat = array($y - 1, $x + 2);
            return true;
        }
        if ($this->pieceAt($y - 2, $x - 1, $knight)) {
            $this->checkThreat = array($y - 2, $x - 1);
            return true;
        }
        if ($this->pieceAt($y - 2, $x + 1, $knight)) {
            $this->checkThreat = array($y - 2, $x + 1);
            return true;
        }
        return false;
    }

    /**
     * Check if king is in check by pawn
     * @param string $opColour
     * @param array $kingSquare
     * @return bool
     */
    protected function inCheckByPawn(string $opColour, array $kingSquare): bool
    {
        $dir = 1;
        $pawn = $this->getPlayerPiece($opColour, 'p');
        if ($opColour == 'w') {
            $dir = -1;
        }
        if ($this->pieceAt($kingSquare[0] + $dir, $kingSquare[1] - 1, $pawn)) {
            $this->checkThreat = array($kingSquare[0] + $dir, $kingSquare[1] - 1);
            return true;
        }
        if ($this->pieceAt($kingSquare[0] + $dir, $kingSquare[1] + 1, $pawn)) {
            $this->checkThreat = array($kingSquare[0] + $dir, $kingSquare[1] + 1);
            return true;
        }
        return false;
    }

    /**
     * Check given piece is at given square
     * @param int $row
     * @param int $column
     * @param string $piece
     * @return bool
     */
    protected function pieceAt(int $row, int $column, string $piece): bool
    {
        if ($row > -1 && $row < 8 && $column > -1 && $column < 8) {
            if ($this->board[$row][$column] == $piece) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get piece/false at given square
     * @param int $row
     * @param int $column
     * @return string|false
     */
    protected function getPieceAt(int $row, int $column): string|false
    {
        if ($row > -1 && $row < 8 && $column > -1 && $column < 8) {
            return $this->board[$row][$column];
        }
        return false;
    }

    /**
     * Get opponent's colour
     * @param string $colour
     * @return string
     */
    protected function getOpponentColour(string $colour): string
    {
        if ($colour == 'w') {
            $colour = 'b';
        } else {
            $colour = 'w';
        }
        return $colour;
    }

    /**
     * Update abstract board (handles taking automatically)
     * @param array from [y,x]
     * @param array to [y,x]
     * @return void
     */
    protected function updateAbstractBoard(array $from, array $to): void
    {
        $this->board[$to[0]][$to[1]] = $this->board[$from[0]][$from[1]];
        $this->board[$from[0]][$from[1]] = false;
    }

    /**
     * Check if x-axis squares are blocked
     * @param int $from x1
     * @param int $to   x2
     * @param int $row  y
     * @return bool
     */
    protected function xAxisBlocked(int $from, int $to, int $row): bool
    {
        //get x-axis direction
        $range = abs($to - $from);
        $x = ($to - $from) / $range;
        //check squares inbetween are empty
        for ($i = 1; $i < $range; $i++) {
            if ($this->board[$row][$from + ($i * $x)]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if y-axis squares are blocked
     * @param int $from     y1
     * @param int $to       y2
     * @param int $column   x
     * @return bool
     */
    protected function yAxisBlocked(int $from, int $to, int $column): bool
    {
        //get y-axis direction
        $range = abs($to - $from);
        $y = ($to - $from) / $range;
        //check squares inbetween are empty
        for ($i = 1; $i < $range; $i++) {
            if ($this->board[$from + ($i * $y)][$column]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if diagonal squares are blocked
     * @param int $fromX
     * @param int $fromY
     * @param int $toX
     * @param int $toY
     * @return bool
     */
    protected function diagonalBlocked(
        int $fromX,
        int $fromY,
        int $toX,
        int $toY
    ): bool {
        $range = abs($fromX - $toX);
        //get x-axis direction
        $xDir = ($toX - $fromX) / $range;
        //get y-axis direction
        $yDir = ($toY - $fromY) / $range;
        //check squares inbetween are empty
        for ($i = 1; $i < $range; $i++) {
            if ($this->board[$fromY + ($i * $yDir)][$fromX + ($i * $xDir)]) {
                return true;
            }
        }

        return false;
    }

    /**
     * check if target square is diagonal with source
     * @param array $from [y,x]
     * @param array $to [y,x]
     *
     * @return bool
     */
    protected function onDiagonal(array $from, array $to): bool
    {
        return abs($to[0] - $from[0]) == abs($to[1] - $from[1]);
    }

    /**
     * Check if target square is unoccupied
     * @param int $row
     * @param int $column
     * @return bool
     */
    protected function vacant(int $row, int $column): bool
    {
        return $this->board[$row][$column] === false;
    }

    /**
     * Check if target square is occupied by own piece
     * @param int $row
     * @param int $column
     * @param string $colour
     * @return bool
     */
    protected function occupiedByOwnPiece(int $row, int $column, string $colour): bool
    {
        return $row > -1 && $row < 8
            && $column > -1 && $column < 8
            && !$this->vacant($row, $column) && $this->getPieceColour($this->board[$row][$column]) == $colour;
    }

    /**
     * Check if target square is occupied by other piece
     * @param int $row
     * @param int $column
     * @param string $colour
     * @return bool
     */
    protected function occupiedByOtherPiece(int $row, int $column, string $colour): bool
    {
        return $row > -1 && $row < 8 && $column > -1 && $column < 8
            && !$this->vacant($row, $column)
            && $this->getPieceColour($this->board[$row][$column]) != $colour;
    }

    /**
     * Check for takeable piece
     * @param array $square
     * @param string $colour
     * @return bool
     */
    protected function checkTakePiece(array $square, string $colour): bool
    {
        return $this->occupiedByOtherPiece($square[0], $square[1], $colour);
    }
}
