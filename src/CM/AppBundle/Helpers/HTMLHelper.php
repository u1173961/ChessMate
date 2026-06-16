<?php

namespace CM\AppBundle\Helpers;

/**
 * HTMLHelper
 */
class HTMLHelper
{
    private static $defaultBoard = array(
                                            array('R','N','B','Q','K','B','N','R'),
                                            array('P','P','P','P','P','P','P','P'),
                                            array(false, false, false, false, false, false, false, false),
                                            array(false, false, false, false, false, false, false, false),
                                            array(false, false, false, false, false, false, false, false),
                                            array(false, false, false, false, false, false, false, false),
                                            array('p','p','p','p','p','p','p','p'),
                                            array('r','n','b','q','k','b','n','r')
                                        );


    private static $unicode = array(
                                        'r' => '&#9820;',
                                        'n' => '&#9822;',
                                        'b' => '&#9821;',
                                        'q' => '&#9819;',
                                        'k' => '&#9818;',
                                        'p' => '&#9823;',
                                        'R' => '&#9814;',
                                        'N' => '&#9816;',
                                        'B' => '&#9815;',
                                        'Q' => '&#9813;',
                                        'K' => '&#9812;',
                                        'P' => '&#9817;'
                                    );

   /**
    * Get unicode pieces and HTML selector ids
    *
    * @param array|bool $board
    * @return array
    */
    public static function getUnicodePieces($board = false)
    {
        if (!$board) {
            $board = self::$defaultBoard;
        }
        $pieces = array();
        foreach ($board as $row => $cols) {
            $pieces[$row] = array();
            foreach ($cols as $col => $piece) {
                if ($piece) {
                    $pieces[$row][$col] = array('id' => $piece . '_' . $row . $col, 'img' => self::$unicode[$piece]);
                } else {
                    $pieces[$row][$col] = false;
                }
            }
        }

        return $pieces;
    }

   /**
    * Get unicode taken pieces and HTML selector ids
    *
    * @param array $taken
    * @return array
    */
    public static function getUnicodeTakenPieces($taken)
    {
        $wTaken = array();
        $bTaken = array();
        foreach ($taken as $piece => $count) {
            if (strtoupper($piece) == $piece) {
                $wTaken[] = array('id' => $piece . '_t', 'img' => self::$unicode[$piece], 'count' => $count);
            } else {
                $bTaken[] = array('id' => $piece . '_t', 'img' => self::$unicode[$piece], 'count' => $count);
            }
        }

        return array($wTaken, $bTaken);
    }
}
