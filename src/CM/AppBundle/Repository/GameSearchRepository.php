<?php

namespace CM\AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use CM\UserBundle\Entity\User;

/**
 * GameSearchRepository
 */
class GameSearchRepository extends EntityRepository
{
	/**
	 * Find matching search
	 * @param User $player
	 * @param int $length
	 * @param int $minRank
	 * @param int $maxRank
	 *
	 * @return array
	 */
	public function findGameSearch(User $player, $length, $minRank, $maxRank)
	{
		//get search parameters
		$playerRank = $player->getRating();
		$queryParams = array('playerID' => $player->getId(), 
							'playerRank' => $playerRank,
							'minRank' => $minRank,
							'maxRank' => $maxRank );
		$lengthTerm = '';
		if ($length) {
			$queryParams['length'] = $length;
			$lengthTerm .= ' AND (gs.length = :length OR gs.length = 0)';
		}
		//find game
		$search = $this->getEntityManager()
		->createQuery(
				'SELECT gs, ABS(p.rating - :playerRank) AS closest 
				FROM CM\\AppBundle\\Entity\\GameSearch gs
			    JOIN gs.searcher p
				WHERE p.id != :playerID
				AND gs.matched = 0
				AND :playerRank >= gs.minRank
				AND :playerRank <= gs.maxRank
				AND p.rating >= :minRank 
				AND p.rating <= :maxRank
				'.$lengthTerm.' 
				ORDER BY closest ASC'
		)
		->setMaxResults(1)
		->setParameters($queryParams)
		->getResult();
		
		return $search;
	}
	
	/**
	 * Delete game searches
	 * @param Game $gameID
	 */
	public function removeGameSearches($game) {
		$this->getEntityManager()
		->createQuery(
				'DELETE CM\\AppBundle\\Entity\\GameSearch gs
				WHERE gs.game = :game'
		)
		->setParameter('game', $game)
		->execute();
	}
}
