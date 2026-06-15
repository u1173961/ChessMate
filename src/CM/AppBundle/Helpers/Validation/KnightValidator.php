<?php

namespace CM\AppBundle\Helpers\Validation;

use CM\AppBundle\Entity\Game;
use CM\UserBundle\Entity\User;
use CM\AppBundle\Entity\Board;

/**
 * Knight validator
 */
class KnightValidator extends ValidationHelper
{
	/**
	 * Validate knight movement
	 * @param array $move
     * @return bool
	 */
    public function validatePiece(array $move): bool
    {
    	$from = $move['from'];
    	$to = $move['to'];
		return (($to[0] - $from[0]) ** 2)
            + (($to[1] - $from[1]) ** 2)
            === 5;
	}
}
