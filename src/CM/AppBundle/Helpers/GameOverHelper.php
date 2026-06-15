<?php

namespace CM\AppBundle\Helpers;

use CM\AppBundle\Entity\Game;
use CM\AppBundle\Helpers\Validation\ValidationHelper;
use Doctrine\ORM\EntityManager;

/**
 * Evaluate when a game is over due to checkmate/stalemate/tie
 */
class GameOverHelper extends ValidationHelper
{
	/**
	 * Check if last move ended the game i.e. opponent in checkmate/stalemate/drawn
	 * @param Game $game The game
	 * @param bool|int $gameOver Attacking player's opinion on game over
	 * @param bool|int $gameOver2 Defending player's opinion on game over
	 * @param int $pIndex The player confirming/refuting game over
	 */
	public function checkGameOver(Game $game, $gameOver, $gameOver2, $pIndex, EntityManager $em) {
		$over = false;
    	if ($gameOver != $gameOver2) {
    		//consensus differs - check for game over
		   	if ($pIndex === 0) {
		   		$colour = 'b';
		   	} else {
		   		$colour = 'w';
		   	}
	    	$this->setGlobals($game);
	    	$msg = $this->getGameOver($colour);
	    	if ($msg) {
	    		$over = 'Game Over: '.$msg;
	    		if ($msg == 'Checkmate') {
	    			$game->setGameOver(1 - $pIndex, $over);
	    		} else {
	    			$game->setGameOver(2, $over);
	    		}
	    	}
    	} else if ($gameOver) {
	    	$over = 'Game Over: ';
    		//apply game over
			if ($gameOver == 3) {
				//checkmate
		    	$victor = 1 - $pIndex;
				$over .= 'Checkmate';
			} else {
				//draw
				$victor = 2;
				if ($gameOver == 2) {
					$over .= 'Stalemate';
				} else {
					$over .= 'Drawn';
				}
			}
    		$game->setGameOver($victor, $over);
    	}
    	if ($over) {
    		$em->flush();
    		//get new ratings for immediate display
    		$em->refresh($game);
	    	$players = $game->getPlayers();
	    	foreach ($players as $p) {
	    		$em->refresh($p);
	    	}
	    	$pRating = $players->get($pIndex)->getRating();
	    	$opRating = $players->get(1 - $pIndex)->getRating();
	    	return array('pRating' => $pRating, 'opRating' => $opRating, 'overMsg' => $over);
    	}

    	return false;
	}

	/**
	 * Check if last move ended the game
	 * From the perspective of the attacking player i.e. last to move
	 * @param string $colour
	 * @return boolean|string
	 */
	public function getGameOver(string $colour) {
		//get opponent's colour
		$opColour = $this->getOpponentColour($colour);
		//get opponent's king's square
		$kingSquare = $this->getKingSquare($opColour);
		$king = $this->getPlayerPiece($opColour, 'k');
		//check for draw
		$alliesLeft = $this->alliesLeft($opColour);
		if (!$alliesLeft && !$this->alliesLeft($colour)) {
			return 'Drawn';
		}
		//get reachable squares
		$reachables = $this->getReachableSquaresForKing($kingSquare, $opColour);
		//remove king
		$this->board[$kingSquare[0]][$kingSquare[1]] = false;
		//check if any reachable squares are safe
		foreach ($reachables as $square) {
			if (!$this->inCheck($colour, $square)) {
				//put king back
				$this->board[$kingSquare[0]][$kingSquare[1]] = $king;
				return false;
			}
		}
		//--> no safe squares within reach
		//put king back
		$this->board[$kingSquare[0]][$kingSquare[1]] = $king;
		//check if in check
		if (!$this->inCheck($colour, $kingSquare)) {
			//check for stalemate
			if (!$alliesLeft) {
				return 'Stalemate';
			}
			return false;
		}
		//--> in check
		//check if more than one threat from different angles
		$checkThreat = $this->checkThreat;
		//replace threat with blocker
		$threat = $this->board[$checkThreat[0]][$checkThreat[1]];
		$this->board[$checkThreat[0]][$checkThreat[1]] = 'x';
		//if still in check, threat cannot be taken or blocked
		if ($this->inCheck($colour, $kingSquare)) {
			//restore board state
			$this->board[$checkThreat[0]][$checkThreat[1]] = $threat;
			return 'Checkmate';
		}
		//--> only one active threat
		//restore board state
		$this->board[$checkThreat[0]][$checkThreat[1]] = $threat;
		if ($threat == $this->getPlayerPiece($opColour, 'n')) {
			//get copy of  board
			$board = $this->board;
			//attempt to take knight
			if (!$this->getSquareIsReachableWithoutCausingCheck($checkThreat, $opColour, $colour, $kingSquare)) {
				//knight not takeable - revert board
				$this->board = $board;
				return 'Checkmate';
			}
		} else {
			//attempt to block/take other threats
			if ($checkThreat[0] == $kingSquare[0]) {
				//horizontal check
				if (!$this->checkOnXAxisIsDefendable($checkThreat[1], $kingSquare[1], $checkThreat[0], $opColour, $colour)) {
					return 'Checkmate';
				}
			} else if ($checkThreat[1] == $kingSquare[1]) {
				//vertical check
				if (!$this->checkOnYAxisIsDefendable($checkThreat[0], $kingSquare[0], $checkThreat[1], $opColour, $colour)) {
					return 'Checkmate';
				}
			} else {
				//diagonal check
				if (!$this->checkOnDiagIsDefendable($checkThreat[1], $checkThreat[0], $kingSquare[1], $kingSquare[0], $opColour, $colour)) {
					return 'Checkmate';
				}
			}
		}
		return false;
	}

	/**
	 * Check if target square is reachable by player in check
	 * Used for escaping check i.e. blocking/taking attacking piece.
	 * Changes are made to the global board and should be reverted post-execution
	 * Moves must not cause new check
	 * @param array $target
	 * @param string $colour
	 * @param string $opColour
	 * @param array $kingSquare
	 * @return boolean
	 */
	private function getSquareIsReachableWithoutCausingCheck($target, $colour, $opColour, $kingSquare) {
		//Note: inCheck() here, just means reachable
		while ($this->inCheck($colour, $target)) {
			//check can be blocked/taken, provided moving does not cause new check
			$source = $this->checkThreat;
			//move defender to empty space
			$this->updateAbstractBoard($source, $target);
			if (!$this->inCheck($opColour, $kingSquare)) {
				//checker blockable/takeable
				return true;
			}
			//new check created
			//defender cannot be moved, ignore in further attempts
			$this->board[$target[0]][$target[1]] = false;
			$this->board[$source[0]][$source[1]] = 'x';
		}
		return false;
	}

	/**
	 * Check if check on x-axis is defendable
	 * inCheck() is used to identify reachable squares
	 * @param int  $from		x1, Checker's col
	 * @param int  $to			x2, King's col
	 * @param int  $row			y, Checker/King's row
	 * @param string $colour 	Player in check
	 * @param string $opColour 	Player causing check
	 *
	 * @return boolean
	 */
	private function checkOnXAxisIsDefendable($from, $to, $row, $colour, $opColour) {
		//get copy of  board
		$board = $this->board;
		//get x-axis direction
		$range = abs($to - $from);
		$x = ($to - $from) / $range;
		$kingSquare = array($row, $to);
		//check if squares inbetween are defendable
		for ($i = 0; $i < $range; $i++) {
			$blockTo = array($row, $from + ($i*$x));
			if ($this->getSquareIsReachableWithoutCausingCheck($blockTo, $colour, $opColour, $kingSquare)) {
				//checker is blockable/takeable - revert board
				$this->board = $board;
				return true;
			}
		}
		//check not blockable - revert
		$this->board = $board;

		return false;
	}

	/**
	 * Check if check on y-axis is defendable
	 * inCheck() is used to identify reachable squares
	 * @param int  $from		y1, Checker's row
	 * @param int  $to			y2, King's row
	 * @param int  $col			x, Checker/King's col
	 * @param string $colour 		Player in check
	 * @param string $opColour 	Player causing check
	 *
	 * @return boolean
	 */
	private function checkOnYAxisIsDefendable($from, $to, $col, $colour, $opColour) {
		//get copy of  board
		$board = $this->board;
		//get y-axis direction
		$range = abs($to - $from);
		$y = ($to - $from) / $range;
		$kingSquare = array($to, $col);
		//check if squares inbetween are defendable
		for ($i = 0; $i < $range; $i++) {
			$blockTo = array($from + ($i*$y), $col);
			if ($this->getSquareIsReachableWithoutCausingCheck($blockTo, $colour, $opColour, $kingSquare)) {
				//checker is blockable/takeable - revert board
				$this->board = $board;
				return true;
			}
		}
		//check not blockable - revert
		$this->board = $board;

		return false;
	}

	/**
	 * Check if check on diagonal is defendable
	 * inCheck() is used to identify reachable squares
	 * @param int  $fromX		Checker's col
	 * @param int  $fromY		Checker's row
	 * @param int  $toX			King's col
	 * @param int  $toY			King's row
	 * @param string $colour 	Player in check
	 * @param string $opColour 	Player causing check
	 *
	 * @return boolean
	 */
	private function checkOnDiagIsDefendable($fromX, $fromY, $toX, $toY, $colour, $opColour) {
		//get copy of  board
		$board = $this->board;
		//get range
		$range = abs($fromX - $toX);
		//get x-axis direction
		$xDir = ($toX - $fromX) / $range;
		//get y-axis direction
		$yDir = ($toY - $fromY) / $range;
		$kingSquare = array($toY, $toX);
		//check if squares inbetween are defendable
		for ($i = 0; $i < $range; $i++) {
			$blockTo = array($fromY + ($i*$yDir), $fromX + ($i*$xDir));
			if ($this->getSquareIsReachableWithoutCausingCheck($blockTo, $colour, $opColour, $kingSquare)) {
				//checker is blockable/takeable - revert board
				$this->board = $board;
				return true;
			}
		}
		//check not blockable - revert
		$this->board = $board;

		return false;
	}

    /**
     * Check if given colour has any pieces other than king
     * @param string $colour
     * @return bool
     */
    private function alliesLeft($colour) {
		foreach ($this->board as $row) {
			foreach ($row as $piece) {
				if ($piece && $this->getPieceColour($piece) == $colour && $piece != $this->getPlayerPiece($colour, 'k')) {
					return true;
				}
			}
		}
		return false;
    }

    /**
     * Get array of indices for squares reachable by king
     * @param array $kingSquare
     * @param string $colour
     * @return array
     */
    private function getReachableSquaresForKing($kingSquare, $colour) {
    	//get reachable squares
		$reachables = array();
		//avoid out of bounds
		$rowStart = $kingSquare[0];
		$rowEnd = $kingSquare[0];
		if ($rowStart > 0) {
			$rowStart--;
			if ($rowEnd < 7) {
				$rowEnd++;
			}
		}
		$colStart = $kingSquare[1];
		$colEnd = $kingSquare[1];
		if ($colStart > 0) {
			$colStart--;
			if ($colEnd < 7) {
				$colEnd++;
			}
		}
		for ($row = $rowStart; $row <= $rowEnd; $row++) {
			for ($col = $colStart; $col <= $colEnd; $col++) {
				$occupant = $this->board[$row][$col];
				if (!$occupant || $this->getPieceColour($occupant) != $colour) {
					$reachables[] = array($row, $col);
				}
			}
		}
    	return $reachables;
    }
}