//set positioning of letters
var posOf = {'a': 1,'b': 2,'c': 3,'d': 4,'e': 5,'f': 6,'g': 7,'h': 8};
var letterAt = ['a','b','c','d','e','f','g','h'];

//allow abstract validation
if (typeof activePlayer === 'undefined') {
    //create default board state (for non-games i.e. practice/computer opponent)
    var abstractBoard = [
                        ['R','N','B','Q','K','B','N','R'],
                        ['P','P','P','P','P','P','P','P'],
                        [false, false, false, false, false, false, false, false],
                        [false, false, false, false, false, false, false, false],
                        [false, false, false, false, false, false, false, false],
                        [false, false, false, false, false, false, false, false],
                        ['p','p','p','p','p','p','p','p'],
                        ['r','n','b','q','k','b','n','r']
                    ];
    var enPassant = null;
    //castling
    var castling = {'w': 'KQ', 'b': 'kq'};
}
//efficiency variables
var castled = false;
var enPassantPerformed = false;
var checkThreat = null;

/**
 * Resolve grid reference to array indices
 * @param x alphabet ref
 * @param y numeric ref
 *
 * @return the corresponding [row,column] index in abstractBoard array
 */
function getAbstractIndicesFromGridRef(x, y)
{
    return [y - 1, posOf[x] - 1];
}

/**
 * Resolve array index to grid reference
 * @param y row in abstractBoard
 * @param x column in abstractBoard
 *
 * @return the corresponding grid reference
 */
function getGridRefFromAbstractIndices(y, x)
{
    //get alpha x
    x = letterAt[x];
    //translate to grid ref
    return x + '_' + (parseInt(y, '10') + 1);
}

/**
 * Get piece colour
 * @param piece
 * @returns char
 */
function getPieceColour(piece)
{
    if (piece.toUpperCase() == piece) {
        return 'w';
    }
    return 'b';
}

/**
 * Get player's piece (uppercase for white)
 * @param colour
 * @param piece
 * @returns
 */
function getPlayerPiece(colour, piece)
{
    if (colour == 'w') {
        return piece.toUpperCase();
    }
    return piece;
}

/**
 * Get opponent's colour
 */
function getOpponentColour(colour)
{
    if (colour == 'w') {
        return 'b';
    }
    return 'w';
}

/**
 * Update abstract board,
 * handles taking automatically
 * @param from  [y,x]
 * @param to    [y,x]
 */
function updateAbstractBoard(from, to)
{
    abstractBoard[to[0]][to[1]] = abstractBoard[from[0]][from[1]];
    abstractBoard[from[0]][from[1]] = false;
}

//---------------------------------------- movement validation --------------------------------------------//

/**
 * Get validation for different pieces
 * @param colour 'w'/'b'
 * @param from  [y,x]
 * @param to    [y,x]
 */
function validatePieceType(piece, colour, from, to)
{
    var piece = piece.toUpperCase();
    if (piece == 'P') {
        return validatePawn(colour, from, to);
    }
    if (piece == 'R') {
        return validateRook(from, to);
    }
    if (piece == 'N') {
        return validateKnight(from, to);
    }
    if (piece == 'B') {
        return validateBishop(from, to);
    }
    if (piece == 'Q') {
        return validateQueen(from, to);
    }
    if (piece == 'K') {
        return validateKing(colour, from, to);
    }
    return false;
}

/**
 * Validate rook movement
 * @param from  [y,x]
 * @param to    [y,x]
 */
function validateRook(from, to)
{
    if ((from[0] == to[0] && !xAxisBlocked(from[1], to[1], from[0]))
        || (from[1] == to[1] && !yAxisBlocked(from[0], to[0], from[1]))) {
        return true;
    }

    return false;
}

/**
 * Validate knight movement
 * @param from  [y,x]
 * @param to    [y,x]
 */
function validateKnight(from, to)
{
    if (((to[0] - from[0]) * (to[0] - from[0])) + ((to[1] - from[1]) * (to[1] - from[1])) == 5) {
        return true;
    }
    return false;
}

/**
 * Validate bishop movement
 * @param from  [y,x]
 * @param to    [y,x]
 */
function validateBishop(from, to)
{
    if (onDiagonal(from, to) && !diagonalBlocked(from[1], from[0], to[1], to[0])) {
        return true;
    }
    return false;
}

/**
 * Validate queen movement
 * @param from  [y,x]
 * @param to    [y,x]
 */
function validateQueen(from, to)
{
    if ((from[0] == to[0] && !xAxisBlocked(from[1], to[1], from[0]))
        || (from[1] == to[1] && !yAxisBlocked(from[0], to[0], from[1]))
        || (onDiagonal(from, to) && !diagonalBlocked(from[1], from[0], to[1], to[0]))) {
        return true;
    }
    return false;
}

/**
 * Validate king movement
 * @param from  [y,x]
 * @param to    [y,x]
 */
function validateKing(colour, from, to)
{
    //get king's position
    var kingSquare = getKingSquare(colour);
    if (Math.abs(to[1] - from[1]) <= 1 && Math.abs(to[0] - from[0]) <= 1) {
        return true;
    }
    if (castling[colour].length > 0 && to[0] == from[0] && !inCheck(getOpponentColour(colour), kingSquare)) {
        //handle castling
        if ((to[1] == 2 && castling[colour].indexOf(getPlayerPiece(colour, 'q')) > -1)
            || (to[1] == 6 && castling[colour].indexOf(getPlayerPiece(colour, 'k')) > -1)) {
            var rookFromCol = 7;
            var start = 5;
            var end = 7;
            var rookToCol = 5;
            if (to[1] == 2) {
                //long castle
                if (!vacant(from[0], 1)) {
                    //extra square rook travels is occupied
                    return false;
                }
                rookFromCol = 0;
                start = 2;
                end = 4;
                rookToCol = 3;
            }
            //check intermittent points are vacant
            for (var i = start; i < end; i++) {
                if (!vacant(from[0], i)) {
                    return false;
                }
                // if in check at intermittent points, return false
                var nextSpace = [from[0], i];
                updateAbstractBoard(from, nextSpace);
                //get king's position
                var kingSquare = getKingSquare(colour);
                if (inCheck(getOpponentColour(colour), kingSquare)) {
                    //put king back in place
                    updateAbstractBoard(nextSpace, from);
                    return false;
                }
                //put king back in place
                updateAbstractBoard(nextSpace, from);
            }
            //move rook
            updateAbstractBoard([from[0], rookFromCol], [to[0], rookToCol]);
            //flag castled - prevent recheck of inCheck()
            castled = true;
            return true;
        }
    }
    return false;
}

/**
 * Validate pawn movement
 * @param from  [y,x]
 * @param to    [y,x]
 */
function validatePawn(colour, from, to)
{
    var spaces = 1;
    if ((colour == 'w' && from[0] == 1) || (colour == 'b' && from[0] == 6)) {
        //allow initial movement of 2 spaces
        spaces = 2;
    }
    //allow moving forward
    var dir = to[0] - from[0];
    var move = Math.abs(dir);
    if (vacant(to[0], to[1]) && from[1] == to[1] && move <= spaces) {
        if ((colour == 'w' && to[0] > from[0]) || (colour == 'b' && to[0] < from[0])) {
            return true;
        }
    } else if (onDiagonal(from, to)
        && ((colour == 'w' && dir == 1) || (colour == 'b' && dir == -1))) {
        if (checkTakePiece(to, colour)) {
            return true;
        }
        if (enPassant && enPassant[0] == to[0] && enPassant[1] == to[1]) {
            //perform En passant
            //allow revert if in check
            var epTaken = abstractBoard[from[0]][to[1]];
            abstractBoard[from[0]][to[1]] = false;
            //update abstract board
            updateAbstractBoard(from, to);
            //get king's position
            var kingSquare = getKingSquare(colour);
            if (inCheck(getOpponentColour(colour), kingSquare)) {
                //revert
                updateAbstractBoard(to, from);
                abstractBoard[to[0]][to[1]] = epTaken;
                return false;
            }
            enPassantPerformed = true;
            enPassant = null;
            return true;
        }
    }
    return false;
}

/**
 * Check given piece is at given row/col
 */
function pieceAt(row, column, piece)
{
    if (row > -1 && row < 8 && column > -1 && column < 8) {
        if (abstractBoard[row][column] == piece) {
            return true;
        }
    }
    return false;
}

/**
 * Get piece/false at given row/col
 */
function getPieceAt(row, column)
{
    if (row > -1 && row < 8 && column > -1 && column < 8) {
        return abstractBoard[row][column];
    }
    return false;
}

/**
 * Check if x-axis squares are blocked
 * @param from  y1
 * @param to    y2
 * @param row
 */
function xAxisBlocked(from, to, row)
{
    //get x-axis direction
    var range = Math.abs(to - from);
    var x = (to - from) / range;
    //check squares inbetween are empty
    for (var i = 1; i < range; i++) {
        if (abstractBoard[row][from + (i * x)]) {
            return true;
        }
    }

    return false;
}

/**
 * Check if y-axis squares are blocked
 * @param from  x1
 * @param to    x2
 * @param column
 */
function yAxisBlocked(from, to, column)
{
    //get y-axis direction
    var range = Math.abs(to - from);
    var y = (to - from) / range;
    //check squares inbetween are empty
    for (var i = 1; i < range; i++) {
        if (abstractBoard[from + (i * y)][column]) {
            return true;
        }
    }

    return false;
}

/**
 * Check if diagonal squares are blocked
 */
function diagonalBlocked(fromX, fromY, toX, toY)
{
    var range = Math.abs(fromX - toX);
    //get x-axis direction
    var xDir = (toX - fromX) / range;
    //get y-axis direction
    var yDir = (toY - fromY) / range;
    //check squares inbetween are empty
    for (var i = 1; i < range; i++) {
        if (abstractBoard[fromY + (i * yDir)][fromX + (i * xDir)]) {
            return true;
        }
    }

    return false;
}

/**
 * check if target square is diagonal with source
 * @param from  [y,x]
 * @param to    [y,x]
 *
 * @return Boolean
 */
function onDiagonal(from, to)
{
    return Math.abs(to[0] - from[0]) == Math.abs(to[1] - from[1]);
}

/**
 * Check if target square is unoccupied
 */
function vacant(row, column)
{
    return abstractBoard[row][column] === false;
}

/**
 * Check if target square is occupied by own piece
 */
function occupiedByOwnPiece(row, column, colour)
{
    if (row > -1 && row < 8 && column > -1 && column < 8) {
        if (!vacant(row, column) && getPieceColour(abstractBoard[row][column]) == colour) {
            return true;
        }
    }

    return false;
}

/**
 * Check if target square is occupied by other piece
 */
function occupiedByOtherPiece(row, column, colour)
{
    if (row > -1 && row < 8 && column > -1 && column < 8) {
        if (!vacant(row, column) && getPieceColour(abstractBoard[row][column]) != colour) {
            return true;
        }
    }

    return false;
}

/**
 * Check for takeable piece
 */
function checkTakePiece(square, colour)
{
    return occupiedByOtherPiece(square[0], square[1], colour);
}

/**
 * Update castling availability for player (FEN)
 * @param moved
 * @param colour
 * @param fRow
 * @param fCol
 * @returns String
 */
function updateCastling(moved, colour, fRow, fCol)
{
    //get player's castling privileges
    var pCastling = castling[colour];
    if (pCastling.length > 0) {
        if (moved == 'k' || moved == 'K') {
            pCastling = '';
        } else if (
            (fCol == 0 || fCol == 7)
            && (
                (moved == 'r' && fRow == 7)
                || (moved == 'R' && fRow == 0)
            )
        ) {
            if (pCastling.length > 1) {
                //castle possible on both sides
                pCastling = pCastling.charAt(fCol == 0 ? 0 : 1);
            } else if (pCastling == 'K' || pCastling == 'k') {
                //castle king-side possible
                if (fCol == 7) {
                    //castle no longer possible
                    pCastling = '';
                }
            } else if (fCol == 0) {
                //queen-side castle no longer possible
                pCastling = '';
            }
        }
        castling[colour] = pCastling;
    }
}

/**
 * Set/unset En passant availability
 */
function setEnPassant(moved, fRow, fCol, tRow)
{
    if (moved == 'p' && fRow == 6 && tRow == 4) {
        enPassant = [5, fCol];
    } else if (moved == 'P' && fRow == 1 && tRow == 3) {
        enPassant = [2, fCol];
    } else {
        enPassant = null;
    }
}

/**
 * Check En passant has been performed
 */
function checkEnPassantPerformed()
{
    if (enPassantPerformed) {
        enPassantPerformed = false;
        return true;
    }
    return false;
}

//---------------------------------------- in check validation --------------------------------------------//

/**
 * Get king's indices on abstract board
 */
function getKingSquare(colour)
{
    var king = getPlayerPiece(colour, 'k');
    //get king's position
    var kingSquare = 0;
    for (var row = 0; row < 8; row++) {
        var col = $.inArray(king, abstractBoard[row]);
        if (col !== -1) {
            return [row, col];
        }
    }
}

/**
 * Check if king is in check
 */
function inCheck(opColour, kingSquare)
{
    //check if in check
    return (inCheckOnDiagonal(opColour, kingSquare) || inCheckByKnight(opColour, kingSquare)
            || inCheckOnXAxis(opColour, kingSquare) || inCheckOnYAxis(opColour, kingSquare)
            || inCheckByPawn(opColour, kingSquare));
}

/**
 * Check if in check on diagonal
 */
function inCheckOnDiagonal(colour, kingSquare)
{
    var row = kingSquare[0];
    var col = kingSquare[1];
    var blocks = [false,false,false,false];
    var bishop = getPlayerPiece(colour, 'b');
    var queen = getPlayerPiece(colour, 'q');
    for (var i = 1; i < 8; i++) {
        var threats = [
            getPieceAt(row + i, col - i),
            getPieceAt(row + i, col + i),
            getPieceAt(row - i, col - i),
            getPieceAt(row - i, col + i)
        ];
        if (!blocks[0] && (threats[0] == bishop || threats[0] == queen)) {
            checkThreat = [row + i, col - i];
            return true;
        }
        if (!blocks[1] && (threats[1] == bishop || threats[1] == queen)) {
            checkThreat = [row + i, col + i];
            return true;
        }
        if (!blocks[2] && (threats[2] == bishop || threats[2] == queen)) {
            checkThreat = [row - i, col - i];
            return true;
        }
        if (!blocks[3] && (threats[3] == bishop || threats[3] == queen)) {
            checkThreat = [row - i, col + i];
            return true;
        }
        //get blocking pieces
        for (var j = 0; j < 4; j++) {
            if (!blocks[j]) {
                blocks[j] = threats[j];
            }
        }
    }
    return false;
}

/**
 * Check if in check on x-axis
 */
function inCheckOnXAxis(colour, kingSquare)
{
    var row = kingSquare[0];
    var rook = getPlayerPiece(colour, 'r');
    var queen = getPlayerPiece(colour, 'q');
    //radiate out (for checkmates)
    for (var col = kingSquare[1] - 1; col >= 0; col--) {
        if (abstractBoard[row][col] == rook || abstractBoard[row][col] == queen) {
            if (!xAxisBlocked(kingSquare[1], col, row)) {
                checkThreat = [row, col];
                return true;
            }
        }
    }
    for (var col = kingSquare[1] + 1; col < 8; col++) {
        if (abstractBoard[row][col] == rook || abstractBoard[row][col] == queen) {
            if (!xAxisBlocked(kingSquare[1], col, row)) {
                checkThreat = [row, col];
                return true;
            }
        }
    }
    return false;
}

/**
 * Check if in check on y-axis
 */
function inCheckOnYAxis(colour, kingSquare)
{
    var col = kingSquare[1];
    var rook = getPlayerPiece(colour, 'r');
    var queen = getPlayerPiece(colour, 'q');
    //radiate out
    for (var row = kingSquare[0] - 1; row >= 0; row--) {
        if (abstractBoard[row][col] == rook || abstractBoard[row][col] == queen) {
            if (!yAxisBlocked(kingSquare[0], row, col)) {
                checkThreat = [row, col];
                return true;
            }
        }
    }
    for (var row = kingSquare[0] + 1; row < 8; row++) {
        if (abstractBoard[row][col] == rook || abstractBoard[row][col] == queen) {
            if (!yAxisBlocked(kingSquare[0], row, col)) {
                checkThreat = [row, col];
                return true;
            }
        }
    }
    return false;
}

/**
 * Check if king is in check by knight
 */
function inCheckByKnight(colour, kingSquare)
{
    var x = kingSquare[1];
    var y = kingSquare[0];
    var knight = getPlayerPiece(colour, 'n');
    if (pieceAt(y + 2, x - 1, knight)) {
        checkThreat = [y + 2, x - 1];
        return true;
    }
    if (pieceAt(y + 2, x + 1, knight)) {
        checkThreat = [y + 2, x + 1];
        return true;
    }
    if (pieceAt(y + 1, x - 2, knight)) {
        checkThreat = [y + 1, x - 2];
        return true;
    }
    if (pieceAt(y + 1, x + 2, knight)) {
        checkThreat = [y + 1, x + 2];
        return true;
    }
    if (pieceAt(y - 1, x - 2, knight)) {
        checkThreat = [y - 1, x - 2];
        return true;
    }
    if (pieceAt(y - 1, x + 2, knight)) {
        checkThreat = [y - 1, x + 2];
        return true;
    }
    if (pieceAt(y - 2, x - 1, knight)) {
        checkThreat = [y - 2, x - 1];
        return true;
    }
    if (pieceAt(y - 2, x + 1, knight)) {
        checkThreat = [y - 2, x + 1];
        return true;
    }
    return false;
}

/**
 * Check if king is in check by pawn
 */
function inCheckByPawn(colour, kingSquare)
{
    var dir = 1;
    if (colour == 'w') {
        dir = -1;
    }
    var pawn = getPlayerPiece(colour, 'p');
    if (pieceAt(kingSquare[0] + dir, kingSquare[1] - 1, pawn)) {
        checkThreat = [kingSquare[0] + dir, kingSquare[1] - 1];
        return true;
    }
    if (pieceAt(kingSquare[0] + dir, kingSquare[1] + 1, pawn)) {
        checkThreat = [kingSquare[0] + dir, kingSquare[1] + 1];
        return true;
    }
    return false;
}

//---------------------------------------- game over validation --------------------------------------------//

/**
 * Check if last move ended the game
 * @param string colour
 * @return boolean|int
 */
function checkGameOver(colour)
{
    //get opponent's colour
    var opColour = getOpponentColour(colour);
    //get opponent's king's square
    var kingSquare = getKingSquare(opColour);
    var king = getPlayerPiece(opColour, 'k');
    //check for draw
    var alliesLeft = getAlliesLeft(opColour);
    if (!alliesLeft && !getAlliesLeft(colour)) {
        //Drawn
        return 1;
    }
    //get reachable squares
    var reachables = getReachableSquaresForKing(kingSquare, opColour);
    //remove king
    abstractBoard[kingSquare[0]][kingSquare[1]] = false;
    //check if any reachable squares are safe
    for (var i = 0; i < reachables.length; i++) {
        if (!inCheck(colour, reachables[i])) {
            //put king back
            abstractBoard[kingSquare[0]][kingSquare[1]] = king;
            return false;
        }
    }
    //--> no safe squares within reach
    //put king back
    abstractBoard[kingSquare[0]][kingSquare[1]] = king;
    //check if in check
    if (!inCheck(colour, kingSquare)) {
        //check for stalemate
        if (!alliesLeft) {
            //Stalemate
            return 2;
        }
        return false;
    }
    //--> in check
    //check if more than one threat from different angles
    var cThreat = checkThreat.slice();
    //replace threat with blocker
    var threat = abstractBoard[cThreat[0]][cThreat[1]];
    abstractBoard[cThreat[0]][cThreat[1]] = 'x';
    //if still in check, threat cannot be taken or blocked
    if (inCheck(colour, kingSquare)) {
        //restore board state
        abstractBoard[cThreat[0]][cThreat[1]] = threat;
        //Checkmate
        return 3;
    }
    //--> only one active threat
    //restore board state
    abstractBoard[cThreat[0]][cThreat[1]] = threat;
    if (threat == getPlayerPiece(opColour, 'n')) {
        //get copy of  board
        var board = getBoardCopy();
        //attempt to take knight
        if (!getSquareIsReachableWithoutCausingCheck(cThreat, opColour, colour, kingSquare)) {
            //knight not takeable - revert board
            abstractBoard = board;
            //Checkmate
            return 3;
        }
    } else {
        //attempt to block/take other threats
        if (cThreat[0] == kingSquare[0]) {
            //horizontal check
            if (!checkOnXAxisIsDefendable(cThreat[1], kingSquare[1], cThreat[0], opColour, colour)) {
                //Checkmate
                return 3;
            }
        } else if (cThreat[1] == kingSquare[1]) {
            //vertical check
            if (!checkOnYAxisIsDefendable(cThreat[0], kingSquare[0], cThreat[1], opColour, colour)) {
                //Checkmate
                return 3;
            }
        } else {
            //diagonal check
            if (!checkOnDiagIsDefendable(cThreat[1], cThreat[0], kingSquare[1], kingSquare[0], opColour, colour)) {
                //Checkmate
                return 3;
            }
        }
    }
    return false;
}

function getBoardCopy()
{
    var board = [];
    for (var i = 0; i < 8; i++) {
        board[i] = abstractBoard[i].slice();
    }
    return board;
}

/**
 * Check if given colour has any pieces other than king
 * @param string colour
 * @return bool
 */
function getAlliesLeft(colour)
{
    for (var row = 0; row < 8; row++) {
        for (var col = 0; col < 8; col++) {
            var piece = abstractBoard[row][col];
            if (piece && getPieceColour(piece) == colour && piece != getPlayerPiece(colour, 'k')) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Get array of indices for squares reachable by king
 * @param array kingSquare
 * @param string colour
 * @return array
 */
function getReachableSquaresForKing(kingSquare, colour)
{
    //get reachable squares
    var reachables = [];
    //avoid out of bounds
    var rowStart = kingSquare[0];
    var rowEnd = kingSquare[0];
    if (rowStart > 0) {
        rowStart--;
        if (rowEnd < 7) {
            rowEnd++;
        }
    }
    var colStart = kingSquare[1];
    var colEnd = kingSquare[1];
    if (colStart > 0) {
        colStart--;
        if (colEnd < 7) {
            colEnd++;
        }
    }
    for (var row = rowStart; row <= rowEnd; row++) {
        for (var col = colStart; col <= colEnd; col++) {
            var occupant = abstractBoard[row][col];
            if (!occupant || getPieceColour(occupant) != colour) {
                reachables.push([row, col]);
            }
        }
    }
    return reachables;
}

/**
 * Check if target square is reachable by player in check
 * Used for escaping check i.e. blocking/taking attacking piece.
 * Changes are made to the global board and should be reverted post-execution
 * Moves must not cause new check
 * @param array target
 * @param string colour
 * @param string opColour
 * @param array kingSquare
 * @return boolean
 */
function getSquareIsReachableWithoutCausingCheck(target, colour, opColour, kingSquare)
{
    //Note: inCheck() here, just means reachable
    while (inCheck(colour, target)) {
        //check can be blocked/taken, provided moving does not cause new check
        var source = checkThreat;
        //move defender to empty space
        updateAbstractBoard(source, target);
        if (!inCheck(opColour, kingSquare)) {
            //checker blockable/takeable
            return true;
        }
        //new check created
        //defender cannot be moved, ignore in further attempts
        abstractBoard[target[0]][target[1]] = false;
        abstractBoard[source[0]][source[1]] = 'x';
    }
    return false;
}

/**
 * Check if check on x-axis is defendable
 * inCheck() is used to identify reachable squares
 * @param int  from         x1, Checker's col
 * @param int  to           x2, King's col
 * @param int  row          y, Checker/King's row
 * @param string colour     Player in check
 * @param string opColour   Player causing check
 *
 * @return boolean
 */
function checkOnXAxisIsDefendable(from, to, row, colour, opColour)
{
    //get copy of  board
    var board = getBoardCopy();
    //get x-axis direction
    var range = Math.abs(to - from);
    var x = (to - from) / range;
    var kingSquare = [row, to];
    //check if squares inbetween are defendable
    for (var i = 0; i < range; i++) {
        var blockTo = [row, from + (i * x)];
        if (getSquareIsReachableWithoutCausingCheck(blockTo, colour, opColour, kingSquare)) {
            //checker is blockable/takeable - revert board
            abstractBoard = board;
            return true;
        }
    }
    //check not blockable - revert
    abstractBoard = board;

    return false;
}

/**
 * Check if check on y-axis is defendable
 * inCheck() is used to identify reachable squares
 * @param int  from         y1, Checker's row
 * @param int  to           y2, King's row
 * @param int  col          x, Checker/King's col
 * @param string colour     Player in check
 * @param string opColour   Player causing check
 *
 * @return boolean
 */
function checkOnYAxisIsDefendable(from, to, col, colour, opColour)
{
    //get copy of  board
    var board = getBoardCopy();
    //get y-axis direction
    var range = Math.abs(to - from);
    var y = (to - from) / range;
    var kingSquare = [to, col];
    //check if squares inbetween are defendable
    for (var i = 0; i < range; i++) {
        var blockTo = [from + (i * y), col];
        if (getSquareIsReachableWithoutCausingCheck(blockTo, colour, opColour, kingSquare)) {
            //checker is blockable/takeable - revert board
            abstractBoard = board;
            return true;
        }
    }
    //check not blockable - revert
    abstractBoard = board;

    return false;
}

/**
 * Check if check on diagonal is defendable
 * inCheck() is used to identify reachable squares
 * @param int  fromX        Checker's col
 * @param int  fromY        Checker's row
 * @param int  toX          King's col
 * @param int  toY          King's row
 * @param string colour     Player in check
 * @param string opColour   Player causing check
 *
 * @return boolean
 */
function checkOnDiagIsDefendable(fromX, fromY, toX, toY, colour, opColour)
{
    //get copy of  board
    var board = getBoardCopy();
    //get range
    var range = Math.abs(fromX - toX);
    //get x-axis direction
    var xDir = (toX - fromX) / range;
    //get y-axis direction
    var yDir = (toY - fromY) / range;
    var kingSquare = [toY, toX];
    //check if squares inbetween are defendable
    for (var i = 0; i < range; i++) {
        var blockTo = [fromY + (i * yDir), fromX + (i * xDir)];
        if (getSquareIsReachableWithoutCausingCheck(blockTo, colour, opColour, kingSquare)) {
            //checker is blockable/takeable - revert board
            abstractBoard = board;
            return true;
        }
    }
    //check not blockable - revert
    abstractBoard = board;

    return false;
}
