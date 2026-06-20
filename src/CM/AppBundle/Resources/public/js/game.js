$(document).ready(function () {

    /**
     * Change labels on selection of human/computer opponent
     */
    $("input:radio[name='opponent']").on('change', function () {
        if (this.value == 1) {
            $('.computerSkillOnly').addClass('hidden');
            $('#skill1').prop('checked', true);
            $("label[for='skill1']").html('Best Match');
            $("label[for='skill2']").html('Lesser');
            $("label[for='skill3']").html('Greater');
            //hide options for guest players
            if (!$('#skillLevel').hasClass('visible')) {
                $('#skillLevel').addClass('hidden');
            }
            $('#minsPerPlayer').removeClass('hidden');
            $('#findGame').text('Find Match');
        } else {
            $('.computerSkillOnly').removeClass('hidden');
            $('#skill0').prop('checked', true);
            $("label[for='skill1']").html('Easy');
            $("label[for='skill2']").html('Moderate');
            $("label[for='skill3']").html('Difficult');
            //show options for computer opponent
            if ($('#skillLevel').hasClass('hidden')) {
                $('#skillLevel').removeClass('hidden');
            }
            $('#minsPerPlayer').addClass('hidden');
            $('#findGame').text('PLAY');
        }
    });

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
            of: ".container-fluid"
        },
        modal: true,
    });

    $('.closeable').dialog({
        open: function (event, ui) {
            $(".ui-dialog-titlebar-close").show();
        },
    });

    $('a#startGame').on('click', function () {
        $('#newGameOptions').dialog("open");
    });
    $('a#startGame2').on('click', function () {
        $('#newGameOptions').dialog("open");
    });

    $('a#showCurrentGames').on('click', function () {
        $('#currentGamesDialog').dialog("open");
    });

    $('a#cancelSearch').on('click', function (e) {
        e.preventDefault();
        cancelSearch();
        $('#findingGameDialog').dialog("close");
    });

    $('a#relaxSearch').on('click', function () {
        cancelSearch();
        //wait to finish
        var i = setInterval(function () {
            if (!matchSearch) {
                clearInterval(i);
            }
        }, 200);
        //create new ajax call
        createSearch(false);
        $(this).hide();
        $('#findingGameDialog').append('<center><p style="color:#0000ff;">Search relaxed</p></center>');
    });

    $('a#playComputer').on('click', function () {
        //cancel first
        cancelSearch();
        $('#findingGameDialog').dialog("close");
        var skill = $('#newSearchForm').find('input[name="skill"]:checked').val();
        location.href = $(this).attr('href') + '/' + skill;
    });

    $("a#findGame").on('click', function () {
        if ($('#newSearchForm').find('input[name="opponent"]:checked').val() == 1) {
            //human opponent
            //change dialog
            $('#newGameOptions').dialog("close");
            $('#findingGameDialog').dialog("open");
            setSearchMessage('');
            //reset relax search
            $('a#relaxSearch').show();
            createSearch(true);
        } else {
            //computer opponent
            var uri = $('a#playComputer').attr('href');
            var skill = $('#newSearchForm').find('input[name="skill"]:checked').val();
            location.href = uri + '/' + skill;
        }
    });
});

/**
 * Create new search
 */
function createSearch(match)
{
    //ajax form
    var form = $('#newSearchForm'),
        url = form.attr('action');
    if (match) {
        var skill = form.find('input[name="skill"]:checked').val(),
            duration = form.find('input[name="duration"]:checked').val();
        var search = $.post(url, {'skill': skill, 'duration': duration });
    } else {
        var search = $.post(url, {'skill': null, 'duration': null });
    }
    search.done(function (data) {
        startLoadingDots();
        //get search id
        var searchID = data['searchID'];
        setActiveSearch(searchID);
        //wait for search to be matched
        checkSearchMatched(searchID);
    });
    search.fail(function () {
        stopLoadingDots();
        setSearchMessage('Unable to create a game search right now. Please try again.');
    });
}

var matchSearch;
var activeSearchID = null;
var loadingDotsInterval = null;

function setSearchMessage(message)
{
    $('#findingGameDialog p').remove();
    if (message) {
        $('#findingGameDialog').append('<p>' + message + '</p>');
    }
}

function startLoadingDots()
{
    var loading = 0;
    stopLoadingDots();
    loadingDotsInterval = setInterval(function () {
        if (loading < 3) {
            $('#findingGameDialog span').append('.');
            loading++;
        } else {
            $('#findingGameDialog span').html('');
            loading = 0;
        }
    }, 600);
}

function stopLoadingDots()
{
    if (loadingDotsInterval) {
        clearInterval(loadingDotsInterval);
        loadingDotsInterval = null;
    }
    $('#findingGameDialog span').html('');
}

function setActiveSearch(searchID)
{
    activeSearchID = searchID;
    $('a#cancelSearch').attr('href', getCancelSearchUrl(searchID));
}

function clearActiveSearch()
{
    activeSearchID = null;
    $('a#cancelSearch').attr('href', $('a#cancelSearch').data('url-template').replace('SEARCH_ID', 0));
}

function getMatchSearchUrl(searchID)
{
    return $('#newSearchForm').data('match-url-template').replace('SEARCH_ID', searchID);
}

function getCancelSearchUrl(searchID)
{
    return $('a#cancelSearch').data('url-template').replace('SEARCH_ID', searchID);
}
/**
 * Find/create new game
 */
function checkSearchMatched(searchID)
{
    var url = getMatchSearchUrl(searchID);
    matchSearch = $.post(url);
    matchSearch.done(function (data) {
        if (data['matched']) {
            stopLoadingDots();
            clearActiveSearch();
            //load game
            location.href = data['gameURL'];
        } else if (data['cancelled']) {
            stopLoadingDots();
            clearActiveSearch();
        } else {
            //retry
            checkSearchMatched(searchID);
        }
    });
    matchSearch.fail(function (xhr, status) {
        if (status !== 'abort') {
            stopLoadingDots();
            clearActiveSearch();
            setSearchMessage('The matchmaking request failed. Please try again.');
        }
    });
}

/**
 * Cancel search
 */
function cancelSearch()
{
    var i = setInterval(function () {
        if (matchSearch) {
            matchSearch.abort();
            var url = getCancelSearchUrl(activeSearchID || 0);
            var cancel = $.post(url);
            matchSearch = null;
            clearActiveSearch();
            stopLoadingDots();
            clearInterval(i);
        }
    }, 200);
}
