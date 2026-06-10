<?php

namespace CM\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use CM\UserBundle\Entity\User;

#[ORM\Entity(repositoryClass: 'CM\AppBundle\Repository\ChatRepository')]
#[ORM\Table(name: 'chat_msg')]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\ManyToOne(targetEntity: 'Game')]
    private $game;

    #[ORM\ManyToOne(targetEntity: 'CM\UserBundle\Entity\User')]
    private $user;

    #[ORM\Column(type: 'string')]
    private $message;
    
    /**
     * Constructor
     */
    public function __construct(Game $game, User $user, $message)
    {
        $this->game = $game;
        $this->user = $user;
        $this->message = $message;
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
     * Set game
     *
     * @return ChatMessage 
     */
    public function setGame(Game $game)
    {
    	$this->game = $game;
        return $this;
    }

    /**
     * Get game
     *
     * @return Game 
     */
    public function getGame()
    {
        return $this->game;
    }

    /**
     * Set player
     *
     * @return ChatMessage 
     */
    public function setPlayer(User $player)
    {
    	$this->player = $player;
        return $this;
    }

    /**
     * Get player
     *
     * @return User 
     */
    public function getPlayer()
    {
        return $this->player;
    }

    /**
     * Set message
     *
     * @return ChatMessage 
     */
    public function setMessage($msg)
    {
    	$this->message = $msg;
        return $this;
    }

    /**
     * Get message
     *
     * @return string 
     */
    public function getMessage()
    {
        return $this->message;
    }
}
