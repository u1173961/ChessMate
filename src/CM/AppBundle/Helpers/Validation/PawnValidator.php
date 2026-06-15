<?php

namespace CM\AppBundle\Helpers\Validation;

use CM\AppBundle\Entity\Game;
use CM\UserBundle\Entity\User;
use CM\AppBundle\Entity\Board;
/**
 * Pawn validator
 */
class PawnValidator extends ValidationHelper
{
    /**
     * Validate pawn movement
     * @param array $move
     */
    public function validatePiece(array $move): bool
    {
        $from = $move['from'];
        $to = $move['to'];
        $colour = $this->getPieceColour($move['piece']);
        $spaces = 1;
        if (($colour == 'w' && $from[0] == 1) || ($colour == 'b' && $from[0] == 6)) {
            //allow initial movement of 2 spaces
            $spaces = 2;
        }
        $valid = false;
        //allow moving forward
        $dir = $to[0] - $from[0];
        $moved = abs($dir);
        if ($this->vacant($to[0], $to[1]) && $from[1] == $to[1] && $moved <= $spaces) {
            if (($colour == 'w' && $to[0] > $from[0]) || ($colour == 'b' && $to[0] < $from[0])) {
                return true;
            }
        } else if ($this->onDiagonal($from, $to) && (($colour == 'w' && $dir == 1) || $colour == 'b' && $dir == -1))  {
            $enPassant = $this->game->getBoard()->getEnPassant();
            if ($this->checkTakePiece($to, $colour)) {
                //allow diagonal take
                $valid = true;
            }
            //check/perform En passant
            else if ($enPassant && $enPassant[0] == $to[0] && $enPassant[1] == $to[1]) {
                //take pawn
                $this->board[$from[0]][$to[1]] = false;
                return true;
            }
        }
        if ($valid) {
            //check for pawn reaching opposing end
            if (($colour == 'w' && $to[0] == 7) || ($colour == 'b' && $to[0] == 0)) {
                $this->board[$to[0]][$to[1]] = $move['newPiece'];
                $this->board[$from[0]][$from[1]] = false;
                $this->pieceSwapped = true;
            }
        }
        return $valid;
    }
}
