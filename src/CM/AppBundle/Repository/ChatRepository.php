<?php

namespace CM\AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use CM\UserBundle\Entity\User;
use CM\AppBundle\Entity\Game;

/**
 * ChatRepository
 */
class ChatRepository extends EntityRepository
{
    /**
     * Find chat messages for player & game
     * @param User $player
     * @param Game $game
     * @param int $lastSeen
     *
     * @return array
     */
    public function findGamePlayerChat(User $user, Game $game, $lastSeen)
    {
        $result = $this->getEntityManager()
        ->createQuery(
            'SELECT m FROM CM\\AppBundle\\Entity\\ChatMessage m
				WHERE m.id > :lastSeen
				AND m.user = :user
				AND m.game = :game'
        )
        ->setParameter('lastSeen', $lastSeen)
        ->setParameter('user', $user)
        ->setParameter('game', $game)
        ->getResult();

        $res = count($result);
        if ($res != 0) {
            //get new last seen
            $msgs = array($result[$res - 1]->getId(), array());
        } else {
            $msgs = array($lastSeen, array());
        }
        //restructure
        foreach ($result as $msg) {
            $msgs[1][] = $msg->getMessage();
        }

        return $msgs;
    }
    /**
     * Find all chat messages for game
     * @param Game $game
     *
     * @return array
     */
    public function findAllGameChat(Game $game)
    {
        $result = $this->getEntityManager()
        ->createQuery(
            'SELECT m FROM CM\\AppBundle\\Entity\\ChatMessage m
				where m.game = :game'
        )
        ->setParameter('game', $game)
        ->getResult();

        $res = count($result);
        if ($res != 0) {
            //get new last seen
            $msgs = array($result[$res - 1]->getId(), array());
        } else {
            $msgs = array(0, array());
        }
        //restructure
        foreach ($result as $msg) {
            $msgs[1][] = $msg->getMessage();
        }

        return $msgs;
    }
}
