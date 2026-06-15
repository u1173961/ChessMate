<?php

namespace CM\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use CM\UserBundle\Entity\User;
use CM\AppBundle\Entity\Game;

#[ORM\Entity(repositoryClass: 'CM\AppBundle\Repository\GameSearchRepository')]
#[ORM\Table(name: 'game_search')]
class GameSearch
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    #[ORM\Column(type: 'integer')]
    private $minRank;

    #[ORM\Column(type: 'integer')]
    private $maxRank;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $length;

    #[ORM\ManyToOne(targetEntity: 'CM\UserBundle\Entity\User')]
    private $searcher;

    #[ORM\Column(type: 'boolean')]
    private $matched;

    #[ORM\Column(type: 'boolean')]
    private $cancelled;

    #[ORM\OneToOne(targetEntity: 'Game')]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: true)]
    private $game;

    /**
     * Constructor
     */
    public function __construct($length, $minRank, $maxRank)
    {
        $this->length = $length;
        $this->minRank = $minRank;
        $this->maxRank = $maxRank;
        $this->matched = false;
        $this->cancelled = false;
        $this->game = null;
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
     * Set minimum rank
     *
     * @param integer $minRank
     * @return GameSearch
     */
    public function setMinRank($minRank)
    {
        $this->minRank = $minRank;

        return $this;
    }

    /**
     * Get minimum rank
     *
     * @return integer
     */
    public function getMinRank()
    {
        return $this->minRank;
    }

    /**
     * Set maximum rank
     *
     * @param integer $maxRank
     * @return GameSearch
     */
    public function setMaxRank($maxRank)
    {
        $this->maxRank = $maxRank;

        return $this;
    }

    /**
     * Get maximum rank
     *
     * @return integer
     */
    public function getMaxRank()
    {
        return $this->maxRank;
    }

    /**
     * Set length
     *
     * @param integer $length
     * @return GameSearch
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
     * Set search initiator
     *
     * @param User $searcher
     * @return GameSearch
     */
    public function setSearcher(User $searcher)
    {
        $this->searcher = $searcher;

        return $this;
    }

    /**
     * Get search initiator
     *
     * @return User
     */
    public function getSearcher()
    {
        return $this->searcher;
    }

    /**
     * Set search matched
     *
     * @param boolean $matched
     * @return GameSearch
     */
    public function setMatched($matched)
    {
        $this->matched = $matched;

        return $this;
    }

    /**
     * Check if search is matched
     *
     * @return boolean
     */
    public function getMatched()
    {
        return $this->matched;
    }

    /**
     * Set search cancelled
     *
     * @param boolean $cancelled
     * @return GameSearch
     */
    public function setCancelled($cancelled)
    {
        $this->cancelled = $cancelled;

        return $this;
    }

    /**
     * Check if search is cancelled
     *
     * @return boolean
     */
    public function getCancelled()
    {
        return $this->cancelled;
    }

    /**
     * Set game
     *
     * @param Game $game
     * @return GameSearch
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
}
