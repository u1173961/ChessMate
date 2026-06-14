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
	 */
	public function validatePiece($move) {
    	$from = $move['from'];
    	$to = $move['to'];
    	$colour = $this->getPieceColour($move['piece']);
		if (abs($to[1] - $from[1]) <= 1 && abs($to[0] - $from[0]) <= 1) {
			return true;
		}
		$castling = $this->game->getBoard()->getPlayerCastling($this->getPlayerIndex($colour));
		if ($castling && $to[0] == $from[0] && !$this->inCheck($colour, $move['from'])) {
			//handle castling
			if (($to[1] == 2 && strpos($castling, $this->getPlayerPiece($colour, 'q')) !== false) 
					|| ($to[1] == 6 && strpos($castling, $this->getPlayerPiece($colour, 'k')) !== false)) {
				$rookFromCol = 0;
				$start = 1;
				$end = 4;
				$rookToCol = 3;
				if ($to[1] == 6) {
					$rookFromCol = 7;
					$start = 5;
					$end = 7;
					$rookToCol = 5;
				}
				//check intermittent points are vacant
				for ($i = $start; $i < $end; $i++) {
					if (!$this->vacant($from[0], $i)) {
						return false;
					}
					// if in check at intermittent points, return false
					$nextSpace = [$from[0], $i];
					$this->updateAbstractBoard($from, $nextSpace);
					if ($this->inCheck($colour, $move['from'])) {
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
		}
		return false;
	}
}
