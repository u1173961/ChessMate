<?php

namespace CM\AppBundle\Controller;

use CM\AppBundle\Entity\Game;
use CM\AppBundle\Helpers\FENHelper;
use CM\AppBundle\Helpers\GameOverHelper;
use CM\AppBundle\Helpers\Validation\BishopValidator;
use CM\AppBundle\Helpers\Validation\KingValidator;
use CM\AppBundle\Helpers\Validation\KnightValidator;
use CM\AppBundle\Helpers\Validation\PawnValidator;
use CM\AppBundle\Helpers\Validation\QueenValidator;
use CM\AppBundle\Helpers\Validation\RookValidator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MoveController extends AbstractController
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'b_validator' => BishopValidator::class,
            'fen_helper' => FENHelper::class,
            'game_fin_helper' => GameOverHelper::class,
            'k_validator' => KingValidator::class,
            'n_validator' => KnightValidator::class,
            'p_validator' => PawnValidator::class,
            'q_validator' => QueenValidator::class,
            'r_validator' => RookValidator::class,
        ]);
    }

    private function get(string $service)
    {
        return $this->container->get($service);
    }

    /**
     * Set last move for retrieval/validation by opponent
     * If validity is not confirmed by opponent, server-side validation is conducted in subsequent call
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function sendMoveAction(Request $request)
    {
        $content = json_decode($request->getContent());
        //find game
        $em = $this->doctrine->getManager();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($content->gameID);
        if ($game->over()) {
            return new JsonResponse(array('sent' => false));
        }
        $user = $this->getUser();
        //make sure valid user for game & turn
        $player = $game->getPlayers()->indexOf($user);
        //check for attempted hacks that don't affect gameplay
        if ($player === false) {
            throw new AccessDeniedException('You are not part of this game!');
        }
        if ($game->getActivePlayerIndex() != $player) {
            throw new AccessDeniedException('It is not your turn!');
        }
        if (!$game->getLastMoveValidated()) {
            //attempt to make move without validating previous
            throw new AccessDeniedException('Stop messing about!');
        }
        $moveTime = round(microtime(true) * 1000);
        $game->setPlayerTime($player, $game->getPlayerTime($player)+100);
        $game->setLastMoveTime($moveTime);
        //get game over - if opponent disagrees, validate server-side
        $gameOver = $content->gameOver;
        //get move details
        $move = array(
            'by' => $player,
            'from' => $content->from,
            'to' => $content->to,
            'castling' => (array) $content->castling,
            'newFEN' => $content->fen,
            'enPassant' => $content->enPassant,
            'newPiece' => $content->newPiece,
            'gameOver' => $gameOver
        );
        //save move for validation by opponent
        $game->setLastMove($move);
        //mark move as unvalidated
        $game->setLastMoveValidated(false);
        $em->flush();

        return new JsonResponse(array('sent' => true));
    }

    /**
     * Save move if validated by opponent]
     * @param int $gameID
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function saveMoveAction($gameID, Request $request)
    {
        $content = json_decode($request->getContent());
        $gameOver = $content->gameOver;
        $user = $this->getUser();
        //find game
        $em = $this->doctrine->getManager();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        //switch active player
        $game->switchActivePlayer();
        //make sure valid user for game
        //& only allow save of opponent's move
        $player = $game->getPlayers()->indexOf($user);
        if ($player === false) {
            throw new AccessDeniedException('You are not part of this game!');
        } else if ($game->getActivePlayerIndex() != $player) {
            throw new AccessDeniedException('You may not save your own move!');
        }
        //get opponent's validated move
        $move = $game->getLastMove();
        //mark move as validated
        $game->setLastMoveValidated(true);
        //save move
        $this->saveMove($game, $move, $em);
        //check game over
        $em->refresh($game);
        $this->get('game_fin_helper')->checkGameOver($game, $gameOver, $move['gameOver'], $player, $em);

        return new JsonResponse(array('saved' => true));
    }

    /**
     * Save move
     * @param Game $game
     * @param array $move
     * @param unknown $em
     */
    private function saveMove(Game $game, array $move, $em)
    {
        //update game state
        $board = $game->getBoard();
        $taken = $this->get('fen_helper')->getPieceFromFEN($board->getFEN(), $move['to'][0], $move['to'][1]);
        if (!is_numeric($taken)) {
            $board->addTaken($taken);
        }
        $board->setFEN($move['newFEN']);
        $board->setEnPassant($move['enPassant']);
        $board->setCastling($move['castling']);
        $game->setBoard($board);
        $em->flush();
    }

    /**
     * Moves are validated client-side
     * If consensus on validity differs between players, the cheat is exposed
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function findCheatAction($gameID)
    {
        $user = $this->getUser();
        //find game
        $em = $this->doctrine->getManager();
        $game = $em->getRepository(\CM\AppBundle\Entity\Game::class)->find($gameID);
        //get user
        $player = $game->getPlayers()->indexOf($user);
        //get player that made move
        $mover = $game->getActivePlayerIndex();
        //get player that questioned validity
        $shaker = $game->getInactivePlayerIndex();
        //check for attempted hacks that don't affect gameplay
        if ($player === false) {
            throw new AccessDeniedException('You are not part of this game!');
        }
        if ($game->getLastMoveValidated()) {
            throw new AccessDeniedException('Move already validated!');
        }
        if ($player == $mover) {
            throw new AccessDeniedException('Stop messing about');
        }
        if ($game->over()) {
            throw new AccessDeniedException('The game is over');
        }
        $board = $game->getBoard();
        $fenHelper = $this->get('fen_helper');
        $absBoard = $fenHelper->getBoardFromFEN($board->getFEN());
        //get attempted move
        $attempted = $game->getLastMove();
        //$absBoard = $board->getBoard();
        $from = $attempted['from'];
        $to = $attempted['to'];
        //check piece exists at from square
        $piece = $absBoard[$from[0]][$from[1]];
        if (!$piece) {
            //cheat = player that made move
            $game->setGameOver($shaker, 'Game Aborted: '.$game->getPlayers()->get($mover)->getUsername().' has cheated.');
        } else {
            //get move details
            $move = array(
                'from' => $from,
                'to' => $to,
                'piece' => $piece,
                'castling' => $attempted['castling'],
                'colour' => $this->getPieceColour($piece),
                'enPassant' => $attempted['enPassant'],
                'newPiece' => $attempted['newPiece'],
                'newBoard' => $fenHelper->getBoardFromFEN($attempted['newFEN'])
            );
            //make sure right colour moved
            if ($move['colour'] == 'w' and $mover == 1 || $move['colour'] == 'b' and $mover == 0) {
                $game->setGameOver($shaker, 'Game Aborted: '.$game->getPlayers()->get($mover)->getUsername().' has cheated.');
            } else {
                //get piece validator
                $validator = $this->get(strtolower($move['piece']).'_validator');
                //validate move
                $valid = $validator->validateMove($move, $game, $absBoard);
                if ($valid['valid']) {
                    //cheater = player that questioned validity
                    $game->setGameOver($mover, 'Game Aborted: '.$game->getPlayers()->get($shaker)->getUsername().' has cheated.');
                    //save validated move
                    $this->saveMove($game, $attempted, $em);
                } else {
                    //cheat = inactive player i.e. player that made move
                    $game->setGameOver($shaker, 'Game Aborted: '.$game->getPlayers()->get($mover)->getUsername().' has cheated.');
                }
            }
        }
        //mark move as validated
        $game->setLastMoveValidated(true);
        $em->flush();

        return new JsonResponse(array('cheat' => true));
    }

    /**
     * Get piece colour
     * @param string $piece
     * @return string
     */
    private function getPieceColour(string $piece)
    {
        if (strtoupper($piece) == $piece) {
            return 'w';
        }
        return 'b';
    }
}
