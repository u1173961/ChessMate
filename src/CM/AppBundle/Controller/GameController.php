<?php

namespace CM\AppBundle\Controller;

use CM\AppBundle\Factories\GameFactory;
use CM\AppBundle\Factories\GameSearchFactory;
use CM\AppBundle\Helpers\FENHelper;
use CM\AppBundle\Helpers\HTMLHelper;
use CM\AppBundle\Entity\ChatMessage;
use CM\AppBundle\Entity\Game;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class GameController extends AbstractController
{
    private $doctrine;
    private $eventDispatcher;
    private $requestStack;

    public function __construct(ManagerRegistry $doctrine, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack)
    {
        $this->doctrine = $doctrine;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'exercise_html_purifier.default' => \HTMLPurifier::class,
            'fen_helper' => FENHelper::class,
            'game_factory' => GameFactory::class,
            'game_search_factory' => GameSearchFactory::class,
            'html_helper' => HTMLHelper::class,
        ]);
    }

    /**
     * @param string $service
     */
    private function get(string $service)
    {
        return $this->container->get($service);
    }

    /**
     * Login as inactive guest account
     * Create's a new account if none are available
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function guestAction(Request $request)
    {
        //check user is not already logged in
        if (!$this->getUser()) {
            //get inactive guest account
            $em = $this->doctrine->getManager();
            $user = $em->getRepository(\CM\UserBundle\Entity\User::class)->findInactiveGuest();
            if (!$user) {
                //create new guest accounts as needed
                $id = $em->createQuery('SELECT MAX(u.id) FROM CM\\UserBundle\\Entity\\User u')->getSingleScalarResult() + 1;
                $name = "Guest0" . $id;
                $user = new \CM\UserBundle\Entity\User();
                $user->setUsername($name);
                $user->setEmail($name);
                $user->setPassword("");
                $user->setRegistered(false);
                $user->setEnabled(true);
                $user->setLastActiveTime(new \DateTime());
                //give guest average rating
                $user->setRating(1100);
                $em->persist($user);
                $em->flush();
            } else {
                $user = $user[0];
                $name = $user->getUsername();
                $user->setLastActiveTime(new \DateTime());
                //remove any left over games
                $user->setCurrentGames(new ArrayCollection());
            }
            //set login token
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->get("security.token_storage")->setToken($token);
            // fire login
            $event = new InteractiveLoginEvent($request, $token);
            $this->eventDispatcher->dispatch($event, "security.interactive_login");
        }

        return $this->redirect($this->generateUrl('cm_start', array()));
    }

    /**
     * Index action
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function startAction()
    {
        $user = $this->getUser();
        $games = $user->getCurrentGames();

        return $this->render('Game/main.html.twig', array('games' => $games, 'player' => 'x'));
    }

    /**
     * Create search
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function newSearchAction(Request $request)
    {
        $user = $this->getUser();
        $em = $this->doctrine->getManager();
        //get game variables - null if match any
        $duration = $request->request->get('duration');
        $skill = $request->request->get('skill');
        //create new search
        $search = $this->get('game_search_factory')->createNewSearch($user, $duration, $skill);
        $em->persist($search);
        //save changes
        $em->flush();

        return new JsonResponse(array('searchID' => $search->getId()));
    }

    /**
     * Find/create new game
     *
     * @param int $searchID
     * @return JsonResponse
     */
    public function matchSearchAction($searchID)
    {
        //disconnect session
        $this->requestStack->getSession()->save();
        $em = $this->doctrine->getManager();
        $search = $em->getRepository(\CM\AppBundle\Entity\GameSearch::class)->find($searchID);
        $gameID = false;
        if ($search) {
            $user = $this->getUser();
            if ($search->getSearcher() != $user) {
                throw new AccessDeniedException('This is not your search!');
            }

            //find match
            $length = $search->getLength();
            $minRank = $search->getMinRank();
            $maxRank = $search->getMaxRank();
            $repo = $em->getRepository(\CM\AppBundle\Entity\GameSearch::class);
            $match = null;
            $waited = 0;
            while ($search && $waited < 45) {
                if (!$search->getMatched()) {
                    $match = $repo->findGameSearch($user, $length, $minRank, $maxRank);
                    if ($match) {
                        usleep(500000);
                        break;
                    }
                    $em->refresh($search);
                } else {
                    break;
                }
                usleep(500000);
                $waited++;
            }

            $em->refresh($search);
            if ($search->getMatched() || $match) {
                //get game id
                if (!$search->getMatched()) {
                    $match = $match[0][0];
                    //set searches as matched
                    $match->setMatched(true);
                    $search->setMatched(true);
                    $em->flush();
                    //if length is not specified - use opponents settings
                    if (!$length) {
                        $length = $match->getLength();
                        //if neither is specified - default 10 mins. each
                        if (!$length) {
                            $length = 600;
                        }
                    }
                    $game = $this->get('game_factory')->createNewGame($length, $user, $match->getSearcher());
                    $em->persist($game);
                    $match->setGame($game);
                    $em->flush();
                    $gameID = $game->getId();
                } else {
                    while (!$search->getGame()) {
                        usleep(500000);
                        $em->refresh($search);
                    }
                    $gameID = $search->getGame()->getId();
                }
                //return link to game
                return new JsonResponse([
                    'matched' => true,
                    'gameURL' => $this->generateUrl('cm_play_game', ['gameID' => $gameID])
                ]);
            }
            //report back for retry
            return new JsonResponse(array('matched' => false));
        }
        //only reachable if search cancelled & ajax aborted
        return new JsonResponse(array('cancelled' => true));
    }

    /**
     * Cancel search
     * @param int $searchID
     * @throws AccessDeniedException
     * @return JsonResponse
     */
    public function cancelSearchAction($searchID)
    {
        $cancelled = false;
        if ($searchID != 0) {
            $em = $this->doctrine->getManager();
            $search = $em->getRepository(\CM\AppBundle\Entity\GameSearch::class)->find($searchID);
            if ($search) {
                $user = $this->getUser();
                if ($search->getSearcher() != $user) {
                    throw new AccessDeniedException('This is not your search!');
                }
                //remove search
                $em->remove($search);
                $em->flush();
                $cancelled = true;
            }
        }

        return new JsonResponse(array('cancelled' => $cancelled));
    }

    /**
     * Start  game
     *
     * @param int $gameID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function playAction($gameID)
    {
        $user = $this->getUser();
        $em = $this->doctrine->getManager();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        //authenticate user/game
        $this->checkGameValidity($game, $user);
        //get player colour
        $colour = $this->getPlayerColour($game, $user);
        //get player numbers
        $pIndex = $game->getPlayers()->indexOf($user);
        $opIndex = 1 - $pIndex;
        //get time left
        $userTime = $this->getMinutesTimeString(floor($game->getPlayerTime($pIndex) / 100 / 10));
        $opTime = $this->getMinutesTimeString(floor($game->getPlayerTime($opIndex) / 100 / 10));
        $pChatty = $game->getPlayerIsChatty($pIndex);
        $opChatty = $game->getPlayerIsChatty($opIndex);
        //get opponent
        $opponent = $game->getPlayers()->get($opIndex);
        //get taken pieces
        $taken = $this->get('html_helper')->getUnicodeTakenPieces($game->getBoard()->getTaken());

        return $this->render('Game/main.html.twig', array(
            'game' => $game,
            'player' => $colour,
            'opponent' => $opponent,
            'pChatty' => $pChatty,
            'opChatty' => $opChatty,
            'userTime' => $userTime,
            'opTime' => $opTime,
            'taken' => $taken
        ));
    }

    /**
     * Play against computer
     *
     * @param int $skill
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function playComputerAction($skill)
    {
        $taken = $this->get('html_helper')->getUnicodeTakenPieces(array(
            'P' => 0, 'R' => 0, 'N' => 0, 'B' => 0, 'Q' => 0,
            'p' => 0, 'r' => 0, 'n' => 0, 'b' => 0, 'q' => 0
        ));
        return $this->render('Game/playComputer.html.twig', array(
            'skillLevel' => $skill,
            'player' => 'w',
            'taken' => $taken
        ));
    }

    /**
     * Get time string from seconds mm:ss
     * @param unknown $seconds
     * @return string
     */
    private function getMinutesTimeString($seconds)
    {
        $s = $seconds % 60;
        $minutes = ($seconds - $s) / 60;
        if ($s < 10) {
            $s .= '0';
        }

        return $minutes . ':' . $s;
    }

    /**
     * Join game
     * @param int $gameID
     * @return JsonResponse
     */
    public function joinGameAction(int $gameID)
    {
        $user = $this->getUser();
        $em = $this->doctrine->getManager();
        $game = $em->getRepository(Game::class)->find($gameID);
        //authenticate user/game
        $this->checkGameValidity($game, $user);
        //join game
        $game->setPlayerJoined($game->getPlayers()->indexOf($user), true);
        $em->flush();

        return new JsonResponse(array('joined' => $this->checkJoined($user, $game, $em)));
    }

    /**
     * Check opponent has joined the game
     * @param User $user
     * @param Game $game
     * @param EntityManager $em
     * @return bool
     */
    private function checkJoined($user, $game, $em)
    {
        //wait for opponent to join
        $waited = 0;
        $em->refresh($game);
        while (!$game->getJoined() && $waited < 8) {
            sleep(1);
            $em->refresh($game);
            $waited++;
        }
        $joined = $game->getJoined();
        if ($joined) {
            //add to user
            $user->addCurrentGame($game);
            //update rating deviation
            $game->setStartRDs();
            //set time
            $game->setLastMoveTime(round(microtime(true) * 1000) + 300);
        } else {
            //cancel game
            $em->remove($game);
        }
        //remove searches
        $em->getRepository(\CM\AppBundle\Entity\GameSearch::class)->removeGameSearches($game);
        $em->flush();

        return $joined;
    }

    /**
     * Show embedded board view
     *
     * @param int|string $gameID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showBoardAction($gameID = null)
    {
        if (is_null($gameID)) {
            $gameID = 'x';
            $colour = 'x';
            //get default pieces
            $pieces = $this->get('html_helper')->getUnicodePieces();
        } elseif ($gameID == 'w' || $gameID == 'b') {
            //get black/white orientation
            $colour = $gameID;
            $gameID = 'x';
            $pieces = $this->get('html_helper')->getUnicodePieces();
        } else {
            $user = $this->getUser();
            $em = $this->doctrine->getManager();
            $game = $em->getRepository(Game::class)->find($gameID);
            //authenticate user/game
            $this->checkGameValidity($game, $user);
            //get player colour
            $colour = $this->getPlayerColour($game, $user);
            //get game pieces
            $board = $this->get('fen_helper')->getBoardFromFEN($game->getBoard()->getFEN());
            $pieces = $this->get('html_helper')->getUnicodePieces($board);
        }

        return $this->render('Game/board.html.twig', array(
            'gameID' => $gameID,
            'pieces' => $pieces,
            'player' => $colour
        ));
    }

    /**
     * Get player's colour
     * User validity must already be checked
     *
     * @param Game $game
     * @param User $user
     *
     * @return string
     */
    private function getPlayerColour($game, $user)
    {
        if ($game->getPlayers()->get(0) == $user) {
            return 'w';
        }
        return 'b';
    }

    /**
     * Check game exists and is valid for user
     *
     * @param Game $game
     * @param User $user
     * @throws AccessDeniedException
     */
    private function checkGameValidity($game, $user)
    {
        if (!$game) {
            throw $this->createNotFoundException('Game not found!');
        }
        //make sure valid user for game
        if (!$game->getPlayers()->contains($user)) {
            throw new AccessDeniedException('You are not part of this game!');
        }
    }

    /**
     * Resign game
     * @param int $gameID
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function resignAction($gameID)
    {
        $user = $this->getUser();
        $em = $this->doctrine->getManager();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        //authenticate user/game
        $this->checkGameValidity($game, $user);
        $players = $game->getPlayers();
        $pIndex = $players->indexOf($user);
        $opIndex = 1 - $pIndex;
        //resign
        if (!$game->over()) {
            $game->setGameOver($opIndex, 'Game Over: ' . $user->getUsername() . ' has resigned');
            $em->flush();
        }
        return new JsonResponse();
    }

    /**
     * Toggle chat
     * @param int $gameID
     * @param int $player
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toggleChatAction($gameID, $player)
    {
        $user = $this->getUser();

        $em = $this->doctrine->getManager();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        if ($player == 'w') {
            $pIndex = 0;
        } else {
            $pIndex = 1;
        }
        if ($game->getPlayers()->get($pIndex) != $user) {
            throw new AccessDeniedException('You may not toggle your opponent\'s chat!');
        }
        $game->togglePlayerIsChatty($pIndex);
        $user->toggleChatty();
        $em->flush();

        return new JsonResponse();
    }

    /**
     * Create chat message
     * @param unknown $gameID
     * @param Request $request
     * @return JsonResponse
     */
    public function sendChatAction($gameID, Request $request)
    {
        $msg = $this->get('exercise_html_purifier.default')->purify($request->request->get('msg'));
        $em = $this->doctrine->getManager();
        $user = $this->getUser();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        $this->checkGameValidity($game, $user);
        //add username to message
        $msg = '<label>' . $user->getUsername() . ': </label> ' . $msg;
        $chat = new ChatMessage($game, $user, $msg);
        $em->persist($chat);
        $em->flush();

        return new JsonResponse(array('chatID' => $chat->getId()));
    }

    /**
     * Offer draw
     * @param int $gameID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function offerDrawAction($gameID)
    {
        $user = $this->getUser();
        $em = $this->doctrine->getManager();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        //authenticate user/game
        $this->checkGameValidity($game, $user);
        //offer draw
        $game->setDrawOfferer($game->getPlayers()->indexOf($user));
        $em->flush();

        return new JsonResponse();
    }

    /**
     * Accept draw
     * @param int $gameID
     */
    public function acceptDrawAction($gameID)
    {
        $em = $this->doctrine->getManager();
        $user = $this->getUser();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        $this->checkGameValidity($game, $user);
        $players = $game->getPlayers();
        $pIndex = $players->indexOf($user);
        //check draw was offered by opponent - set on receipt
        if ($game->getDrawOfferer() != $pIndex + 2) {
            throw new AccessDeniedException('You may not accept your own offer!');
        }
        //accept draw
        $game->setGameOver(2, "Game Over: Draw Accepted");
        $em->flush();

        return new JsonResponse();
    }

    /**
     * General listener
     * @param Request $request
     * @return JsonResponse
     */
    public function listenAction(Request $request)
    {
        $content = json_decode($request->getContent());
        $user = $this->getUser();
        $changed = false;
        //save session (allow multiple ajax calls)
        $this->requestStack->getSession()->save();
        set_time_limit(180);
        //get game
        $em = $this->doctrine->getManager();
        $gameID = $content->gameID;
        $lastSeen = $content->lastChat;
        //check if game over already received
        $overReceived = $content->overReceived;
        $opChatty = $content->opChatty;
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        $players = $game->getPlayers();
        $pIndex = $players->indexOf($user);
        $opIndex = 1 - $pIndex;
        $opponent = $players->get($opIndex);
        //check opponent has time left
        if ($game->getPlayerTime($opIndex) < 200) {
            $game->setGameOver($pIndex, 'Game Over: ' . $opponent->getUsername() . ' is out of time.');
            $em->flush();
        } else {
            //check opponent has moved within reasonable amount of time TODO: fix
            //$game = $this->checkFairPlay($game, $pIndex, $em);
        }
        //get all chat on reloads
        if ($game->getPlayerIsChatty($pIndex)) {
            if ($lastSeen == 0) {
                $chatMsgs = $em->getRepository(\CM\AppBundle\Entity\ChatMessage::class)->findAllGameChat($game);
            } else {
                $chatMsgs = $em->getRepository(\CM\AppBundle\Entity\ChatMessage::class)->findGamePlayerChat($opponent, $game, $lastSeen);
            }
        } else {
            $chatMsgs = array($lastSeen, array());
        }
        //wait for game over/new move/draw offered/chat
        $waited = 0;
        $em->refresh($game);
        //check for changes
        while (
            (!$game->over() || $overReceived)
            && !$game->newMoveReady($pIndex)
            && count($chatMsgs[1]) == 0
            && $game->getDrawOfferer() != $opIndex && $waited < 150
        ) {
            sleep(1);
            $em->refresh($game);
            if ($game->getPlayerIsChatty($pIndex)) {
                //get opponent's chat - own handled client-side & on reload
                $chatMsgs = $em->getRepository(\CM\AppBundle\Entity\ChatMessage::class)->findGamePlayerChat($opponent, $game, $lastSeen);
            }
            if (!$game->over() && $game->getPlayerTime($opIndex) < 200) {
                $game->setGameOver($pIndex, 'Game Over: ' . $opponent->getUsername() . ' is out of time.');
                $em->flush();
                $em->refresh($game);
            }
            $waited++;
        }
        $gameOver = $game->over();
        //still allow chat if game over
        $chatToggled = false;
        if ($game->getPlayerIsChatty($opIndex) != $opChatty) {
            //chat toggled
            $chatToggled = true;
            if ($opChatty) {
                $chatMsgs[1][] = '<span class="red">' . $opponent->getUsername() . ' has disabled chat.</span><br>';
            } else {
                $chatMsgs[1][] = '<span class="green">' . $opponent->getUsername() . ' has enabled chat.</span><br>';
            }
        }
        $chat = array('msgs' => $chatMsgs, 'toggled' => $chatToggled);
        //check if game is over
        if ($gameOver && !$overReceived) {
            $message = $game->getGameOverMessage();
            //get new ratings
            $players = $game->getPlayers();
            foreach ($players as $p) {
                $em->refresh($p);
            }
            //re-fetch users from database to ensure updated
            $repo = $em->getRepository(\CM\UserBundle\Entity\User::class);
            $pRating = $repo->find($players->get($pIndex)->getId())->getRating();
            $opRating = $repo->find($players->get($opIndex)->getId())->getRating();

            return new JsonResponse(array('change' => true, 'gameOver' => true, 'pRating' => $pRating, 'opRating' => $opRating, 'overMsg' => $message, 'chat' => $chat));
        }

        //check for draw offered
        if ($game->getDrawOfferer() == $opIndex) {
            $drawOffered = true;
            $game->setDrawOfferer($pIndex + 2);
            $em->flush();
            $changed = true;
        } else {
            $drawOffered = false;
        }
        //check for new move
        if ($game->newMoveReady($pIndex)) {
            //return opponent's move for validation
            $response = $this->getNewMoveResponse($game->getLastMove());
            $response['chat'] = $chat;
            $response['drawOffered'] = $drawOffered;
            $response['pTimeLeft'] = $game->getPlayerTime($pIndex);
            $response['opTimeLeft'] = $game->getPlayerTime($opIndex);
            return new JsonResponse($response);
        } elseif (count($chatMsgs[1]) > 0) {
            $changed = true;
        }

        return new JsonResponse(array('change' => $changed, 'gameOver' => false, 'chat' => $chat, 'drawOffered' => $drawOffered));
    }

    /**
     * Get last move details formatted for validation
     *
     * @param array $move
     * @return array
     */
    private function getNewMoveResponse(array $move)
    {
        return [
            'change' => true,
            'gameOver' => false,
            'moved' => true,
            'from' => $move['from'],
            'to' => $move['to'],
            'swapped' => $move['newPiece'],
            'enPassant' => $move['enPassant'],
            'castling' => $move['castling'],
            'newFEN' => $move['newFEN']
        ];
    }

    /**
     * Check for user disconnecting
     * @param Game $game
     * @param int $pIndex
     * @param EntityManager $em
     * @return Game
     */
    private function checkFairPlay(Game $game, $pIndex, EntityManager $em)
    {
        $opponent = $game->getPlayers()->get(1 - $pIndex);
        if (!$game->getLastMoveValidated()) {
            //user has moved/is waiting
            if ((round(microtime(true) * 1000) - $game->getLastMoveTime()) > 60) {
                $game->setGameOver($pIndex, "Game Aborted: " . $opponent->getUsername() . " has disconnected");
                $em->flush();
            }
        } elseif ($game->getActivePlayerIndex() != $pIndex) {
            //user has moved/is waiting
            $gameLength = $game->getLength();
            if ($gameLength < 601) {
                $timeOut = 5;
            } elseif ($gameLength < 1801) {
                $timeOut = 6;
            } else {
                $timeOut = 11;
            }
            if ($opponent->getLastActiveTime() < new \DateTime($timeOut . ' minutes ago')) {
                $game->setGameOver($pIndex, "Game Aborted: " . $opponent->getUsername() . " has disconnected");
                $em->flush();
            }
        }
        return $game;
    }
}
