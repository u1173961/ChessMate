<?php

namespace CM\AppBundle\Factories;

use CM\AppBundle\Entity\Game;
use CM\UserBundle\Entity\User;
use CM\AppBundle\Entity\Board;
use Doctrine\ORM\EntityManager;

/**
 * Chess game factory
 */
class GameFactory
{
    /**
     * Create new game of chess
     *
     * @param int $length the length of game
     * @param User $whitePlayer player 1
     * @param User $blackPlayer player 2
     *
     * @return Game
     */
    public function createNewGame($length, User $whitePlayer, User $blackPlayer)
    {
        //create board
        $board = new Board();
        $game = new Game($board, $length);

        $game->addPlayer($whitePlayer);
        $game->setPlayerIsChatty(0, $whitePlayer->getChatty());
        $game->addPlayer($blackPlayer);
        $game->setPlayerIsChatty(1, $blackPlayer->getChatty());

        return $game;
    }
}
