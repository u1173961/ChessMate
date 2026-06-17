<?php

namespace CM\AppBundle\Helpers\Validation;

use CM\AppBundle\Entity\Game;
use CM\UserBundle\Entity\User;
use CM\AppBundle\Entity\Board;

/**
 * King validator
 */
class KingValidator extends ValidationHelper
{
    /**
     * Validate king movement
     * @param array $move
     * @return bool
     */
    public function validatePiece(array $move): bool
    {
        $from = $move['from'];
        $to = $move['to'];
        $colour = $this->getPieceColour($move['piece']);
        if (abs($to[1] - $from[1]) <= 1 && abs($to[0] - $from[0]) <= 1) {
            return true;
        }

        //handle castling
        $castling = $this->game->getBoard()->getPlayerCastling($this->getPlayerIndex($colour));
        $opColour = $this->getOpponentColour($colour);
        if (!$castling || $to[0] !== $from[0] || $this->inCheck($opColour, $move['from'])) {
            return false;
        }
        if (
            !(
            ($to[1] == 2 && $this->canCastleQueenSide($castling, $colour))
            || ($to[1] == 6 && $this->canCastleKingSide($castling, $colour))
            )
        ) {
            return false;
        }

        $rookFromCol = 7;
        $start = 5;
        $end = 7;
        $rookToCol = 5;
        if ($to[1] == 2) {
            //long castle
            if (!$this->vacant($from[0], 1)) {
                //extra square rook travels is occupied
                return false;
            }
            $rookFromCol = 0;
            $start = 2;
            $end = 4;
            $rookToCol = 3;
        }

        //check intermittent points are vacant
        for ($i = $start; $i < $end; $i++) {
            if (!$this->vacant($from[0], $i)) {
                return false;
            }
            // if in check at intermittent points, return false
            $nextSpace = [$from[0], $i];
            $this->updateAbstractBoard($from, $nextSpace);
            if ($this->inCheck($opColour, $nextSpace)) {
                //put king back in place
                $this->updateAbstractBoard($nextSpace, $from);
                return false;
            }
            //put king back in place
            $this->updateAbstractBoard($nextSpace, $from);
        }
        //move rook
        $this->updateAbstractBoard([$from[0], $rookFromCol], [$to[0], $rookToCol]);

        return true;
    }

    /**
     * @param string $castlingOptions
     * @param string $playerColour
     * @return bool
     */
    private function canCastleKingSide(string $castlingOptions, string $playerColour): bool
    {
        return $this->canCastle($castlingOptions, $playerColour, 'k');
    }

    /**
     * @param string $castlingOptions
     * @param string $playerColour
     * @return bool
     */
    private function canCastleQueenSide(string $castlingOptions, string $playerColour): bool
    {
        return $this->canCastle($castlingOptions, $playerColour, 'q');
    }

    /**
     * @param string $castlingOptions
     * @param string $playerColour
     * @param string $side
     * @return bool
     */
    private function canCastle(string $castlingOptions, string $playerColour, string $side = 'k'): bool
    {
        return strpos($castlingOptions, $this->getPlayerPiece($playerColour, $side)) !== false;
    }
}
