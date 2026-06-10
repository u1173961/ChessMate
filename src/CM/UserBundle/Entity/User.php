<?php

namespace CM\UserBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: 'CM\UserBundle\Repository\UserRepository')]
#[ORM\Table(name: 'cm_user')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    #[ORM\Column(type: 'string', length: 180)]
    protected $username;

    #[ORM\Column(name: 'username_canonical', type: 'string', length: 180, unique: true)]
    protected $usernameCanonical;

    #[ORM\Column(type: 'string', length: 180)]
    protected $email;

    #[ORM\Column(name: 'email_canonical', type: 'string', length: 180, unique: true)]
    protected $emailCanonical;

    #[ORM\Column(type: 'boolean')]
    protected $enabled = true;

    #[ORM\Column(type: 'string', nullable: true)]
    protected $salt;

    #[ORM\Column(type: 'string')]
    protected $password = '';

    /**
     * Not persisted.
     */
    protected $plainPassword;

    #[ORM\Column(name: 'last_login', type: 'datetime', nullable: true)]
    protected $lastLogin;

    #[ORM\Column(name: 'confirmation_token', type: 'string', length: 180, unique: true, nullable: true)]
    protected $confirmationToken;

    #[ORM\Column(name: 'password_requested_at', type: 'datetime', nullable: true)]
    protected $passwordRequestedAt;

    #[ORM\Column(type: 'array')]
    protected $roles = array();

    /**
     * @var string
     */
    public $currUsername;

    #[ORM\ManyToMany(targetEntity: 'CM\AppBundle\Entity\Game')]
    protected $currentGames;

    #[ORM\Column(type: 'boolean')]
    protected $registered;

    #[ORM\Column(type: 'integer')]
    protected $rating;

    #[ORM\Column(type: 'decimal')]
    protected $deviation;

    #[ORM\Column(type: 'bigint')]
    protected $lastPlayedTime;

    #[ORM\Column(name: 'last_active_time', type: 'datetime')]
    protected $lastActiveTime;

    #[ORM\Column(type: 'boolean')]
    protected $chatty;

    /**
     * Constant governing uncertainty in rating.
     */
    const CONSTANT = 6.32;

    /**
     * Average period length.
     */
    const PERIOD_MINS = 806;

    public function __construct()
    {
        $this->currentGames = new ArrayCollection();
        $this->rating = 1500;
        $this->deviation = 350;
        $this->lastPlayedTime = time();
        $this->chatty = true;
        $this->registered = true;
        $this->lastActiveTime = new \DateTime();
        $this->roles = array();
        $this->enabled = true;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function syncCanonicalFields()
    {
        $this->usernameCanonical = $this->canonicalize($this->username);
        $this->emailCanonical = $this->canonicalize($this->email);
    }

    #[ORM\PostLoad]
    #[ORM\PostUpdate]
    #[ORM\PostPersist]
    public function syncCurrUsername()
    {
        $this->currUsername = $this->username;
    }

    private function canonicalize($value)
    {
        return null === $value ? null : mb_strtolower(trim($value));
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($username)
    {
        $this->username = $username;
        $this->usernameCanonical = $this->canonicalize($username);

        return $this;
    }

    public function getUsernameCanonical()
    {
        return $this->usernameCanonical;
    }

    public function setUsernameCanonical($usernameCanonical)
    {
        $this->usernameCanonical = $usernameCanonical;

        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
        $this->emailCanonical = $this->canonicalize($email);

        return $this;
    }

    public function getEmailCanonical()
    {
        return $this->emailCanonical;
    }

    public function setEmailCanonical($emailCanonical)
    {
        $this->emailCanonical = $emailCanonical;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword()
    {
        return $this->plainPassword;
    }

    public function setPlainPassword($plainPassword)
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function setSalt($salt)
    {
        $this->salt = $salt;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles)
    {
        $this->roles = array();

        foreach ($roles as $role) {
            $this->addRole($role);
        }

        return $this;
    }

    public function addRole($role)
    {
        $role = strtoupper($role);

        if ('ROLE_USER' === $role) {
            return $this;
        }

        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getCurrUsername()
    {
        return $this->currUsername;
    }

    public function setEnabled($enabled)
    {
        $this->enabled = (bool) $enabled;

        return $this;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getLastLogin()
    {
        return $this->lastLogin;
    }

    public function setLastLogin($time)
    {
        $this->lastLogin = $time;

        return $this;
    }

    public function getConfirmationToken()
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken($confirmationToken)
    {
        $this->confirmationToken = $confirmationToken;

        return $this;
    }

    public function getPasswordRequestedAt()
    {
        return $this->passwordRequestedAt;
    }

    public function setPasswordRequestedAt($date)
    {
        $this->passwordRequestedAt = $date;

        return $this;
    }

    public function setRegistered($reg)
    {
        $this->registered = $reg;
    }

    public function getRegistered()
    {
        return $this->registered;
    }

    public function setLastPlayedTime($time)
    {
        $this->lastPlayedTime = $time;
    }

    public function getLastPlayedTime()
    {
        return $this->lastPlayedTime;
    }

    public function setLastActiveTime($time)
    {
        $this->lastActiveTime = $time;
    }

    public function getLastActiveTime()
    {
        return $this->lastActiveTime;
    }

    public function getIsOnline()
    {
        return $this->getLastActiveTime() > new \DateTime('3 minutes ago');
    }

    public function getId()
    {
        return $this->id;
    }

    public function addCurrentGame(\CM\AppBundle\Entity\Game $currentGame)
    {
        $this->currentGames[] = $currentGame;

        return $this;
    }

    public function removeCurrentGame(\CM\AppBundle\Entity\Game $currentGame)
    {
        $this->currentGames->removeElement($currentGame);
    }

    public function setCurrentGames(Collection $games)
    {
        $this->currentGames = $games;
    }

    public function getCurrentGames()
    {
        return $this->currentGames;
    }

    public function setChatty($chatty)
    {
        $this->chatty = $chatty;
    }

    public function getChatty()
    {
        return $this->chatty;
    }

    public function toggleChatty()
    {
        $this->chatty = !$this->chatty;
    }

    public function setRating($rating)
    {
        $this->rating = $rating;
    }

    public function getRating()
    {
        return $this->rating;
    }

    public function setDeviation($rd)
    {
        $this->deviation = $rd;
    }

    public function getDeviation()
    {
        return $this->deviation;
    }

    public function setStartRD()
    {
        $pp = time() - $this->lastPlayedTime;
        $t = floor($pp / self::PERIOD_MINS);
        $oldRD = $this->deviation;
        $this->deviation = min(sqrt(($oldRD * $oldRD) + (self::CONSTANT * self::CONSTANT * $t)), 350);

        return $this;
    }

    public function updateRating(array $matches)
    {
        $dSq = $this->getDSq($matches);
        $this->rating = $this->getNewRating($matches, $dSq);
        $this->deviation = $this->getNewDeviation($dSq);

        return $this;
    }

    private function getNewRating(array $matches, $dSq)
    {
        $t1 = $this->getQ() / ((1 / $this->deviation / $this->deviation) + (1 / $dSq));
        $sum = 0;

        foreach ($matches as $match) {
            $sum += $this->getG($match['opRD']) * ($match['result'] - $this->getE($match['opRating'], $match['opRD']));
        }

        return round($this->rating + ($t1 * $sum));
    }

    private function getNewDeviation($dSq)
    {
        return max(round(sqrt(pow((1 / $this->deviation / $this->deviation) + (1 / $dSq), -1)), 1), 30);
    }

    private function getDSq($matches)
    {
        $sum = 0;

        foreach ($matches as $match) {
            $sum += $this->getMatchDifference($match['opRating'], $match['opRD']);
        }

        $q = $this->getQ();

        return pow(($q * $q * $sum), -1);
    }

    private function getMatchDifference($opRating, $opRD)
    {
        $g = $this->getG($opRD);
        $e = $this->getE($opRating, $opRD);

        return $g * $g * $e * (1 - $e);
    }

    private function getQ()
    {
        return log(10) / 400;
    }

    private function getG($RD)
    {
        $q = $this->getQ();

        return 1 / sqrt(1 + (3 * $q * $q * $RD * $RD / M_PI / M_PI));
    }

    private function getE($opRating, $opRD)
    {
        $pow = -$this->getG($opRD) * ($this->rating - $opRating) / 400;

        return 1 / (1 + pow(10, $pow));
    }

    public function serialize()
    {
        return serialize($this->__serialize());
    }

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
            unset($data[4], $data[5], $data[6], $data[9], $data[10]);
            $data = array_values($data);
        } elseif (11 === count($data)) {
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
