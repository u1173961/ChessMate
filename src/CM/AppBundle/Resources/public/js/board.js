$(document).ready(function () {
    //scale board
    handleResizes();
    $('div.board').removeClass('hidden');
    $(window).resize(function () {
        handleResizes();
    });

    setMovement();
    //override position of loading dialog
    $('#joiningGameDialog').dialog({
        position: {
            my: "center center",
            at: "center center",
            of: ".container-fluid"
        },
    });
    $('#gameOverDialog').dialog({
        position: {
            my: "center center",
            at: "center center",
            of: ".container-fluid"
        },
    });

    /**
     * Join game/check opponent has joined &
     * wait for first move (also used for reloads)
     */
    if (typeof activePlayer !== 'undefined') {
        //get game id
        var game = $('.board').attr('id').split('_');
        if (!inProgress) {
            //join game/check joined
            joinGame(game[2]);
        } else {
            performOnLoadActions(game[2]);
        }
    }

    $('.choosablePiece').click(function () {
        swapPawn(this.id);
    });

    $('#resign').click(function (e) {
        e.preventDefault();
        if (!gameOver) {
            $.post($(this).attr('href'));
        }
    });

    $('#offerDraw').click(function (e) {
        e.preventDefault();
        if (!gameOver) {
            $.post($(this).attr('href'));
        }
    });

    $('#acceptDraw').click(function (e) {
        e.preventDefault();
        if (!gameOver) {
            acceptDraw($(this).attr('href'));
        }
    });

    $('#acceptGameOver').click(function (e) {
        $('#gameOverDialog').dialog("close");
    });

    //toggle chat
    $('a#toggleChat').on('click', function (e) {
        e.preventDefault();
        var chat = $('div#chatBox');
        if (chat.hasClass('hidden')) {
            chat.removeClass('hidden');
            $(this).html('Disable Chat');
        } else {
            chat.addClass('hidden');
            $(this).html('Enable Chat');
        }
        var url = $(this).attr('href');
        $.ajax({
            type: "POST",
            url: url
        });
    });

    //listener for sent chat
    $('form#chatSend').on('submit', function (e) {
        e.preventDefault();
        var msg = $('input#chatMsg').val().trim();
        var url = $(this).attr('action');
        $.ajax({
            type: "POST",
            url: url,
            data: {'msg': '<span class="purple">' + msg + '</span><br>'},
            success: function (data) {
                lastChatSeen = data['chatID'];
            }
        });
        //get username
        var user = $('span#pName2').html().split(':');
        //add to own window
        $('div#chatLog').append('<label>' + user[0].trim() + ':&nbsp;</label><span class="blue">' + msg + '</span><br>');
        //always scroll to bottom
        var scroll = $('div#chatDisplay');
        scroll.scrollTop(scroll.scrollTop() + 300);
        $('input#chatMsg').val('');
    });
});
//var for point & click
var selectedPiece = null;
//allow player to move
var playersTurn = true;
//timer interval
var tInterval;
var pTime = 0;
var opTime = 0;
//global for swapping pawn
var gFrom = [];
var lastChatSeen = 0;

function setMovement()
{
    /**
     * Make pieces draggable
     */
    $('.ui-draggable').draggable({
        containment : '.board',
        revert: function () {
            //validate based on droppable.drop
            if ($(this).hasClass('invalid')) {
                $(this).removeClass('invalid');
                return true;
            }
        },
        helper: "clone",
        appendTo: ".board",
        start: function (event, ui) {
            //remove any highlight
            $(this).closest('div.square').stop(true,true);
            selectedPiece = null;
            return $(event.target).fadeTo(0, 0);
        },
        stop: function (event, ui) {
            //check for game over (for non-games/computer opponent)
            if ($('.board').attr('id').charAt(7) == 'x' && !newPiece) {
                var over = checkGameOver(getPieceColour(event.target.id[0]));
                if (over) {
                    alert(getGameOverMessage(over));
                    gameOver = true;
                }
            }
            return $(event.target).fadeTo(0, 1);
        },
    });

    /**
     * Make squares droppable
     */
    $('.square').droppable({
        accept: '.piece',
        drop: validateDragAndDrop,
    });

    //highlight own pieces on click
    $('.ui-draggable').on('click', function (e) {
        removeMoveHighlights();
        //check player's turn and piece (if actual game)
        var piece = this.id.charAt(0);
        var colour = getPieceColour(piece);
        if (!gameOver && playersTurn) {
            if (selectedPiece) {
                if (selectedPiece.id != $(this).id) {
                    selectedPiece.closest('div.square').stop(true,true);
                    if (colour == getPieceColour(selectedPiece.id.charAt(0))) {
                        //reselect piece
                        selectedPiece = $(this);
                        $(this).closest('div.square').effect("highlight", 50000);
                    } else {
                        //attempt take
                        validatePointAndClick(selectedPiece, selectedPiece.closest('div.square').attr('id'), $(this).closest('div.square').attr('id'));
                        selectedPiece = null;
                    }
                }
            } else if ($('.board').attr('id').charAt(5) == 'x' || colour == $('.board').attr('id').charAt(5)) {
                //select own piece
                selectedPiece = $(this);
                selectedPiece.closest('div.square').effect("highlight", 50000);
            }
        }
    });
    //handle point and click movement
    $('.square').on('click', function (e) {
        if (selectedPiece && selectedPiece.hasClass('piece') && selectedPiece.closest('div.square').attr('id') != this.id) {
            selectedPiece.closest('div.square').stop(true,true);
            validatePointAndClick(selectedPiece, selectedPiece.closest('div.square').attr('id'), this.id);
            selectedPiece = null;
        }
    });
}

/**
 * Join game/check opponent has joined
 */
function joinGame(gameID)
{
    //show loading dialog
    $('#joiningGameDialog').dialog("open");
    var loading = 0;
    var joining = setInterval(function () {
        if (loading < 3) {
            $('#joiningGameDialog span').append('.');
            loading++;
        } else {
            $('#joiningGameDialog span').html('');
            loading = 0;
        }
    }, 600);

    $.ajax({
        type: "POST",
        url: Routing.generate('cm_join_game', { gameID: gameID }),
        success: function (data) {
            //close loading dialog
            clearInterval(joining);
            $('#joiningGameDialog').dialog("close");
            if (!data['joined']) {
                //game cancelled
                alert('Game aborted by opponent!');
                //back to start
                location.href = Routing.generate('cm_start');
            } else {
                performOnLoadActions(gameID);
                inProgress = true;
            }
        }
    });
}

/**
 * Game initialisation for load/reload
 */
function performOnLoadActions(gameID)
{
    opTime = getSecondsLeft($('#tLeft1')) * 1000;
    pTime = getSecondsLeft($('#tLeft2')) * 1000;
    //if not players turn
    if ((activePlayer === 0 && $('.board').attr('id').charAt(5) == 'b')
            || (activePlayer === 1 && $('.board').attr('id').charAt(5) == 'w')) {
        playersTurn = false;
        //start opponent's timer
        startTimer($('#tLeft1'), opTime);
    } else {
        //start own timer
        startTimer($('#tLeft2'), pTime);
    }
    //open general listener
    listen(gameID);
}

/**
 * Switch active player/timer
 */
function switchPlayer()
{
    clearInterval(tInterval);
    if ($('#tLeft1').hasClass('red')) {
        $('#tLeft1').removeClass('red');
        startTimer($('#tLeft2'), pTime + 100);
        playersTurn = true;
    } else {
        playersTurn = false;
        setTimeout(function () {
            $('#tLeft2').removeClass('red');
        }, 100);
        startTimer($('#tLeft1'), opTime + 200);
    }
}

/**
 * Start given timer
 */
function startTimer(timer, actualTimeLeft)
{
    timer.addClass('red');
    var time = timer.text().split(':');
    var mins = time[0];
    var secs = time[1];
    var tSpeed = actualTimeLeft / getSecondsLeft(timer);
    //use Date() for more accurate time-keeping
    var start = new Date().getTime();
    var elapsed;
    //track milliseconds
    var ms = 0;
    //synch timer every 100ms
    tInterval = setInterval(function () {
        elapsed = new Date().getTime() - start;
        if (elapsed + ms >= tSpeed) {
            ms = elapsed - 1000;
            start = start + tSpeed;
            time = tick(mins, secs);
            mins = time[0];
            secs = time[1];
            timer.text(mins + ':' + secs);
        }
    }, 100);
}

/**
 * Get next second in countdown
 */
function tick(mins, secs)
{
    if (secs == '00') {
        if (mins == '0') {
            //end game
            clearInterval(tInterval);
        } else {
            secs = '59';
            mins = mins - 1;
        }
    } else {
        secs = secs - 1;
        if (secs < 10) {
            secs = '0' + secs;
        }
    }
    return [mins, secs];
}

function getSecondsLeft(timer)
{
    var time = timer.text().split(':');
    var mins = time[0];
    var secs = time[1];
    return parseInt(mins * 60, 10) + parseInt(secs, 10);
}

/**
 * Validate drag & drop move
 */
function validateDragAndDrop(event, ui)
{
    //get moved piece
    var piece = ui.draggable.attr('id').charAt(0);
    var colour = getPieceColour(piece);
    //check player's turn and piece (if actual game)
    if (!checkPieceAndTurnForPlayer(colour)) {
        //invalidate move
        ui.draggable.addClass('invalid');
        return false;
    }
    //get abstract indices for from/to squares
    var from = getAbstractedSquareIndex(ui.draggable.parent().attr('id'));
    var to = getAbstractedSquareIndex(this.id);
    //validate move
    if (!validateMove(piece, colour, from, to, 'Won')) {
        //invalidate move
        ui.draggable.addClass('invalid');
        return false;
    }
    //check for pawn reaching opposing end
    if (pawnHasReachedOtherSide(piece, colour, to[0])) {
        //ajax move on piece selection
        gFrom = from;
        openPieceChooser(colour);
    } else if ($('.board').attr('id').charAt(7) != 'x' && !gameOver) {
        //ajax move & confirm validity
        sendMove(from, to, colour);
    }
    //center piece
    $(this).append(ui.draggable.css('position','static'));
    //check if playing computer
    if (typeof computerOpponent !== 'undefined') {
        //defined in computer.js
        switchToComputerOpponent(from, to);
    }

    return true;
}

/**
 * Validate/perform point & click move
 * @param array from grid-ref.
 * @param array to grid-ref
 * @param bool
 */
function validatePointAndClick(moved, gridFrom, gridTo)
{
    var piece = moved.attr('id').charAt(0);
    var colour = getPieceColour(piece);
    //check player's turn and piece (if actual game)
    if (!checkPieceAndTurnForPlayer(colour)) {
        //invalidate move
        return false;
    }
    //get abstract indices for from/to squares
    var from = getAbstractedSquareIndex(gridFrom);
    var to = getAbstractedSquareIndex(gridTo);
    //validate move
    if (!validateMove(piece, colour, from, to, 'Won')) {
        return false;
    }
    //check for pawn reaching opposing end
    if (pawnHasReachedOtherSide(piece, colour, to[0])) {
        //ajax move on piece selection
        gFrom = from;
        openPieceChooser(colour);
    } else if ($('.board').attr('id').charAt(7) != 'x' && !gameOver) {
        //ajax move & confirm validity
        sendMove(from, to, colour);
    }
    //make move
    moved.position({
        of: 'div#' + gridTo
    });
    //center piece
    $('div#' + gridTo).append(moved.css('position','static'));
    //check for game over (for non-games/computer opponent)
    if ($('.board').attr('id').charAt(7) == 'x' && !newPiece) {
        var over = checkGameOver(colour);
        if (over) {
            alert(getGameOverMessage(over));
            gameOver = true;
        }
    }
    //check if playing computer
    if (typeof computerOpponent !== 'undefined') {
        //defined in computer.js
        switchToComputerOpponent(from, to);
    }

    return true;
}

/**
 * Check opponent's move
 * @param array from grid-ref.
 * @param array to grid-ref
 * @param bool|string swapped has pawn been swapped
 */
function checkMoveByOpponent(from, to, swapped, enPassant, newCastling, newFEN)
{
    var gridFrom = getGridRefFromAbstractIndices(from[0], from[1]);
    var gridTo = getGridRefFromAbstractIndices(to[0], to[1]);
    //get new board
    var newBoard = getBoardFromFEN(newFEN);
    //get moved piece
    var moved = getOccupant(gridFrom);
    var piece = moved.attr('id').charAt(0);
    var colour = getPieceColour(piece);
    //check piece exists & question move validity
    if (moved.length && validateMoveIn(piece, colour, from, to, swapped, enPassant, newCastling, newBoard)) {
        //check if opponent's move ended game
        var over = checkGameOver(colour);
        //save move
        saveMove(over);
        //make move
        makeOpponentMove(moved, gridFrom, gridTo, swapped);
        return true;
    }
    //validate server-side & find cheat
    findCheat();

    return false;
}
function makeOpponentMove(piece, gridFrom, gridTo, swapped)
{
    piece.position({
        of: 'div#' + gridTo
    });
    //center piece
    $('div#' + gridTo).append(piece.css('position','static'));
    //highlight
    highlightMove(gridFrom, gridTo);
    //perform pawn swap
    if (swapped) {
        //get new id
        var num = getNewPieceNumber(swapped);
        //change piece
        piece.html($('#pick_' + swapped).html());
        //set new id
        piece.attr('id', swapped + '_' + num);
    }
}
function highlightMove(gridFrom, gridTo)
{
    $('div#' + gridFrom).addClass('startPos');
    $('div#' + gridTo).addClass('endPos');
}
function removeMoveHighlights()
{
    $('div.startPos').removeClass('startPos')
    $('div.endPos').removeClass('endPos')
}

/**
 * Validate move made by opponent
 * If invalid, one of the players has cheated
 */
function validateMoveIn(piece, colour, from, to, newPiece, newEP, newCastling, newBoard)
{
    //check opponent's piece
    if (colour == $('.board').attr('id').charAt(5)) {
        return false;
    }
    //check if target is occupied by own piece
    if (occupiedByOwnPiece(to[0], to[1], colour)) {
        return false;
    }
    //validate move
    if (!validateMove(piece, colour, from, to, 'Lost')) {
        return false;
    }
    //ensure en passant is correct
    if (enPassant != newEP && (enPassant[0] != newEP[0] || enPassant[1] != newEP[1])) {
        return false;
    }
    //check castling
    if (JSON.stringify(newCastling) != JSON.stringify(castling)) {
        return false;
    }
    //check swapped piece
    if (newPiece) {
        if (!pawnHasReachedOtherSide(piece, colour, to[0])) {
            return false;
        }
        //update abstract board
        abstractBoard[to[0]][to[1]] = newPiece;
    }
    //check boards match following updates
    for (var row = 0; row < 8; row++) {
        for (var col = 0; col < 8; col++) {
            if (abstractBoard[row][col] != newBoard[row][col]) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Validate move
 */
function validateMove(piece, colour, from, to, takenSide)
{
    //check if target is occupied by own piece
    if (occupiedByOwnPiece(to[0], to[1], colour)) {
        return false;
    }
    //validate move
    var valid = validatePieceType(piece, colour, from, to);
    if (valid) {
        if (checkEnPassantPerformed(to)) {
            takePiece(getGridRefFromAbstractIndices(from[0],to[1]), takenSide);
        } else {
            //get target square occupant - in case of revert
            var occupant = abstractBoard[to[0]][to[1]];
            //update abstract board
            updateAbstractBoard(from, to);
            if (castled) {
                //check already checked
                moveCastle(to, colour);
            } else {
                //if in check, invalidate move
                //get king's position
                var kingSquare = getKingSquare(colour);
                if (inCheck(getOpponentColour(colour), kingSquare)) {
                    //highlight king briefly
                    var king = getOccupant(getGridRefFromAbstractIndices(kingSquare[0], kingSquare[1]));
                    var highlight = setInterval(function () {
                        king.effect("highlight", {color:"#ff3333"}, 250);
                    }, 500);
                    setTimeout(function () {
                        clearInterval(highlight);
                    }, 2500);
                    //revert board
                    updateAbstractBoard(to, from);
                    abstractBoard[to[0]][to[1]] = occupant;
                    //invalidate move
                    return false;
                }
                if (occupant) {
                    //take piece
                    takePiece(getGridRefFromAbstractIndices(to[0],to[1]), takenSide);
                }
            }
            if ($.inArray(piece, ['k', 'K', 'r', 'R']) !== -1) {
                //update castling availability
                updateCastling(piece, colour, from[0], from[1]);
            }
            setEnPassant(piece, from[0], from[1], to[0]);
        }
    }

    return valid;
}

/**
 * Check player's turn and piece (if actual game)
 * @param colour
 * @returns
 */
function checkPieceAndTurnForPlayer(colour)
{
    //check player's turn and piece (if actual game)
    if (gameOver || !playersTurn || ($('.board').attr('id').charAt(5) != 'x'
        && colour != $('.board').attr('id').charAt(5))) {
        return false;
    }
    return true;
}

/**
 * Check for pawn reaching opposing end
 * @param piece
 * @param colour
 * @param toY
 * @return Boolean
 */
function pawnHasReachedOtherSide(piece, colour, toY)
{
    if (piece.toUpperCase() == 'P' && ((colour == 'w' && toY == 7) || (colour == 'b' && toY == 0))) {
        return true;
    }
    return false;
}

/**
 * Move castle in accordance with castling.
 * Validity must be pre-checked
 * @param to [y,x]
 * @param colour the piece's colour
 */
function moveCastle(to, colour)
{
    to[0] = parseInt(to[0], '10')
    if (to[1] == 2) {
        $('#d_' + (to[0] + 1)).append($('#' + getPlayerPiece(colour, 'r') + '_' + to[0] + '0'));
    } else {
        $('#f_' + (to[0] + 1)).append($('#' + getPlayerPiece(colour, 'r') + '_' + to[0] + '7'));
    }
    castled = false;
}

/**
 * Find cheat, if validity consensus differs
 */
function findCheat()
{
    //get game id
    var game = $('.board').attr('id').split('_');
    $.ajax({
        type: "POST",
        url: Routing.generate('cm_find_cheat', { gameID: game[2] }),
        success: function (data) {
            //re-open listener
            listen(game[2]);
        }
    });
}

/**
 * Accept draw
 */
function acceptDraw(url)
{
    $.ajax({
        type: "POST",
        url: url
    });
    $('#drawOffered').addClass('hidden');
}

/**
 * Get game over message
 * @param over
 * @return String
 */
function getGameOverMessage(over)
{
    var message = '';
    if (over !== false) {
        message = 'Game Over: ';
        if (over == 1) {
            message = message + 'Drawn';
        } else if (over == 2) {
            message = message + 'Stalemate';
        } else if (over == 3) {
            message = message + 'Checkmate';
        }
    }
    return message;
}

/**
 * Ajax move for opponent retrieval/validation
 * & wait for opponent's move
 * @param array from [y,x]
 * @param array to [y,x]
 * @param string colour
 */
function sendMove(from, to, colour)
{
    //check if move ended game
    var over = false;
    var message = '';
    if (!newPiece) {
        over = checkGameOver(colour);
        message = getGameOverMessage(over);
    }
    //get game id
    var game = $('.board').attr('id').split('_');
    var data = {
        'gameID' : game[2],
        'fen' : getFENFromBoard(abstractBoard),
        'castling': castling,
        'from' : from,
        'to' : to ,
        'enPassant' : enPassant,
        'newPiece' : newPiece,
        'gameOver' : over
    };
    $.ajax({
        type: "POST",
        url: Routing.generate('cm_send_move', {}),
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify(data)
    });
    switchPlayer();
    newPiece = false;
}

/**
 * Save opponent's move
 * @param bool|int over 1=drawn, 2=stalemate, 3=checkmate
 */
function saveMove(over)
{
    //get game id
    var game = $('.board').attr('id').split('_');
    //save move
    $.ajax({
        type: "POST",
        url: Routing.generate('cm_save_move', { gameID: game[2] }),
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify({'gameOver': over}),
        success: function (data) {
            //re-open listener
            listen(game[2]);
        }
    });
}

/**
 * Open piece-chooser dialog
 */
function openPieceChooser(colour)
{
    $('#choosePiece_' + colour).dialog("open");
}

/**
 * Get new piece number, for html id
 * @param newPiece
 */
function getNewPieceNumber(newPiece)
{
    //get new id
    var num = 1;
    //check for conflict
    var conflict = $('#' + newPiece + '_' + num);
    while (conflict.length) {
        num++;
        conflict = $('#' + newPiece + '_' + num);
    }
    return num;
}

var newPiece = false;
/**
 * Swap pawn on selection
 */
function swapPawn(pieceID)
{
    //get selected piece
    var piece = pieceID.charAt(5);
    var colour = getPieceColour(piece);
    //get new piece & id
    newPiece = piece;
    var num = getNewPieceNumber(newPiece);
    //get pawn position
    var endRow = 7;
    if (colour == 'b') {
        endRow = 0;
    }
    var pawnCol = $.inArray(getPlayerPiece(colour, 'p'), abstractBoard[endRow]);
    //update abstract board
    abstractBoard[endRow][pawnCol] = newPiece;
    //update real board
    var pawn = getOccupant(getGridRefFromAbstractIndices(endRow, pawnCol));
    //change piece
    pawn.html($('#' + pieceID).html());
    //set new id
    pawn.attr('id', newPiece + '_' + num);
    //close piece-chooser
    $('#choosePiece_' + colour).dialog("close");
    //ajax move if real game
    if ($('.board').attr('id').charAt(7) != 'x' && !gameOver) {
        //send move for validation
        sendMove(gFrom, [endRow, pawnCol], colour);
    } else if (typeof computerOpponent !== 'undefined') {
        //defined in computer.js
        fen = swapPieceInFEN(fen, newPiece, [endRow, pawnCol]);
    }
}

/**
 * Get board square's abstracted index in array
 */
function getAbstractedSquareIndex(squareID)
{
    //get grid reference
    var square = squareID.split('_');
    //convert to array indices
    return getAbstractIndicesFromGridRef(square[0], parseInt(square[1], '10'));
}

/**
 * Check for takeable piece and remove if found
 * Given square must already be checked for own piece
 */
function checkAndTakePiece(square, wonOrLost)
{
    if (!vacant(square[0],square[1])) {
        takePiece(getGridRefFromAbstractIndices(square[0],square[1]), wonOrLost);
    }
}

/**
 * Remove piece, from given square, and move to side
 */
function takePiece(toSquare, wonOrLost)
{
    //get taken piece
    var taken = getOccupant(toSquare);
    var newID = ' div#' + taken.attr('id').charAt(0) + '_t';
    taken.remove();
    if ($(newID + ' sub.subscript').length) {
        var newT = $('div#pieces' + wonOrLost + newID);
        if (newT.hasClass('hidden')) {
            newT.removeClass('hidden');
        } else if ($(newID + ' sub.subscript:first').html().trim() != '') {
            //increment count
            $(newID + ' sub.subscript:first').html(parseInt($(newID + ' sub.subscript:first').html(), 10) + 1)
        } else {
            $(newID + ' sub.subscript:first').html(2);
        }
    }
}

/**
 * Get occupant of given square
 */
function getOccupant(squareID)
{
    return $('#' + squareID).children('div.piece');
}

/**
 * Long poll
 * @param gameID
 * @param gameOverReceived
 */
function listen(gameID, delay)
{
    delay = delay || 100;
    $.ajax({
        type: "POST",
        url: Routing.generate('cm_listen', {}),
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify({'gameID': gameID, 'opChatty': opChatty, 'lastChat': lastChatSeen, 'overReceived': gameOver}),
        success: function (data) {
            if (data['change']) {
                //display any chat messages
                handleChat(data['chat']);
                //check for game over or new move
                if (data['moved']) {
                    if (checkMoveByOpponent(data['from'], data['to'], data['swapped'], data['enPassant'], data['castling'], data['newFEN'])) {
                        pTime = data['pTimeLeft'];
                        opTime = data['opTimeLeft'];
                        switchPlayer();
                    }
                } else {
                    if (data['gameOver']) {
                        updateGameOver(data['pRating'], data['opRating'], data['overMsg']);
                    } else if (data['drawOffered']) {
                        //show draw offered options
                        $('#drawOffered').removeClass('hidden');
                        //hide in 10 seconds if not accepted
                        setTimeout(function () {
                            if (!$('#drawOffered').hasClass('hidden')) {
                                $('#drawOffered').addClass('hidden');
                            }
                        }, 10000);
                    }
                    listen(gameID);
                }
            } else {
                listen(gameID);
            }
        },
        error: function (data) {
            //exponentially increase time between attempts
            setInterval(function () {
                listen(gameID, delay * 10);
            }, delay);
        }
    });
}

/**
 * Show game over message, update displayed ratings,
 * stop timers & hide controls
 * @param int pRating
 * @param int opRating
 * @param string overMsg
 */
function updateGameOver(pRating, opRating, overMsg)
{
    gameOver = true;
    showRatingDifferences(pRating, opRating);
    //update ratings
    $('label#rating1').html('(' + opRating + ')');
    $('label#rating2').html('(' + pRating + ')');
    //hide resign/offer draw
    if (!$('a#offerDraw').hasClass('hidden')) {
        $('a#offerDraw').addClass('hidden');
        $('a#resign').addClass('hidden');
    }
    //show new game button
    $('a#startGame2').closest('div').removeClass('hidden');
    //stop timers
    clearInterval(tInterval);
    //show message
    $('#gameOverDialog h4').html(overMsg);
    $('#gameOverDialog').dialog("open");
}

/**
 * Show rating changes in chat box
 */
function showRatingDifferences(pRating, opRating)
{
    var pDisplay = $('label#rating2');
    var opDisplay = $('label#rating1');
    var oldPR = pDisplay.html().substr(1, pDisplay.html().length - 2);
    var oldOpR = opDisplay.html().substr(1, opDisplay.html().length - 2);
    var pChange = parseInt(pRating, 10) - parseInt(oldPR, 10);
    var pColour = 'red';
    if (pChange > -1) {
        pChange = '+' + pChange;
        pColour = 'blue';
    }
    var opChange = parseInt(opRating, 10) - parseInt(oldOpR, 10);
    var opColour = 'red';
    if (opChange > -1) {
        opChange = '+' + opChange;
        opColour = 'blue';
    }
    //get usernames
    var player = $('span#pName2').html().split(':');
    var opponent = $('span#pName1').html().split(':');
    $('div#chatLog').append('<label>Game Over: &nbsp;</label>');
    $('div#chatLog').append('<label>' + opponent[0] + '</label><span class="' + opColour + '"> ' + opChange + '</span>');
    $('div#chatLog').append('<label>, &nbsp; ' + player[0] + '</label><span class="' + pColour + '"> ' + pChange + '</span><br>');
}

/**
 * Handle chat messages/toggle
 * @param data ajax response
 */
function handleChat(chat)
{
    if (chat['msgs'][0] > lastChatSeen) {
        lastChatSeen = chat['msgs'][0];
        for (var i = 0; i < chat['msgs'][1].length; i++) {
            $('div#chatLog').append(chat['msgs'][1][i].trim());
        }
        //always scroll to bottom
        var scroll = $('div#chatDisplay');
        scroll.scrollTop(scroll.scrollTop() + 300);
    }
    //check for chat toggled
    if (chat['toggled']) {
        opChatty = !opChatty;
    }
}
/**
 * Scale board to screen size and shift controls layout
 * @returns
 */
function handleResizes()
{
    adjustLayout();
    scaleBoard();
    fixjQueryDialog();
}
function adjustLayout()
{
    var winWidth = $(window).width();
    if (!$('.boardButton').length) {
        if ($('div.boardHolder').width() < 538 && winWidth > 684) {
            setSmallView();
        } else if (winWidth < 685) {
            setMobileView();
        } else {
            setNormalView();
        }
    }
}
function scaleBoard()
{
    var pieceSize = 75;
    var offset = $('div#timer1.moved').length ? $('div#timer1.moved').height() * 2 : 25;
    var newSize = $(window).height() - offset;
    var xSpace = $('div.boardHolder').width() - (getPixelValueFromCSS('padding-left', 'div.wrapper') * 2);
    newSize = newSize > xSpace ? xSpace : newSize;
    newSize = Math.min(800, Math.round(newSize / 8) * 8);
    $('div.board').css('width', newSize + 'px');
    $('div.board').css('height', newSize + 'px');
    pieceSize = Math.round(pieceSize * (newSize / 680));
    $('div.board div.piece').css('font-size', pieceSize + 'px');
    if ($('.boardButton').length) {
        $('.boardButton').css('width', (newSize + 5) + 'px');
    }
}
function fixjQueryDialog()
{
    //fix jquery dialog if showing
    var dialog = $('.ui-dialog.ui-widget:visible');
    if (dialog.length) {
        dialog.css('left', Math.round((winWidth - dialog.width()) / 2) + 'px');
        dialog.css('top', Math.round($('div.boardHolder').offset().top + 50) + 'px');
    }
}
function setSmallView()
{
    //restore
    var timer1 = $('div#timer1');
    if (timer1.hasClass('moved')) {
        timer1.removeClass('moved');
        $('div#piecesLost').before(timer1);
    }
    var piecesWon = $('div#piecesWon');
    if (piecesWon.hasClass('moved')) {
        piecesWon.removeClass('moved');
        if ($('#moveableMiddle').length) {
            $('div.gameControls div.separator').after(piecesWon);
        } else {
            $('div#midGameOptions').after(piecesWon);
        }
    }
    var timer2 = $('div#timer2');
    if (timer2.hasClass('moved')) {
        timer2.removeClass('moved');
        $('div#piecesWon').after(timer2);
    }
    //set new
    var chatOrSlider = getMoveableOptionalControl();
    if (chatOrSlider.length) {
        if (!chatOrSlider.hasClass('moved')) {
            chatOrSlider.addClass('moved');
            $('div.wrapper').append(chatOrSlider);
        }
    }
}
function setMobileView()
{
    //restore
    var chatOrSlider = getMoveableOptionalControl();
    if (chatOrSlider.length) {
        if (chatOrSlider.hasClass('moved')) {
            chatOrSlider.removeClass('moved');
            $('div#piecesLost').after(chatOrSlider);
        }
    }
    //set new
    var timer2 = $('div#timer2');
    if (!timer2.hasClass('moved')) {
        timer2.addClass('moved');
        $('div.gameControls').prepend(timer2);
    }
    var timer1 = $('div#timer1');
    if (!timer1.hasClass('moved')) {
        timer1.addClass('moved');
        $('div#separator').after(timer1);
    }
    var piecesWon = $('div#piecesWon');
    if (!piecesWon.hasClass('moved')) {
        piecesWon.addClass('moved');
        $('div#piecesLost').after(piecesWon);
    }
}
function setNormalView()
{
    //restore
    var chatOrSlider = getMoveableOptionalControl();
    if (chatOrSlider.length) {
        if (chatOrSlider.hasClass('moved')) {
            chatOrSlider.removeClass('moved');
            $('div#piecesLost').after(chatOrSlider);
        }
    }
    var timer1 = $('div#timer1');
    if (timer1.hasClass('moved')) {
        timer1.removeClass('moved');
        $('div#piecesLost').before(timer1);
    }
    var piecesWon = $('div#piecesWon');
    if (piecesWon.hasClass('moved')) {
        piecesWon.removeClass('moved');
        if ($('#moveableMiddle').length) {
            $('div.gameControls div.separator').after(piecesWon);
        } else {
            $('div#midGameOptions').after(piecesWon);
        }
    }
    var timer2 = $('div#timer2');
    if (timer2.hasClass('moved')) {
        timer2.removeClass('moved');
        $('div#piecesWon').after(timer2);
    }
}
function getMoveableOptionalControl()
{
    if ($('div#chatBox').length) {
        return $('div#chatBox');
    }
    if ($('div#moveableMiddle').length) {
        return $('div#moveableMiddle');
    }
    return false;
}