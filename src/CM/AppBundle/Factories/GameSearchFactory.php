<?php

namespace CM\AppBundle\Factories;

use CM\AppBundle\Entity\GameSearch;
use CM\UserBundle\Entity\User;

/**
 * Chess game search factory
 */
class GameSearchFactory
{
    /**
     * Create new game search
     * @param User $player
     * @param int $duration
     * @param int $skill
     * @return \CM\AppBundle\Entity\GameSearch
     */
    public function createNewSearch(User $player, $duration = null, $skill = null)
    {
        if (!$skill) {
            //match any
            $minRank = 0;
            $maxRank = 3000;
        } else {
            $playerRank = $player->getRating();
            if ($skill == 1) {
                //best match
                $minRank = 0;
                $maxRank = 3000;
            } elseif ($skill == 2) {
                //lesser skill
                $maxRank = $playerRank;
                $minRank = 0;
            } else {
                //greater skill
                $minRank = $playerRank;
                $maxRank = 3000;
            }
        }
        $search = new GameSearch($duration, $minRank, $maxRank);
        $search->setSearcher($player);

        return $search;
    }
}
