<?php

namespace CM\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'CM\AppBundle\Repository\GameRepository')]
#[ORM\Table(name: 'game')]
class Game
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    #[ORM\OneToOne(targetEntity: 'Board', cascade: ['persist'])]
    private $board;

    #[ORM\ManyToMany(targetEntity: 'CM\UserBundle\Entity\User', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'game_players')]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private $players;

    #[ORM\Column(type: 'integer')]
    private $activePlayerIndex;

    #[ORM\Column(type: 'array')]
    private $playersJoined;

    #[ORM\Column(type: 'array')]
    private $chattyPlayers;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private $lastMoveTime;

    #[ORM\Column(type: 'array')]
    protected $lastMove;

    #[ORM\Column(type: 'boolean')]
    private $lastMoveValidated;

    #[ORM\Column(type: 'integer')]
    private $length;

    #[ORM\Column(type: 'array')]
    private $playerTimes;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $drawOfferer;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $victorIndex;

    #[ORM\Column(type: 'string', nullable: true)]
    private $gameOverMessage;

    /**
     * Constructor
     */
    public function __construct($board, $length)
    {
        $this->players = new \Doctrine\Common\Collections\ArrayCollection();
        $this->board = $board;
        $this->playersJoined = array(false,false);
        $this->playerTimes = array($length * 1000, $length * 1000);
        $this->length = $length;
        //set white as active
        $this->setActivePlayerIndex(0);
        $this->lastMoveValidated = true;
        $this->chatLog = array();
        $this->drawOfferer = 2;
        //set last move by no-one to prevent superfluous check
        $this->lastMove = array('by' => 2);
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set player has joined game
     *
     * @param int $player index
     * @param bool $joined
     * @return Game
     */
    public function setPlayerJoined($player, $joined)
    {
        $this->playersJoined[$player] = $joined;

        return $this;
    }

    /**
     * Check if player has joined
     * @param int $player index
     *
     * @return boolean
     */
    public function getPlayerJoined($player)
    {
        return $this->playersJoined[$player];
    }

    /**
     * Check if both players have joined
     *
     * @return boolean
     */
    public function getJoined()
    {
        return ($this->playersJoined[0] && $this->playersJoined[1]);
    }

    /**
     * Set board
     *
     * @param \CM\AppBundle\Entity\Board $board
     * @return Game
     */
    public function setBoard(\CM\AppBundle\Entity\Board $board = null)
    {
        $this->board = $board;

        return $this;
    }

    /**
     * Get board
     *
     * @return \CM\AppBundle\Entity\Board
     */
    public function getBoard()
    {
        return $this->board;
    }

    /**
     * Set whitePlayer
     *
     * @param \CM\UserBundle\Entity\User $whitePlayer
     * @return Game
     */
    public function setWhitePlayer(\CM\UserBundle\Entity\User $whitePlayer = null)
    {
        $this->players->set(0, $whitePlayer);

        return $this;
    }

    /**
     * Get whitePlayer
     *
     * @return \CM\UserBundle\Entity\User
     */
    public function getWhitePlayer()
    {
        return $this->players->get(0);
    }

    /**
     * Set blackPlayer
     *
     * @param \CM\UserBundle\Entity\User $blackPlayer
     * @return Game
     */
    public function setBlackPlayer(\CM\UserBundle\Entity\User $blackPlayer)
    {
        $this->players->set(1, $blackPlayer);

        return $this;
    }

    /**
     * Get blackPlayer
     *
     * @return \CM\UserBundle\Entity\User
     */
    public function getBlackPlayer()
    {
        return $this->players->get(1);
    }

    /**
     * Set active player index
     * @param int index
     *
     * @return Game
     */
    public function setActivePlayerIndex($index)
    {
        if ($index == 0 || $index == 1) {
            $this->activePlayerIndex = $index;
        }

        return $this;
    }


    /**
     * Get active player index
     *
     * @return \CM\UserBundle\Entity\User
     */
    public function getActivePlayerIndex()
    {
        return $this->activePlayerIndex;
    }


    /**
     * Get inactive player index
     *
     * @return \CM\UserBundle\Entity\User
     */
    public function getInactivePlayerIndex()
    {
        return 1 - $this->activePlayerIndex;
    }

    /**
     * Switch active player
     *
     * @return Game
     */
    public function switchActivePlayer()
    {
        $this->activePlayerIndex = 1 - $this->activePlayerIndex;

        return $this;
    }

    /**
     * Get active player
     *
     * @return \CM\UserBundle\Entity\User
     */
    public function getActivePlayer()
    {
        return $this->players->get($this->activePlayerIndex);
    }

    /**
     * Add players
     *
     * @param \CM\UserBundle\Entity\User $players
     * @return Game
     */
    public function addPlayer(\CM\UserBundle\Entity\User $player)
    {
        $this->players[] = $player;

        return $this;
    }

    /**
     * Remove players
     *
     * @param \CM\UserBundle\Entity\User $players
     */
    public function removePlayer(\CM\UserBundle\Entity\User $player)
    {
        $this->players->removeElement($player);
    }

    /**
     * Get players
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPlayers()
    {
        return $this->players;
    }

    /**
     * Set length
     *
     * @param integer $length
     * @return Game
     */
    public function setLength($length)
    {
        $this->length = $length;

        return $this;
    }

    /**
     * Get length
     *
     * @return integer
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Set last move time
     *
     * @param integer $time
     * @return Game
     */
    public function setLastMoveTime($time)
    {
        $this->lastMoveTime = $time;

        return $this;
    }

    /**
     * Get last move time
     *
     * @return integer
     */
    public function getLastMoveTime()
    {
        return $this->lastMoveTime;
    }

    /**
     * Set time left for player
     *
     * @param int $player index
     * @param int $timeLeft milliseconds
     * @return Game
     */
    public function setPlayerTime($player, $timeLeft)
    {
        $this->playerTimes[$player] = $timeLeft;

        return $this;
    }

    /**
     * Get time left for player
     *
     * @return int
     */
    public function getPlayerTime($player)
    {
        if ($this->over()) {
            return 0;
        }
        if ($player == $this->activePlayerIndex && !is_null($this->lastMoveTime) && $this->lastMoveValidated) {
            return $this->playerTimes[$player] + $this->lastMoveTime - round(microtime(true) * 1000);
        }
        return $this->playerTimes[$player];
    }

    /**
     * Enable/disable chat for player
     *
     * @param int $player index
     * @param bool $chatty
     * @return Game
     */
    public function setPlayerIsChatty($player, $chatty)
    {
        $this->chattyPlayers[$player] = $chatty;

        return $this;
    }

    /**
     * Check if player has chat enabled
     *
     * @return bool
     */
    public function getPlayerIsChatty($player)
    {
        return $this->chattyPlayers[$player];
    }

    /**
     * Toggle chat for player
     * @param int $player
     */
    public function togglePlayerIsChatty($player)
    {
        $this->chattyPlayers[$player] = !$this->chattyPlayers[$player];
    }

    /**
     * Set last move, for validation
     *
     * @param array $move[by<playerIndex>, from[y,x], to[y,x], newFEN, enPassantAvailable, newPiece]
     *
     * @return Game
     */
    public function setLastMove(array $move)
    {
        $this->lastMove = $move;

        return $this;
    }

    /**
     * Get last move, for validation
     *
     * @return array
     */
    public function getLastMove()
    {
        return $this->lastMove;
    }

    /**
     * Get index of player that made last move
     *
     * @return array
     */
    public function getLastMoveBy()
    {
        return $this->lastMove['by'];
    }

    /**
     * Set last move is validated
     *
     * @param boolean $validated
     * @return Game
     */
    public function setLastMoveValidated($validated)
    {
        $this->lastMoveValidated = $validated;

        return $this;
    }

    /**
     * Check if last move is validated
     *
     * @return boolean
     */
    public function getLastMoveValidated()
    {
        return $this->lastMoveValidated;
    }

    /**
     * Check if new move is ready
     * @param int $player index
     *
     * @return boolean
     */
    public function newMoveReady($player)
    {
        return $this->getLastMoveBy() != $player && !$this->getLastMoveValidated();
    }

    /**
     * Check if game is over
     * @return boolean
     */
    public function over()
    {
        return !is_null($this->victorIndex);
    }

    /**
     * Set game over
     * @param int $victor index, 2 for draw
     * @param string $message game over message
     * @return boolean
     */
    public function setGameOver($victor, $message)
    {
        //set won/lost/drawn on players
        if (!$this->over()) {
            //mark game as over
            $this->setVictorIndex($victor);
            //calculate new ratings
            if ($victor == 2) {
                $wResult = 0.5;
                $lResult = 0.5;
                $windex = 0;
                $lIndex = 1;
            } else {
                $wResult = 1;
                $lResult = 0;
                $windex = $victor;
                $lIndex = 1 - $victor;
            }
            $winner = $this->players->get($windex);
            $wRating = $winner->getRating();
            $wDev = $winner->getDeviation();
            $loser = $this->players->get($lIndex);
            $lRating = $loser->getRating();
            $lDev = $loser->getDeviation();
            //update ratings for registered users
            if ($winner->getRegistered()) {
                $winner->updateRating(array(array('opRating' => $lRating, 'opRD' => $lDev, 'result' => $wResult)));
            }
            if ($loser->getRegistered()) {
                $loser->updateRating(array(array('opRating' => $wRating, 'opRD' => $wDev, 'result' => $lResult)));
            }
            //remove from user's current games
            foreach ($this->players as $p) {
                $p->removeCurrentGame($this);
            }
            $this->setGameOverMessage($message);
        }
    }

    /**
     * Set game over message
     *
     * @return Game
     */
    public function setGameOverMessage($message)
    {
        $this->gameOverMessage = $message;
        return $this;
    }

    /**
     * Get game over message
     *
     * @return string
     */
    public function getGameOverMessage()
    {
        return $this->gameOverMessage;
    }

    /**
     * Set index of player offering draw
     * @param int index
     *
     * @return Game
     */
    public function setDrawOfferer($index)
    {
        $this->drawOfferer = $index;

        return $this;
    }

    /**
     * Get index of player offering draw
     *
     * @return int
     */
    public function getDrawOfferer()
    {
        return $this->drawOfferer;
    }

    /**
     * Set victor's index, if game over
     * @param int index
     *
     * @return Game
     */
    public function setVictorIndex($index)
    {
        $this->victorIndex = $index;

        return $this;
    }

    /**
     * Get victor's index
     *
     * @return int
     */
    public function getVictorIndex()
    {
        return $this->victorIndex;
    }

    /**
     * Set rating deviations for period
     */
    public function setStartRDs()
    {
        foreach ($this->players as $p) {
            $p->setStartRD();
        }
    }
}
