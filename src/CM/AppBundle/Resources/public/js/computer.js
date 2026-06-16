//difficulty
var searchDepth = 6;

$(document).ready(function () {
    if (typeof worker !== 'undefined') {
        worker.addEventListener('message', function (e) {
            if (e.data.substr(0, 8) == 'bestmove') {
                //get move
                var msg = e.data.split(' ');
                performComputerMove(msg[1]);
            }
        }, false);
    }

    /**
     * Dialog settings e.g. piece-chooser
     */
    $('.ui-dialog').dialog({
        autoOpen: false,
        closeOnEscape: false,
        open: function (event, ui) {
            $(".ui-dialog-titlebar-close").hide();
        },
        show: {
            effect: "blind",
            duration: 1000
        },
        hide: {
            effect: "explode",
            duration: 1000
        },
        position: {
            my: "center center",
            at: "center center",
            of: ".board"
        },
        modal: true,
    });

    //set player default white
    $('.board').attr('id', 'game_w_x');
    //set skill level
    if (difficulty == 1) {
        setDifficulty(2);
    } else if (difficulty == 3) {
        setDifficulty(7);
    } else {
        setDifficulty(4);
    }

    //change skill level
    $('#difficultySlider').slider({
        min: 1,
        max: 10,
        value: (searchDepth + 1) / 2,
        animate: 'fast',
        slide: function ( event, ui ) {
            setDifficulty(ui.value);
            $('#difficultySlider').attr('title', ui.value);
        }
    }).attr("title", (searchDepth + 1) / 2);

    var currColour = 0;
    $('#restart').on('click', function () {
        resetState(currColour === 0 ? 'w' : 'b');
    });
    $('#switchSides').on('click', function () {
        resetState(currColour === 0 ? 'b' : 'w');
        switchPiecesWonLost();
        currColour = 1 - currColour;
    });
});

/**
 * Set search depth
 * @param skill
 * @returns
 */
function setDifficulty(skill)
{
    var diff;
    if (skill == 1) {
        diff = 'Very Easy';
    } else if (skill < 4) {
        diff = 'Easy';
    } else if (skill < 6) {
        diff = 'Average';
    } else if (skill < 8) {
        diff = 'Hard';
    } else {
        diff = 'Very Hard';
    }
    $('#diffLabel').html('(' + diff + ')');
    searchDepth = Math.min((skill * 2) - 1, 19);
}

/**
 * Update FEN and send to computer opponent
 * @param from
 * @param to
 */
function switchToComputerOpponent(from, to)
{
    playersTurn = false;
    updateFENSuffixes(activeColour, from[0], from[1], to[0]);
    fen = updateFEN(fen, from, to);
    worker.postMessage('position fen ' + getFEN(fen, activeColour, getCastlingFEN(), ep, halfMoves, fullMoves));
    worker.postMessage('go depth ' + searchDepth);
}

/**
 * Perform computer move
 * @param move algebraic notation
 * @returns
 */
function performComputerMove(move)
{
    //update FEN
    var from = [parseInt(move.charAt(1), 10) - 1, posOf[move.charAt(0)] - 1];
    var to = [parseInt(move.charAt(3), 10) - 1, posOf[move.charAt(2)] - 1];
    var colour = activeColour;
    updateFENSuffixes(activeColour, from[0], from[1], to[0]);
    fen = updateFEN(fen, from, to);
    updateComputerCastling(from);
    var piece = getPieceFromFEN(fen, to[0], to[1]);
    setEnPassant(piece, from[0], from[1], to[0]);
    //get grid refs.
    var gridFrom = getGridRefFromAbstractIndices(from[0], from[1]);
    var gridTo = getGridRefFromAbstractIndices(to[0], to[1]);
    //check for takeable
    if (abstractBoard[to[0]][to[1]]) {
        //take piece
        takePiece(gridTo, 'Lost');
    }
    //update abstract board
    updateAbstractBoard(from, to);
    var moved = getOccupant(gridFrom);
    //check for reaching other side
    if (pawnHasReachedOtherSide(piece, colour, to[0])) {
        //give queen
        var queen = getPlayerPiece(colour, 'q');
        abstractBoard[to[0]][to[1]] = queen;
        var num = getNewPieceNumber(queen);
        //change piece
        moved.html($('#pick_' + queen).html());
        //set new id
        moved.attr('id', queen + '_' + num);
        //update fen
        fen = swapPieceInFEN(fen, queen, to);
    }
    //move piece
    moved.position({
        of: 'div#' + gridTo
    });
    //center piece
    $('div#' + gridTo).append(moved.css('position','static'));
    highlightMove(gridFrom, gridTo);
    //check for game over
    var over = checkGameOver(colour);
    if (over) {
        alert(getGameOverMessage(over));
        gameOver = true;
    } else {
        playersTurn = true;
    }
}

/**
 * Update FEN with move
 * @param fen
 * @param from [row,col]
 * @param to [row,col]
 */
function updateFEN(fen, from, to)
{
    var split = fen.split('/');
    //get 'from' row
    var fRow = split[7 - from[0]];
    var fCol = getFenIndex(fRow, from[1]);
    //update 'from' row
    split[7 - from[0]] = updateFRowFEN(fRow, fCol);
    //get moved piece
    var moved = fRow.charAt(fCol);
    //get 'to' row
    var tRow = split[7 - to[0]];
    var tCol = getFenIndex(tRow, to[1]);
    //update 'to' row
    split[7 - to[0]] = updateTRowFEN(tRow, tCol, to[1], moved);
    //check for castling
    if (moved == 'k' || moved == 'K') {
        if (Math.abs(from[1] - to[1]) == 2) {
            //only possible if castling
            if (moved == 'K') {
                var rook = 'R';
            } else {
                var rook = 'r';
            }
            //get row again
            tRow = split[7 - to[0]];
            if (from[1] < to[1]) {
                //king-side
                var rookFCol = 7;
                var rookTCol = 5;
                fCol = getFenIndex(tRow, 5);
                tRow = tRow.substr(0, fCol) + (parseInt(tRow.charAt(fCol), 10) - 1) + rook + tRow.charAt(fCol + 1) + '1';
            } else {
                //queen-side
                var rookFCol = 0;
                var rookTCol = 3;
                tRow = '2' + tRow.charAt(2) + rook + '1' + tRow.substr(4, tRow.length - 4);
            }
            //move castle
            split[7 - to[0]] = tRow;
            //update abstract & actual board if computer move
            if (!abstractBoard[from[0]][rookTCol]) {
                updateAbstractBoard([from[0], rookFCol], [to[0], rookTCol]);
                //get grid refs.
                var gridFrom = getGridRefFromAbstractIndices(from[0], rookFCol);
                var gridTo = getGridRefFromAbstractIndices(to[0], rookTCol);
                var moved = getOccupant(gridFrom);
                moved.position({
                    of: 'div#' + gridTo
                });
                //center piece
                $('div#' + gridTo).append(moved.css('position','static'));
            }
        }
    }

    return split.join('/');
}

function updateFENSuffixes(colour, fRow, fCol, tRow)
{
    var split = fen.split('/');
    var fenRow = split[7 - fRow];
    var moved = fenRow.charAt(getFenIndex(fenRow, fCol));
    //handle en Passant
    setEnPassantFEN(moved, fRow, fCol, tRow);
    //change player
    activeColour = getOpponentColour(colour);
}

/**
 * Set En passant position using algebraic notation
 */
function setEnPassantFEN(moved, fRow, fCol, tRow)
{
    if (moved == 'p' && fRow == 6 && tRow == 4) {
        ep = letterAt[fCol] + '6';
    } else if (moved == 'P' && fRow == 1 && tRow == 3) {
        ep = letterAt[fCol] + '3';
    } else {
        ep = '-';
    }
}

/**
 * Update computer castling state
 * @param from
 */
function updateComputerCastling(from)
{
    var moved = getPieceFromFEN(fen, from[0], from[1]);
    if ($.inArray(moved, ['k', 'K', 'r', 'R']) !== -1) {
        //update castling availability
        updateCastling(moved, getPieceColour(moved), from[0], from[1]);
    }
}

/**
 * Reset game
 * @param colour - side to play as
 */
function resetState(colour)
{
    colour = colour || 'w';
    fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR';
    ep = '-';
    halfMoves = '0';
    fullMoves = '1';
    activeColour = 'w';
    castling = {'w': 'KQ', 'b': 'kq'};
    enPassant = null;
    castled = false;
    enPassantPerformed = false;
    checkThreat = null;
    $('.board').attr('id', 'game_' + colour + '_x');
    //reset taken pieces
    $('.subscript').each(function () {
        $(this).html('');
        $(this).closest('div.piece').addClass('hidden');
    });
    //reset abstract board
    abstractBoard = [
            ['R','N','B','Q','K','B','N','R'],
            ['P','P','P','P','P','P','P','P'],
            [false, false, false, false, false, false, false, false],
            [false, false, false, false, false, false, false, false],
            [false, false, false, false, false, false, false, false],
            [false, false, false, false, false, false, false, false],
            ['p','p','p','p','p','p','p','p'],
            ['r','n','b','q','k','b','n','r']
        ];
    //refresh actual board
    $.get(Routing.generate('cm_show_board', { gameID: colour }), function (data) {
        $('.board').closest('div.boardHolder').html(data);
        scaleBoard();
        $('div.board').removeClass('hidden');
        setMovement();
        if (colour == 'b') {
            //get first move
            worker.postMessage('position fen ' + getFEN(fen, activeColour, getCastlingFEN(), ep, halfMoves, fullMoves));
            worker.postMessage('go depth ' + searchDepth);
        }
    });
}
function switchPiecesWonLost()
{
    var pieces = $('div#piecesWon div.pieces');
    pieces.attr('id', 'tempSwitch');
    $('div#piecesWon .wonLostHeader').after($('div#piecesLost div.pieces'));
    $('div#piecesLost .wonLostHeader').after(pieces);
    pieces.attr('id', '');
}