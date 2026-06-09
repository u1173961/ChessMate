<?php
// src/CM/UserBundle/Entity/User.php

namespace CM\UserBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\Common\Collections\ArrayCollection;
use CM\AppBundle\Entity\GameSearch;

/**
 * Glicko rated user
 * reference: http://www.glicko.net/glicko/glicko.pdf
 * 
 * @ORM\Entity
 * @ORM\Table(name="cm_user")
 * @ORM\Entity(repositoryClass="CM\UserBundle\Repository\UserRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
        
    /**
     * @var string
     */
    public $currUsername;

    /**
     * Current games
     *
     * @ORM\ManyToMany(targetEntity="CM\AppBundle\Entity\Game")
     */
    protected $currentGames;
    
    /**
     * Is the user register or a guest
     * 
     * @ORM\Column(type="boolean")
     */
    protected $registered;
    
    /**
     * Glicko rating
     * 
     * @ORM\Column(type="integer")
     */
    protected $rating;
    
    /**
     * Glicko rating deviation
     * 
     * @ORM\Column(type="decimal")
     */
    protected $deviation;
    
    /**
     * Last game time (for Glicko)
     * 
     * @ORM\Column(type="bigint")
     */
    protected $lastPlayedTime;
    
    /**
     * Time of last activity
     *
     * @var \Datetime
     * @ORM\Column(name="last_active_time", type="datetime")
     */
    protected $lastActiveTime;
    
    /**
     * Is chat enabled (default true, but maintains last value if user changes)
     * 
     * @ORM\Column(type="boolean")
     */
    protected $chatty;
    
	/**
	 * Constant governing uncertainty in rating - 
	 * 60 mths without playing is assumed to make any rating as unreliable as that of an unrated player.
	 * Average games in a month is estimated at 50
	 * 
 	 * Solve:
 	 *  startDeviation = sqrt((averageDeviation*averageDeviation) + (constant*constant*50*60))
 	 *  => 350 = sqrt((50*50)+(c*c*50*60)) 
	 *  => c = 6.32
	 * 
	 * @var int
	 */
	const CONSTANT = 6.32;
	
	/**
	 * Average period length
	 * @var int
	 */
	const PERIOD_MINS = 806;

    public function __construct()
    {
        parent::__construct();
        $this->currentGames = new ArrayCollection();
        $this->rating = 1500;
        $this->deviation = 350;
        $this->lastPlayedTime = time();
        $this->chatty = true;
        $this->registered = true;
        $this->lastActiveTime = new \DateTime();
    }
    
    /**
     * @ORM\PostLoad()
     * @ORM\PostUpdate()
     * @ORM\PostPersist()
     */
    public function syncCurrUsername() {
        $this->currUsername = $this->username;
    }
    
    /**
     * @return string
     */
    public function getCurrUsername() {
        return $this->currUsername;
    }
    
    /**
     * Set user as registered or guest
     *
     */
    public function setRegistered($reg) {
    	$this->registered = $reg;
    }
    
    /**
     * Check if user is registered or guest
     *
     * @return Bool
     */
    public function getRegistered() {
    	return $this->registered;
    }
    
    /**
     * Set time of player's last game
     * 
     * @param \Datetime $time
     */
    public function setLastPlayedTime($time)
    {
    	$this->lastPlayedTime = $time;
    }
    
    /**
     * Get time of player's last game
     * 
     * @return \Datetime
     */
    public function getLastPlayedTime()
    {
    	return $this->lastPlayedTime;
    }
    
    /**
     * Set time of last activity
     * 
     * @param \Datetime $time
     */
    public function setLastActiveTime($time)
    {
    	$this->lastActiveTime = $time;
    }
    
    /**
     * Get time of last activity
     * 
     * @return \Datetime
     */
    public function getLastActiveTime()
    {
    	return $this->lastActiveTime;
    }
    
    /**
     * Check if user is online/active
     * 
     * @return Bool
     */
    public function getIsOnline()
    {    
    	return $this->getLastActiveTime() > new \DateTime('3 minutes ago');
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
     * Add game to user
     *
     * @param \CM\AppBundle\Entity\Game $currentGame
     * @return User
     */
    public function addCurrentGame(\CM\AppBundle\Entity\Game $currentGame)
    {
        $this->currentGames[] = $currentGame;

        return $this;
    }

    /**
     * Remove game
     *
     * @param \CM\AppBundle\Entity\Game $currentGame
     */
    public function removeCurrentGame(\CM\AppBundle\Entity\Game $currentGame)
    {
        $this->currentGames->removeElement($currentGame);
    }

    /**
     * Set current games
     *
     * @param \Doctrine\Common\Collections\Collection 
     */
    public function setCurrentGames(\Doctrine\Common\Collections\Collection $games)
    {
        $this->currentGames = $games;
    }

    /**
     * Get current games
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getCurrentGames()
    {
        return $this->currentGames;
    }
    
    /**
     * Enable/Disable chat
     * @param Bool $chatty
     */
    public function setChatty($chatty) {
    	$this->chatty = $chatty;
    }
    
    /**
     * Check if user has chat enabled
     *
     * @return Bool
     */
    public function getChatty() {
    	return $this->chatty;
    }
    
    /**
     * Toggle chat for player
     * @param int $player
     */
    public function toggleChatty() {
    	if ($this->chatty) {
    		$this->chatty = false;
    	} else {
    		$this->chatty = true;
    	}
    }
    
    /**
     * Set Glicko user rating
     */
    public function setRating($rating) {
    	$this->rating = $rating;
    }
    
    /**
     * Get Glicko user rating
     *
     * @return Integer
     */
    public function getRating() {
    	return $this->rating;
    }
    
    /**
     * Set Glicko rating deviation
     */
    public function setDeviation($rd) {
    	$this->deviation = $rd;
    }
    
    /**
     * Get Glicko rating deviation
     *
     * @return Integer
     */
    public function getDeviation() {
    	return $this->deviation;
    }
	
	/**
	 * Adjust rating deviation (at start of each game i.e. period)
	 * @return User
	 */
	public function setStartRD() {
		$pp = time() - $this->lastPlayedTime;
		$t = floor($pp / self::PERIOD_MINS);
		$oldRD = $this->deviation;
		$this->deviation = min(sqrt(($oldRD*$oldRD)+(self::CONSTANT*self::CONSTANT*$t)), 350);

		return $this;
	}
	
	/**
	 * Update player's rating & deviation	 * 
	 * @param array $matches [opRating, opRD, result]
	 * @return User
	 */
	public function updateRating(array $matches) {
		$dSq = $this->getDSq($matches);
		$this->rating = $this->getNewRating($matches, $dSq);
		$this->deviation = $this->getNewDeviation($dSq);

		return $this;
	}
	
	/**
	 * Calculate new rating based on results of all games in ratings period
	 * @param array $matches
	 * @param double $dSq
	 * 
	 * @return int
	 */
	private function getNewRating(array $matches, $dSq) {
		$t1 = $this->getQ()/((1/$this->deviation/$this->deviation)+(1/$dSq));
		$sum = 0;
		foreach ($matches as $match) {
			$sum += $this->getG($match['opRD'])*($match['result'] - $this->getE($match['opRating'], $match['opRD']));
		}
		return round($this->rating + ($t1 * $sum));
	}
		
	/**
	 * Calculate new ratings deviation
	 * For use at start of new rating period
	 * @param double $dSq
	 * 
	 * @return double
	 */
	private function getNewDeviation($dSq) {
		//set minimum RD threshold of 30
		return max(round(sqrt(pow((1/$this->deviation/$this->deviation)+(1/$dSq),-1)), 1), 30);
	}
	
	/**
	 * Get d squared as a summation of all matches within set ratings period
	 * Implemented on a game by game basis in this instance
	 */
	private function getDSq($matches) {
		$sum = 0;
		foreach ($matches as $match) {
			$sum += $this->getMatchDifference($match['opRating'], $match['opRD']);
		}
		$q = $this->getQ();
		return pow(($q*$q*$sum),-1);
	}
	
	/**
	 * Get difference for single match
	 * @param int $opRating
	 * @param double $opRD
	 * 
	 * @return double
	 */
	private function getMatchDifference($opRating, $opRD) {
		$g = $this->getG($opRD);
		$e = $this->getE($opRating, $opRD);
		return $g*$g*$e*(1-$e);
	}
	
	/**
	 * Get q term of equations
	 * @return double
	 */
	private function getQ() {
		return log(10)/400;
	}
	
	/**
	 * Get g term of equations
	 * @return double
	 */
	private function getG($RD) {
		$q = $this->getQ();
		return 1/sqrt(1+(3*$q*$q*$RD*$RD/M_PI/M_PI));
	}

	/**
	 * Get E term of equations
	 * @return double
	 */
	private function getE($opRating, $opRD) {
		$pow = -$this->getG($opRD)*($this->rating - $opRating)/400;
		return 1/(1+pow(10,$pow));
	}
	
	/**
	 * {@override}
	 */
	public function serialize()
	{
	    return serialize($this->__serialize());
	}
	
	/**
	 * {@override}
	 */
	public function unserialize($serialized)
	{
	    $this->__unserialize(unserialize($serialized));
	}

	public function __serialize(): array
	{
	    return array(
	        $this->password,
	        $this->salt,
	        $this->usernameCanonical,
	        $this->username,
	        $this->enabled,
	        $this->id,
	        $this->email,
	        $this->emailCanonical,
	        $this->currUsername,
	    );
	}

	public function __unserialize(array $data): void
	{
	    if (13 === count($data)) {
	        // Unserializing a User object from 1.3.x
	        unset($data[4], $data[5], $data[6], $data[9], $data[10]);
	        $data = array_values($data);
	    } elseif (11 === count($data)) {
	        // Unserializing a User from a dev version somewhere between 2.0-alpha3 and 2.0-beta1
	        unset($data[4], $data[7], $data[8]);
	        $data = array_values($data);
	    }

	    list(
	        $this->password,
	        $this->salt,
	        $this->usernameCanonical,
	        $this->username,
	        $this->enabled,
	        $this->id,
	        $this->email,
	        $this->emailCanonical,
	        $this->currUsername
	        ) = $data;
	}
}
